<?php

namespace phpDocumentor\Plugin\Json\Writer;

use phpDocumentor\Descriptor\ClassDescriptor;
use phpDocumentor\Descriptor\Collection;
use phpDocumentor\Descriptor\InterfaceDescriptor;
use phpDocumentor\Descriptor\NamespaceDescriptor;
use phpDocumentor\Descriptor\ProjectDescriptor;
use phpDocumentor\Descriptor\TraitDescriptor;
use phpDocumentor\Transformer\Transformation;
use phpDocumentor\Transformer\Writer\WriterAbstract;
use Zend\Stdlib\Exception\ExtensionNotLoadedException;

class Json extends WriterAbstract
{

    private $keys = ['classes', 'interfaces', 'traits'];

    /**
     * Invokes the query method contained in this class.
     *
     * @param ProjectDescriptor $project        Document containing the structure.
     * @param Transformation    $transformation Transformation to execute.
     *
     * @return void
     */
    public function transform(ProjectDescriptor $project, Transformation $transformation)
    {
        $filename = $this->getDestinationPath($transformation);
        $graph = [
            'namespaces' => []
        ];
        $this->buildNamespaceTree($graph, $project->getNamespace());
        // $containers = [];
        // foreach (self::$keys as $k) {
        //     $containers = array_merge(
        //                         $containers,
        //                         $project->getIndexes()->get($k, new Collection)->getAll()
        //                     );
        // }
        // foreach ($containers as $container) {
        //     $from_name = $container->getFullyQualifiedStructuralElementName();
        //     $parents     = array();
        //     $implemented = array();
        //     if ($container instanceof ClassDescriptor) {
        //         if ($container->getParent()) {
        //             $parents[] = $container->getParent();
        //         }
        //         $implemented = $container->getInterfaces()->getAll();
        //     }
        //     if ($container instanceof InterfaceDescriptor) {
        //         $parents = $container->getParent()->getAll();
        //     }
        //     foreach ($parents as $parent) {
        //         $edge = $this->createEdge($graph, $from_name, $parent);
        //         $edge->setArrowHead('empty');
        //         $graph->link($edge);
        //     }
        //     foreach ($implemented as $parent) {
        //         $edge = $this->createEdge($graph, $from_name, $parent);
        //         $edge->setStyle('dotted');
        //         $edge->setArrowHead('empty');
        //         $graph->link($edge);
        //     }
        // }
        file_put_contents($filename, json_encode($graph, JSON_PRETTY_PRINT));
    }

    /**
     * Builds a tree of namespace subgraphs with their classes associated.
     *
     * @param array       $graph
     * @param NamespaceDescriptor $namespace
     *
     * @return void
     */
    protected function buildNamespaceTree(&$graph, NamespaceDescriptor $namespace)
    {
        $full_namespace_name = $namespace->getFullyQualifiedStructuralElementName();
        if ($full_namespace_name == '\\') {
            $full_namespace_name = 'Global';
        }
        $sub_graph = [
            'fqn' => $full_namespace_name,
            'namespace' => $namespace->getName()
        ];
        $elements = array_merge(
            $namespace->getClasses()->getAll(),
            $namespace->getInterfaces()->getAll(),
            $namespace->getTraits()->getAll()
        );
        /** @var ClassDescriptor|InterfaceDescriptor|TraitDescriptor $subElement */
        foreach ($elements as $subElement) {
            $node = [
                'name' => $subElement->getName()
                //'fqn' => $subElement->getFullyQualifiedStructuralElementName()
            ];
            if ($subElement instanceof ClassDescriptor) {
                if ($subElement->isAbstract()) {
                    $node['abstract'] = true;
                }
                if ($subElement->isFinal()) {
                    $node['final'] = true;
                }
                if (($parent = $subElement->getParent()) && is_object($parent)) {
                    $node['extends'] = $parent->getFullyQualifiedStructuralElementName();
                }
                foreach ($subElement->getInterfaces()->getAll() as $implements) {
                    if (!is_object($implements)) {
                        continue;
                    }
                    if (!isset($node['implements'])) {
                        $node['implements'] = [];
                    }
                    $node['implements'][] = $implements->getFullyQualifiedStructuralElementName();
                }
                foreach ($subElement->getMethods() as $method) {
                    if (!$method || ($method->getVisibility() !== 'public')) {
                        continue;
                    }
                    if (!isset($node['methods'])) {
                        $node['methods'] = [];
                    }
                    $m = [
                        'name' => $method->getName()
                    ];
                    foreach ($method->getArguments() as $argument) {
                        if (!isset($m['args'])) {
                            $m['args'] = [];
                        }
                        $m['args'][] = $argument->getName();
                    }
                    $node['methods'][] = $m;
                }
            }
            if ($subElement instanceof InterfaceDescriptor) {
                $node['interface'] = true;
            }
            if ($subElement instanceof TraitDescriptor) {
                $node['trait'] = true;
            }
            if (!isset($sub_graph['nodes'])) {
                $sub_graph['nodes'] = [];
            }
            $sub_graph['nodes'][] = $node;
        }
        foreach ($namespace->getChildren()->getAll() as $element) {
            $this->buildNamespaceTree($sub_graph, $element);
        }
        $graph['namespaces'][] = $sub_graph;
    }

    /**
     * @param \phpDocumentor\Transformer\Transformation $transformation
     * @return string
     */
    protected function getDestinationPath(Transformation $transformation)
    {
        $filename = $transformation->getTransformer()->getTarget()
            . DIRECTORY_SEPARATOR . $transformation->getArtifact();
        return $filename;
    }

}
