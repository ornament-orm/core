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
        $model = new Wrapper(new StateModel, null, ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED & ~ReflectionProperty::IS_STATIC);
        assert($model->isPristine() === true);
        assert($model->isDirty() === false);
        $model->name = 'Linus';
        assert($model->isPristine() === false);
        assert($model->isDirty() === true);
    };
};

