<?php

namespace Ornament\Core;

use ReflectionObject, ReflectionProperty;

trait State
{
    /**
     * Returns true if any of the model's properties was modified.
     *
     * @return bool
     */
    public function isDirty() : bool
    {
        $reflection = new ReflectionObject($this);
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC & ~ReflectionProperty::IS_STATIC) as $property) {
            if ($this->isModified($property->name)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if the model is still in pristine state.
     *
     * @return bool
     */
    public function isPristine() : bool
    {
        return !$this->isDirty();
    }

    /**
     * Returns true if a specific property on the model is dirty.
     *
     * @param string $property
     * @return bool
     */
    public function isModified(string $property) : bool
    {
        $initial = Repository::getInitial($this);
        if (isset($this->$property, $initial->$property)) {
            return $initial->$property !== $this->$property;
        }

        return false;
    }

    /**
     * Mark the current model as 'pristine', i.e. not dirty.
     *
     * @return void
     */
    public function markPristine() : void
    {
        Repository::setInitial($this);
    }
}

