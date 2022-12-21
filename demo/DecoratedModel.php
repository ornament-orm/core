<?php

namespace Ornament\Demo;

use Ornament\Core\{ Model, Getter };

class DecoratedModel
{
    use Model;

    public SubtractOne $field;

    /**
     * @return string
     */
    #[Getter("virtual_property")]
    protected function getVirtualPropertyDemo() :? string
    {
        return (string)($this->field ?? '');
    }
}

