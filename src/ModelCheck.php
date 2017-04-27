<?php

namespace Ornament\Core;

trait ModelCheck
{
    private function check()
    {
        if (!isset($this->__initial, $this->__state)) {
            throw new DomainException(sprintf(
                "%s is not an Ornament model.",
                get_class($this)
            ));
        }
    }
}
