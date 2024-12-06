<?php

namespace Ornament\Core;

trait FromIterable
{
    /**
     * Generate an instance from an iterable. This is similar to simply
     * constructing an instance, but is handy to use in callbacks etc.
     *
     * @param iterable $data
     * @return object Instance of whatever class uses this trait
     */
    public static function fromIterable(iterable $data) : object
    {
        $class = __CLASS__;
        return new $class($data);
    }

    /**
     * Like `fromIterable`, only this accepts a _collection_ of iterable
     * inputs. Use with e.g. `PDO::fetchAll()`.
     *
     * @param iterable $collection
     * @return iterable
     */
    public static function fromIterableCollection(iterable $collection) : iterable
    {
        array_walk($collection, function (iterable &$item) : void {
            $item = self::fromIterable($item);
        });
        return $collection;
    }
}

