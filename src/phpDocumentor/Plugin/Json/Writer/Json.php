<?php

namespace phpDocumentor\Plugin\Json\Writer;

use phpDocumentor\Descriptor\DescriptorAbstract;
use phpDocumentor\Descriptor\ClassDescriptor;
use phpDocumentor\Descriptor\Type\UnknownTypeDescriptor;
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

    // private static $keys = ['classes', 'interfaces', 'traits'];

    // private static $defaults = [
    //     'false' => false,
    //     'true' => true,
    //     'null' => null,
    //     'array()' => []
    // ];

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
            self::addDoc($subElement, $node);
            if ($subElement instanceof ClassDescriptor) {
                self::addFlags($subElement, $node);
                if (($parent = $subElement->getParent()) && is_object($parent)) {
                    $node['extends'] = $parent->getFullyQualifiedStructuralElementName();
                }
                foreach ($subElement->getInterfaces()->getAll() as $implements) {
                    if (!is_object($implements)) {
                        continue;
                    }
                    self::addEl($node, 'implements', $implements->getFullyQualifiedStructuralElementName());
                }
                foreach ($subElement->getMethods() as $method) {
                    if (!$method || ($method->getVisibility() !== 'public')) {
                        continue;
                    }
                    $m = ['name' => $method->getName()];
                    self::addDoc($method, $m);
                    self::addFlags($method, $m);
                    foreach ($method->getTags() as $tagGroup) {
                        if (!$tagGroup) {
                            continue;
                        }
                        foreach ($tagGroup as $tag) {
                            if ($tag->getName() !== 'return') {
                                continue;
                            }
                            foreach ($tag->getTypes() as $type) {
                                self::addEl($m, 'returns', (string)$type);
                            }
                        }
                    }
                    if (isset($m['returns']) && is_array($m['returns']) && count($m['returns']) === 1) {
                        $m['returns'] = $m['returns'][0];
                    }
                    foreach ($method->getArguments() as $argument) {
                        $a = [
                            'name' => $argument->getName()
                        ];
                        if (($def = $argument->getDefault()) !== null) {
                            $a['default'] = $def;
                        }
                        if ($argument->isByReference()) {
                            $a['byreference'] = true;
                        }
                        $types = $argument->getTypes();
                        $typeStrings = [];
                        foreach ($types as $type) {
                            $str = (string)($type instanceof DescriptorAbstract
                                ? $type->getFullyQualifiedStructuralElementName()
                                :
                                ($type instanceof UnknownTypeDescriptor ? $type->getName() : $type));
                            if ($str === '') {
                                continue;
                            }
                            $typeStrings[] = $str;
                        }
                        if (count($typeStrings)) {
                            if (count($typeStrings) === 1) {
                                $typeStrings = $typeStrings[0];
                            }
                            $a['type'] = $typeStrings;
                        }
                        self::addEl($m, 'args', $a);
                    }
                    self::addEl($node, 'methods', $m);
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

    private static function addFlags($subElement, &$node)
    {
        foreach (array('abstract', 'final', 'static') as $k) {
            $m = 'is' . $k;
            if ($subElement->{$m}()) {
                $node[$k] = true;
            }
        }
    }

    private static function addDoc($element, &$node)
    {
        foreach (array('summary', 'description') as $k) {
            $m = 'get' . $k;
            if ($r = $element->{$m}()) {
                $node[$k] = $r;
            }
        }
    }

    private static function addEl(&$holder, $key, $el)
    {
        if (!isset($holder[$key])) {
            $holder[$key] = [];
        }
        $holder[$key][] = $el;
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
