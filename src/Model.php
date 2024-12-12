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
     * @var object
     *
     * Private storage of the model's initial state.
     */
    private $__initial;

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
                    $this->$field = $this->ornamentalize($field, $input[$field]);
                }
            }
        }
        $this->__initial = clone $this;
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
        $this->$field = $this->ornamentalize($field, $value);
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

    /**
     * Internal helper method to check if the given property is annotated as one
     * of PHP's internal base types (int, float etc).
     *
     * @param string|null $type
     * @return bool
     */
    protected static function checkBaseType(string $type = null) : bool
    {
        static $baseTypes = ['bool', 'int', 'float', 'string', 'array', 'object', 'null'];
        return in_array($type, $baseTypes);
    }

    /**
     * Ornamentalize the requested field. Usually this is called for you, but on
     * occasions you may need to call it manually.
     *
     * @param string $field
     * @param mixed $value
     * @return mixed
     */
    protected function ornamentalize(string $field, mixed $value) : mixed
    {
        $cache = Helpers::getModelPropertyDecorations($this);
        if (!isset($cache['properties'][$field])) {
            throw new PropertyNotDefinedException(get_class($this), $field);
        }
        if (self::checkBaseType($cache['properties'][$field]['var'] ?? null)) {
            if (is_scalar($value) && !strlen($value ?? '') && $cache['properties'][$field]['isNullable'] ?? false) {
                return null;
            }
            return $value;
        } elseif (isset($cache['properties'][$field]['var'])) {
            if (!class_exists($cache['properties'][$field]['var'])) {
                throw new DecoratorClassNotFoundException($cache['properties'][$field]['var']);
            }
            if ($cache['properties'][$field]['isEnum']) {
                $enum = $cache['properties'][$field]['var'];
                if ($cache['properties'][$field]['isNullable']) {
                    return $enum::tryFrom($value);
                } else {
                    return $enum::from($value);
                }
            } else {
                $arguments = [$value];
                $reflection = new ReflectionProperty($this, $field);
                if (is_a($cache['properties'][$field]['var'], Decorator::class, true)) {
                    $arguments[] = $reflection;
                }
                $attributes = $reflection->getAttributes(Construct::class);
                foreach ($attributes as $attribute) {
                    $attribute = $attribute->newInstance();
                    $arguments[] = $attribute->getValue();
                }
                return new $cache['properties'][$field]['var'](...$arguments);
            }
        } else {
            return $value;
        }
    }
}

