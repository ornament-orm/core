<?php

namespace Ornament\Core;

abstract class Repository
{
    public static function setInitial(object $model)
    {
        $storage =& self::getStorage();
        $storage[spl_object_hash($model)] = clone $model;
    }

    public static function getInitial(object $model) : object
    {
        $storage =& self::getStorage();
        return $storage[spl_object_hash($model)];
    }

    public static function &getStorage() : array
    {
        static $storage = [];
        return $storage;
    }
}

