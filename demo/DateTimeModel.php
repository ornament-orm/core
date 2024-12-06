<?php

namespace Ornament\Demo;

use Ornament\Core\{ Model, Construct };
use DateTime, DateTimeZone;

class DateTimeModel
{
    use Model;

    #[Construct(new DateTimeZone('Asia/Tokyo'))]
    public readonly DateTime $datecreated;
}

