<?php

namespace Ornament\Demo;

use Ornament\Core\Decorator;

class SubtractOne extends Decorator
{
    public function getSource() : int
    {
        return $this->_source - 1;
    }
}

