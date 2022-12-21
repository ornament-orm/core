<?php

namespace Ornament\Core;

use StdClass;

trait State
{
    /**
     * Returns true if any of the model's properties was modified.
     *
     * @return bool
     */
    public function isDirty() : bool
    {
        foreach ($this->__initial as $prop => $val) {
            if ($prop == '__initial') {
                continue;
            }
            if ($this->isModified($prop)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns true if the model is still in pristine state.
     *
     * @return bool
     */
    public function isPristine() : bool
    {
        return !$this->isDirty();
    }

    /**
     * Returns true if a specific property on the model is dirty.
     *
     * @param string $property
     * @return bool
     */
    public function isModified(string $property) : bool
    {
        return $this->__initial?->$property ?? null !== $this->$property ?? null;
    }

    /**
     * Mark the current model as 'pristine', i.e. not dirty.
     *
     * @return void
     */
    public function markPristine() : void
    {
        $this->__initial = clone $this;
    }
}

