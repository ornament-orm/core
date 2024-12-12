<?php

namespace Ornament\Core;

use Attribute;

/**
 * If, for whatever reason, a property should explicitly _not_ be dedorated,
 * you may use the `Ornament\Core\NoDecoration` attribute.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class NoDecoration
{
}

