<?php
/**
 * phpDocumentor
 *
 * PHP Version 5.3
 *
 * @copyright 2010-2013 Mike van Riel / Naenius (http://www.naenius.com)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      http://phpdoc.org
 */

namespace phpDocumentor\Descriptor;

use Zend\Serializer\Adapter\AdapterInterface;

abstract class BuilderAbstract
{
    /** @var ProjectDescriptor $project */
    protected $project;

    /** @var AdapterInterface $serializer */
    protected $serializer;

    public function __construct(ProjectDescriptor $project = null)
    {
        $this->project = $project ?: new ProjectDescriptor('Untitled project');
    }

    public function getProjectDescriptor()
    {
        return $this->project;
    }

    abstract public function buildFile($data);
    abstract public function buildClass($data);
    abstract public function buildInterface($data);
    abstract public function buildTrait($data);
    abstract public function buildFunction($data);
    abstract public function buildConstant($data, $container = null);
    abstract public function buildMethod($data, $container);
    abstract public function buildProperty($data, $container);

    public function setSerializer(AdapterInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    public function export()
    {
        return $this->serializer->serialize($this->project);
    }

    public function import($data)
    {
        $this->project = $this->serializer->unserialize($data);
    }
}