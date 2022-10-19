<?php

namespace Ornament\Core;

use ReflectionClass;
use ReflectionProperty;
use ReflectionException;
use SplObjectStorage;
use StdClass;
use Error;
use Traversable;

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
        $cache = $this->__getModelPropertyDecorations();
        foreach ($cache['properties'] as $field => $annotations) {
            $reflection = new ReflectionProperty($this, $field);
            $decorated = $this->ornamentalize($field, $this->$field ?? null);
            if (is_object($decorated)) {
                $this->$field = $decorated;
            }
        }
        if (isset($input)) {
            foreach ($input as $key => $value) {
                $this->$key = $this->ornamentalize($key, $value);
            }
        }
        $this->__initial = clone $this;
    }

    /**
     * Generate an instance from an iterable. This is similar to simply
     * constructing an instance, but is handy to use in callbacks etc.
     *
     * @param iterable $data
     * @return object Instance of whatever class uses this trait
     */
    public static function fromIterable(iterable $data) : object
    {
        self::initTransformer();
        return call_user_func(self::$arrayToModelTransformer, $data);
    }

    /**
     * Like `fromIterable`, only this accepts a _collection_ of iterable
     * inputs. Use with e.g. `PDO::fetchAll()`.
     *
     * @param iterable $data
     * @return array
     */
    public static function fromIterableCollection(iterable $collection) : iterable
    {
        array_walk($collection, function (iterable &$item) : void {
            $item = self::fromIterable($item);
        });
        return $collection;
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
     * exposed this way. Non-public properties are read-only.
     *
     * If a method was specified as `get` for this property, its return value
     * is used instead. If the property has a `var` annotation _and_ it is an
     * instance of `Ornament\Core\DecoratorInterface`, the corresponding
     * decorator class is initialised with the property's current value and
     * returned instead.
     *
     * @param string $prop Name of the property.
     * @return mixed The property's value.
     * @throws Error if the property is unknown.
     */
    public function __get(string $prop)
    {
        $cache = $this->__getModelPropertyDecorations();
        if (isset($cache['methods'][$prop])) {
            return $this->{$cache['methods'][$prop]}();
        }
        try {
            $reflection = new ReflectionProperty($this, $prop);
        } catch (ReflectionException $e) {
            throw new Error("Tried to get non-existing property $prop on ".get_class($this));
        }
        if (($reflection->isPublic() || $reflection->isProtected()) && !$reflection->isStatic()) {
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
        $cache = $this->__getModelPropertyDecorations();
        if (isset($cache['methods'][$prop])) {
            return true;
        }
        try {
            $reflection = new ReflectionProperty($this, $prop);
        } catch (ReflectionException $e) {
            return false;
        }
        if ($reflection->isPublic() || $reflection->isProtected()) {
            return isset($this->$prop);
        }
        return false;
    }

    /**
     * You'll want to specify a custom implementation for this. For models in an
     * array (on another model, of course) it is called with the current index.
     * Obviously, overriding is only needed if the index is relevant.
     *
     * @param integer $index The current index in the array.
     * @return void
     */
    public function __index(int $index) : void
    {
    }

    public function getPersistableData() : array
    {
        $data = [];
        foreach ($this->__getModelPropertyDecorations()['properties'] as $name => $anns) {
            if (!$anns['readOnly']) {
                $data[$name] = $this->$name ?? null;
            }
        }
        return $data;
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
        $cache = $this->__getModelPropertyDecorations();
        if (!isset($cache['properties'][$field])) {
            throw new PropertyNotDefinedException(get_class($this), $field);
        }
        if (self::checkBaseType($cache['properties'][$field]['var'] ?? null)) {
            // As of PHP 7.4, type coercion is implicit when properties have
            // been correctly type hinted.
            if (!strlen($value ?? '') && $cache['properties'][$field]['isNullable'] ?? false) {
                return null;
            }
            if ((float)phpversion() < 7.4) {
                settype($value, $cache['properties'][$field]['var']);
            }
            return $value;
        } elseif (isset($cache['properties'][$field]['var'])) {
            if (!class_exists($cache['properties'][$field]['var'])) {
                throw new DecoratorClassNotFoundException($cache['properties'][$field]['var']);
            }
            if (!array_key_exists(Ornament\Core\DecoratorInterface::class, class_implements($cache['properties'][$field]['var']))) {
                throw new DecoratorClassMustImplementDecoratorInterfaceException($cache['properties'][$field]['var']);
            }
            return new $cache['properties'][$field]['var']($value);
        } else {
            return $value;
        }
    }

    protected function __getModelPropertyDecorations() : array
    {
        static $cache = [];
        if (!$cache) {
            $reflection = new ReflectionClass($this);
            $cache['class'] = $reflection->getAttributes();
            $cache['methods'] = [];
            foreach ($reflection->getMethods() as $method) {
                $attributes = $method->getAttributes(Getter::class);
                if ($attributes) {
                    foreach ($attributes as $attribute) {
                        $cache['methods'][$attribute->newInstance()->property] = $method->getName();
                    }
                }
            }
            $properties = $reflection->getProperties((ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED) & ~ReflectionProperty::IS_STATIC);
            $cache['properties'] = [];
            foreach ($properties as $property) {
                $name = $property->getName();
                $anns = [];
                if ((float)phpversion() >= 7.4) {
                    if ($type = $property->getType()) {
                        $anns['var'] = $type->getName();
                        $anns['isNullable'] = $type->allowsNull();
                    }
                }
                $anns['readOnly'] = $property->isProtected();
                $cache['properties'][$name] = $anns;
            }
        }
        return $cache;
    }
}

