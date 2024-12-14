<?php

namespace Ornament\Core;

use ReflectionClass, ReflectionObject, ReflectionProperty, ReflectionException;
use Error;

/**
 * `use` this trait to turn any vanilla class into an Ornament model.
 */
trait Model
{
    /**
     * Constructor. Takes an iterable `$input` (e.g. a row from a database) as
     * argument. Key/value pairs are assumed.
     *
     * Each known public property, if available in `$input`, is populated with
     * the corresponding value, cast to the correct (decorated) type. Note that
     * `readonly` properties may only be set from the actual class defining them
     * (not child or parent classes). Ornament ignores these; it is up the
     * implementor to make sure all parent constructors are called.
     *
     * @param iterable|null $input
     * @return void
     */
    public function __construct(iterable $input = null)
    {
        if (isset($input)) {
            $cache = Helpers::getModelPropertyDecorations($this);
            foreach ($cache['properties'] as $field => $annotations) {
                if (array_key_exists($field, $input)) {
                    try {
                        $this->$field = Helpers::ornamentalize($this, $field, $input[$field]);
                    } catch (Error $e) {
                        // Probably a readonly property defined in a parent
                        // class. Implementations with inherited classes
                        // _should_ take care to invoke all parent constructors,
                        // so as to avoid uninitialized readonly properties.
                    }
                }
            }
        }
        Repository::setInitial($this);
    }

    /**
     * Overloaded getter. This allows virtual properties to be retrieved.
     *
     * @param string $prop Name of the property.
     * @return mixed The property's value.
     * @throws Error if the property is unknown.
     */
    public function __get(string $prop)
    {
        $cache = Helpers::getModelPropertyDecorations($this);
        if (isset($cache['methods'][$prop])) {
            return $this->{$cache['methods'][$prop]}();
        }
        throw new Error("Unknown virtual property `$prop`");
    }

    /**
     * As of PHP7.4, `__set` works differently and complains about invalid types
     * _before_ the magic method is executed.
     *
     * @param string $field
     * @param mixed $value
     * @return void
     * @throws Error if the property in question is non-public or static.
     */
    public function set(string $field, $value) : void
    {
        $property = new ReflectionProperty($this, $field);
        if (!$property->isPublic()) {
            throw new Error("Only public properties can be `set` ($field in ".get_class($this).")");
        }
        if ($property->isStatic()) {
            throw new Error("Only non-static properties can be `set` ($field in ".get_class($this).")");
        }
        $this->$field = Helpers::ornamentalize($this, $field, $value);
    }
}

