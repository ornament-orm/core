<?php

namespace Ornament\Core;

abstract final class Helpers
{
    public static function getModelPropertyDecorations(object $model) : array
    {
        static $cache = [];
        if (!$cache) {
            $reflection = new ReflectionClass($model);
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
                $cache['properties'][$name] = $anns;
            }
        }
        return $cache;
    }
}

