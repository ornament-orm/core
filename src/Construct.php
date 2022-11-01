<?php

namespace Ornament\Core;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY|Attribute::IS_REPEATABLE)]
class Construct
{
    public function __construct(private mixed $argument) {}

    public function getValue() : mixed
    {
        return $this->argument;
    }
}

