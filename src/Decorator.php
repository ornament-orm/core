<?php

namespace Ornament\Core;

use stdClass;

abstract class Decorator implements DecoratorInterface
{
    protected mixed $_source;

    /**
     * Constructor. Pass in the original, mixed value.
     *
     * @param mixed $source;
     * @return void
     */
    public function __construct(mixed $source)
    {
        $this->_source = $source;
    }

    /**
     * Get the original source, i.e. $model->$property.
     *
     * @return mixed
     */
    public function getSource()
    {
        return $this->_source;
    }

    /**
     * __toString the source.
     *
     * @return string
     */
    public function __toString() : string
    {
        return (string)$this->getSource();
    }
}

