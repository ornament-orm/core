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
     * @var callable
     */
    private static $arrayToModelTransformer;

    /**
     * Constructor.
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
                    $this->$field = Helpers::ornamentalize($this, $field, $input[$field]);
                }
            }
        }
        Repository::setInitial($this);
    }

    /**
     * Initialize the iterable-to-model transformer for this class. The default
     * is to pass a hash of key/value pairs to the constructor.
     *
     * @param callable|null $transformer
     * @return void
     */
    public static function initTransformer(callable $transformer = null) : void
    {
        if (!isset(self::$arrayToModelTransformer)) {
            self::$arrayToModelTransformer = function (iterable $data) : object {
                $class = __CLASS__;
                return new $class($data);
            };
        }
        if (isset($transformer)) {
            self::$arrayToModelTransformer = $transformer;
        }
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

    /**
     * Overloaded getter. All public and protected properties on a model are
     * exposed this way, unless explicitly marker with the NoDecoration
     * attribute. Non-public properties are read-only.
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
        try {
            $reflection = new ReflectionProperty($this, $prop);
        } catch (ReflectionException $e) {
            throw new Error("Tried to get non-existing property $prop on ".get_class($this));
        }
        if ($reflection->isPublic() && !$reflection->isStatic()) {
            return $this->$prop;
        } else {
            throw new Error("Tried to get private or abstract property $prop on ".get_class($this));
        }
    }
    
    /**
     * Check if a property is defined. Note that this will return true for
     * protected properties.
     *
     * @param string $prop The property to check.
     * @return boolean True if set, otherwise false.
     */
    public function __isset(string $prop) : bool
    {
        $cache = Helpers::getModelPropertyDecorations($this);
        if (isset($cache['methods'][$prop])) {
            return true;
        }
        try {
            $reflection = new ReflectionProperty($this, $prop);
        } catch (ReflectionException $e) {
            return false;
        }
        if ($reflection->isPublic()) {
            return isset($this->$prop);
        }
        return false;
    }
}

