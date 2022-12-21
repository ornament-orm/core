<?php

use Ornament\Demo\StateModel;
use Gentry\Gentry\Wrapper;

/**
 * Tests for stateful models.
 */
return function () : Generator {
    /**
     * Stateful models should correctly report their state as pristine or dirty.
     */
    yield function () {
        $model = new Wrapper(new StateModel);
        assert($model->isPristine());
        $model->name = 'Linus';
        assert($model->isDirty());
    };
};

