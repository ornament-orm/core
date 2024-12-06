<?php

namespace Ornament\Core;

trait Persistable
{
    /**
     * Get the persistable data for this model.
     *
     * This is basically shorthand for:
     * - get all public properties;
     * - that are not static;
     * - and are not readonly.
     *
     * @param array $extraProperties Array of non-public properties to store
     *  regardless.
     * @return array
     */
    public function getPersistableData(array $extraProperties = []) : array
    {
        $data = [];
        $reflection = new ReflectionObject($this);
        foreach ($reflection->getProperties(
            ReflectionProperty::IS_PUBLIC &
            ~ReflectionProperty::IS_STATIC &
            ~ReflectionProperty::IS_READONLY
        ) as $property) {
            $data[$property->name] = $this->{$property->name} ?? null;
        }
        foreach ($extraProperties as $property) {
            $data[$property] = $this->$property ?? null;
        }
        return $data;
    }
}
