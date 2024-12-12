<?php

namespace Ornament\Demo;

use Ornament\Core\{ Model, FromIterable };

class CoreModel
{
    use Model;
    use FromIterable;

    public readonly int $id;

    public string $name = 'Marijn';

    private bool $invisible = true;
}

