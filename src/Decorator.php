<?php

namespace Ornament\Core;

use stdClass;
use ReflectionProperty;

abstract class Decorator
{
    /**
     * Constructor. Receives the original, mixed value, and a ReflectionProperty
     * of the property being decorated (for optional attributes).
     *
     * @param mixed $_source;
     * @param ReflectionProperty $_target;
     * @return void
     */
    public function __construct(protected mixed $_source, protected ReflectionProperty $_target) {}

    /**
     * Get the original _source, i.e. $model->$property.
     *
     * @return mixed
     */
    public function getSource()
    {
        return $this->_source;
    }

    /**
     * __toString the _source.
     *
     * @return string
     */
    public function __toString() : string
    {
        return (string)$this->getSource();
    }
}

