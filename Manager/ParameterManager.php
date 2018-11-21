<?php

namespace Sherlockode\ConfigurationBundle\Manager;

use Doctrine\Common\Persistence\ObjectManager;
use Sherlockode\ConfigurationBundle\Model\ParameterInterface;

/**
 * Class ParameterManager
 */
class ParameterManager
{
    /**
     * @var ObjectManager
     */
    private $om;

    /**
     * Class name of the parameter entity
     *
     * @var string
     */
    private $class;

    /**
     * List of parameter objects
     *
     * @var ParameterInterface[]
     */
    private $parameters;

    /**
     * Keep track of the new objects for the save action
     *
     * @var ParameterInterface[]
     */
    private $newParameters;

    /**
     * Parameters with "real" types (not strings), ie. after transformation from the database
     *
     * Associative array with : path => value
     *
     * @var array
     */
    private $data;

    /**
     * @var bool
     */
    private $loaded;

    /**
     * @var array
     */
    private $config;

    /**
     * @var FieldTypeManager
     */
    private $fieldTypeManager;

    /**
     * ParameterManager constructor.
     *
     * @param ObjectManager    $om
     * @param string           $class
     * @param array            $config
     * @param FieldTypeManager $fieldTypeManager
     */
    public function __construct(ObjectManager $om, $class, $config, FieldTypeManager $fieldTypeManager)
    {
        $this->om = $om;
        $this->class = $class;
        $this->config = $config;
        $this->parameters = [];
        $this->data = [];
        $this->loaded = false;
        $this->fieldTypeManager = $fieldTypeManager;
    }

    /**
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @return array
     */
    public function getAll()
    {
        if (false === $this->loaded) {
            $this->loadParameters();
        }

        return $this->data;
    }

    /**
     * @param string $path
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get($path, $default = null)
    {
        if (false === $this->loaded) {
            $this->loadParameters();
        }

        if (isset($this->parameters[$path])) {
            return $this->parameters[$path];
        }

        return $default;
    }

    /**
     * @param string $path
     * @param mixed  $value
     *
     * @return $this
     */
    public function set($path, $value)
    {
        if (!isset($this->config[$path])) {
            return $this;
        }
        if (!isset($this->parameters[$path])) {
            /** @var ParameterInterface $parameter */
            $parameter = new $this->class();
            $parameter->setPath($path);
            $this->parameters[$path] = $parameter;
            $this->newParameters[] = $parameter;
        }
        $this->parameters[$path]->setValue($this->getStringValue($path, $value));

        return $this;
    }

    /**
     * Save all parameters into the database
     */
    public function save()
    {
        foreach ($this->newParameters as $parameter) {
            $this->om->persist($parameter);
        }
        $this->om->flush($this->parameters);
    }

    /**
     * Load the parameters from the database
     * and transform their string value into the expected type
     */
    private function loadParameters()
    {
        $parameters = $this->om->getRepository($this->class)->findAll();
        /** @var ParameterInterface $parameter */
        foreach ($parameters as $parameter) {
            if (!isset($this->config[$parameter->getPath()])) {
                continue;
            }
            $this->parameters[$parameter->getPath()] = $parameter;

            $this->data[$parameter->getPath()] = $this->getUserValue($parameter->getPath(), $parameter->getValue());
        }
        $this->loaded = true;
    }

    /**
     * @param string $path
     * @param mixed  $value
     *
     * @return string
     */
    private function getStringValue($path, $value)
    {
        $parameterConfig = $this->config[$path];
        $fieldType = $this->fieldTypeManager->getField($parameterConfig['type']);

        if ($fieldType->getModelTransformer()) {
            $value = $fieldType->getModelTransformer()->reverseTransform($value);
        }

        return $value;
    }

    /**
     * @param string $path
     * @param string $value
     *
     * @return mixed
     */
    private function getUserValue($path, $value)
    {
        $parameterConfig = $this->config[$path];
        $fieldType = $this->fieldTypeManager->getField($parameterConfig['type']);

        if ($fieldType->getModelTransformer()) {
            $value = $fieldType->getModelTransformer()->transform($value);
        }

        return $value;
    }

    /**
     * @return array
     */
    public function getConfiguration()
    {
        return $this->config;
    }
}
