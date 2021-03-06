<?php

namespace Analogue\ORM\System\Wrappers;

use Analogue\ORM\System\InternallyMappable;
use Analogue\ORM\System\Proxies\ProxyFactory;

/**
 * The Wrapper Class provides a single interface access several Entity types.
 */
abstract class Wrapper implements InternallyMappable
{
    /**
     * Original Entity Object.
     *
     * @var mixed
     */
    protected $entity;

    /**
     * Corresponding EntityMap.
     *
     * @var \Analogue\ORM\EntityMap
     */
    protected $entityMap;

    /**
     * @var \Analogue\ORM\System\Proxirs\ProxyFactory
     */
    protected $proxyFactory;

    /**
     * Wrapper constructor.
     *
     * @param $entity
     * @param $entityMap
     */
    public function __construct($entity, $entityMap)
    {
        $this->entity = $entity;
        $this->entityMap = $entityMap;
        $this->proxyFactory = new ProxyFactory();
    }

    /**
     * Return the wrapped entity class.
     *
     * @return mixed
     */
    public function getEntityClass()
    {
        return get_class($this->entity);
    }

    /**
     * Return the entity's primary key valuye.
     *
     * @return string
     */
    public function getEntityKey()
    {
        return $this->getEntityAttribute($this->entityMap->getKeyName());
    }

    /**
     * Return the Entity class/primary key couple,
     * which is used for internall operations.
     *
     * @return string
     */
    public function getEntityHash()
    {
        return $this->getEntityClass().'.'.$this->getEntityKey();
    }

    /**
     * Returns the wrapped entity.
     *
     * @return mixed
     */
    public function getObject()
    {
        return $this->entity;
    }

    /**
     * Returns the wrapped entity's map.
     *
     * @return mixed
     */
    public function getMap()
    {
        return $this->entityMap;
    }

    /**
     * Set the lazyloading proxies on the wrapped entity objet.
     *
     * @param array $relations list of relations to be lazy loaded
     *
     * @return void
     */
    public function setProxies(array $relations = null)
    {
        $attributes = $this->getEntityAttributes();
        $proxies = [];

        $relations = $this->getRelationsToProxy();

        // Before calling the relationship methods, we'll set the relationship
        // method to null, to avoid hydration error on class properties
        foreach ($relations as $relation) {
            $this->setEntityAttribute($relation, null);
        }

        foreach ($relations as $relation) {

            // First, we check that the relation has not been already
            // set, in which case, we'll just pass.
            if (array_key_exists($relation, $attributes) && !is_null($attributes[$relation])) {
                continue;
            }

            // If the key is handled locally and we know it not to be set,
            // we'll set the relationship to null value
            if (!$this->relationNeedsProxy($relation, $attributes)) {
                $proxies[$relation] = $this->entityMap->getEmptyValueForRelationship($relation);
            } else {
                $targetClass = $this->getClassToProxy($relation, $attributes);
                $proxies[$relation] = $this->proxyFactory->make($this->getObject(), $relation, $targetClass);
            }
        }

        foreach ($proxies as $key => $value) {
            $this->setEntityAttribute($key, $value);
        }
    }

    /**
     * Get Target class to proxy for a one to one.
     *
     * @param string $relation
     * @param array  $attributes
     *
     * @return string
     */
    protected function getClassToProxy($relation, array $attributes)
    {
        if ($this->entityMap->isPolymorphic($relation)) {
            $localTypeAttribute = $this->entityMap->getLocalKeys($relation)['type'];

            return $attributes[$localTypeAttribute];
        }

        return $this->entityMap->getTargettedClass($relation);
    }

    /**
     * Determine which relations we have to build proxy for, by parsing
     * attributes and finding methods that aren't set.
     *
     * @return array
     */
    protected function getRelationsToProxy()
    {
        $proxies = [];
        $attributes = $this->getEntityAttributes();

        foreach ($this->entityMap->getNonEmbeddedRelationships() as $relation) {
            //foreach ($this->entityMap->getRelationships() as $relation) {

            if (!array_key_exists($relation, $attributes) || is_null($attributes[$relation])) {
                $proxies[] = $relation;
            }
        }

        return $proxies;
    }

    /**
     * Determine if the relation needs a proxy or not.
     *
     * @param string $relation
     * @param array  $attributes
     *
     * @return bool
     */
    protected function relationNeedsProxy($relation, $attributes)
    {
        if (in_array($relation, $this->entityMap->getRelationshipsWithoutProxy())) {
            return false;
        }

        $localKey = $this->entityMap->getLocalKeys($relation);

        if (is_null($localKey)) {
            return true;
        }

        if (is_array($localKey)) {
            $localKey = $localKey['id'];
        }

        if (!isset($attributes[$localKey])) {
            return false;
        }

        if (is_null($attributes[$localKey])) {
            return false;
        }

        return true;
    }

    /**
     * @param string $key
     * @param string $value
     *
     * @return mixed
     */
    abstract public function setEntityAttribute($key, $value);

    /**
     * @param string $key
     *
     * @return mixed
     */
    abstract public function getEntityAttribute($key);

    /**
     * @param array $attributes
     *
     * @return mixed
     */
    abstract public function setEntityAttributes(array $attributes);

    /**
     * @return mixed
     */
    abstract public function getEntityAttributes();

    /**
     * @param string $key
     *
     * @return mixed
     */
    abstract public function hasAttribute($key);
}
