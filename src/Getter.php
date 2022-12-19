<?php

namespace Ornament\Core;

#[\Attribute]
class Getter
{
    public string $property;

    public function __construct(string $property)
    {
        $this->property = $property;
    }
}

