<?php

namespace Classes;

use ArrayAccess;
use BadMethodCallException;

class EntityWrapper implements ArrayAccess
{
    private $entity;
    private array $extraData;

    public function __construct($entity, array $extraData = [])
    {
        $this->entity = $entity;
        $this->extraData = $extraData;
    }

    public function __call($name, $arguments)
    {
        if (method_exists($this->entity, $name))
        {
            return call_user_func_array([$this->entity, $name], $arguments);
        }

        throw new BadMethodCallException("Method {$name} does not exist.");
    }
    public function offsetExists($offset): bool
    {
        return isset($this->extraData[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->extraData[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        $this->extraData[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->extraData[$offset]);
    }
}