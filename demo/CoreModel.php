<?php

namespace Ornament\Demo;

use Ornament\Core\Model;

class CoreModel
{
    use Model;

    protected int $id = 1;

    public string $name = 'Marijn';

    private bool $invisible = true;
}

