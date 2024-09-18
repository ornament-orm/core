<?php

namespace Ornament\Demo;

use Ornament\Core\Model;

class CoreModel
{
    use Model;

    public readonly int $id;

    public string $name = 'Marijn';

    private bool $invisible = true;
}

