<?php

namespace DoctrineORMModule\Form\Element;

use RuntimeException;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityManager;
use Zend\Form\Element;

class DoctrineEntity extends Element
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * Seed attributes
     *
     * @var array
     */
    protected $attributes = array(
        'options' => array(),
        'type'    => 'select',
    );

    /**
     * @var array
     */
    protected $entities;

    /**
     * @var string
     */
    protected $property;

    /**
     * @var string
     */
    protected $method;

    /**
     * @var string
     */
    protected $emptyValue;

    /**
     * @var string
     */
    protected $targetClass;

    /**
     * @var mixed
     */
    protected $spec;

    /**
     * @param EntityManager $em
     * @return DoctrineEntity
     */
    public function setEntityManager(EntityManager $em)
    {
        $this->em = $em;
        return $this;
    }

    /**
     * @param string $targetClass
     * @return DoctrineEntity
     */
    public function setTargetClass($targetClass)
    {
        $this->targetClass = $targetClass;
        return $this;
    }

    /**
     * @return array|\Traversable
     */
    public function getAttributes()
    {
        $this->loadOptions();
        return parent::getAttributes();
    }

    /**
     * @return array
     */
    public function getEntities()
    {
        if (null === $this->entities) {
            $spec = $this->getAttribute('spec');

            if ($spec instanceof Query) {
                $entities = $spec->getQuery()->execute();
            } else if (is_callable($spec)) {
                /** @var $spec \Closure  */
                $callable = $spec($this->em->getRepository($this->targetClass));
                $entities = $callable->getQuery()->execute();
            } else {
                $entities = $this->em->getRepository($this->targetClass)->findAll();
            }
            $this->entities = $entities;
        }
        return $this->entities;
    }

    /**
     * @return void
     */
    protected function loadOptions()
    {
        if (!empty($this->attributes['options'])) {
            return;
        }

        $metadata   = $this->em->getClassMetadata($this->targetClass);
        $identifier = $metadata->getIdentifierFieldNames();
        $entities   = $this->getEntities();
        $options    = array();

        foreach($entities as $key => $entity) {
            if (($property = $this->getAttribute('property'))) {
                if (!$metadata->hasField($property)) {
                    throw new RuntimeException(sprintf(
                        'Property "%s" could not be found in entity "%s"',
                        $property,
                        $this->targetClass
                    ));
                }
                $reflClass = $metadata->getReflectionProperty($property);
                $label     = $reflClass->getValue($entity);
            } else if (($method = $this->getAttribute($method))) {
                if (!is_callable(array($entity, $method))) {
                    throw new RuntimeException(sprintf(
                        'Method "%s::%s" is not callable',
                        $this->targetClass,
                        $method
                    ));
                }
                $label = $entity->{$method}();
            } else {
                if (!is_callable(array($entity, '__toString'))) {
                    throw new RuntimeException(sprintf(
                        '%s must have a "__toString()" method defined if you have not set ' .
                        'a property or method to use.',
                        $this->targetClass
                    ));
                }
                $label = (string) $entity;
            }

            if (count($identifier) > 1) {
                $value = $key;
            } else {
                $value = current($metadata->getIdentifierValues($entity));
            }

            $options[] = array('label' => $label, 'value' => $value);
        }

        $this->attributes['options'] = $options;
    }
}