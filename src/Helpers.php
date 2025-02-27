<?php

namespace Ornament\Core;

use ReflectionClass, ReflectionProperty;

abstract class Helpers
{
    public static function getModelPropertyDecorations(object $model) : array
    {
        static $cache = [];
        $class = get_class($model);
        if (!isset($cache[$class])) {
            $reflection = new ReflectionClass($model);
            $cache[$class]['class'] = $reflection->getAttributes();
            $cache[$class]['methods'] = [];
            foreach ($reflection->getMethods() as $method) {
                $attributes = $method->getAttributes(Getter::class);
                if ($attributes) {
                    foreach ($attributes as $attribute) {
                        $cache[$class]['methods'][$attribute->newInstance()->property] = $method->getName();
                    }
                }
            }
            $properties = $reflection->getProperties((ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED) & ~ReflectionProperty::IS_STATIC);
            $cache[$class]['properties'] = [];
            foreach ($properties as $property) {
                $attributes = $property->getAttributes(NoDecoration::class);
                if ($attributes) {
                    continue;
                }
                $name = $property->getName();
                $anns = [];
                if ($type = $property->getType()) {
                    $anns['var'] = $type->getName();
                    $anns['isNullable'] = $type->allowsNull();
                    $anns['isEnum'] = enum_exists($anns['var']);
                }
                $anns['readOnly'] = $property->isReadOnly();
                $cache[$class]['properties'][$name] = $anns;
            }
        }
        return $cache[$class];
    }

    /**
     * Internal helper method to check if the given property is annotated as one
     * of PHP's internal base types (int, float etc).
     *
     * @param string|null $type
     * @return bool
     */
    public static function checkBaseType(?string $type = null) : bool
    {
        static $baseTypes = ['bool', 'int', 'float', 'string', 'array', 'object', 'null'];
        return in_array($type, $baseTypes);
    }

    /**
     * Ornamentalize the requested field. Usually this is called for you, but on
     * occasions you may need to call it manually.
     *
     * @param object $model
     * @param string $field
     * @param mixed $value
     * @return mixed
     */
    public static function ornamentalize(object $model, string $field, mixed $value) : mixed
    {
        $cache = self::getModelPropertyDecorations($model);
        if (!isset($cache['properties'][$field])) {
            var_dump($cache['properties']);
            throw new PropertyNotDefinedException(get_class($model), $field);
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
                $reflection = new ReflectionProperty($model, $field);
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

