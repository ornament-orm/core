<?php

use Ornament\Demo\{ CoreModel, DecoratedModel, SubtractOne };
use Gentry\Gentry\Wrapper;

/**
 * Tests for core Ornament functionality.
 */
return function () : Generator {
    /** Core models should have only the basic functionality: expose properties via magic getters and setters */
    yield function () : void {
        $model = new Wrapper(
            CoreModel::fromIterable(['id' => 1]),
            null,
            ReflectionProperty::IS_PUBLIC & ~ReflectionProperty::IS_STATIC
        );
        assert(isset($model->id));
        assert($model->id === 1);
        assert(!isset($model->invisible1));
        assert(!isset($model->invisible2));
    };

    /** Models can successfully register and apply decorations. */
    yield function () : void {
        $model = new Wrapper(new DecoratedModel, null, ReflectionProperty::IS_PUBLIC & ~ReflectionProperty::IS_STATIC);
        $model->set('field', 2);
        assert((int)"{$model->field}" === 1);
        assert($model->virtual_property === "1");
    };

    /** If we try to access a private property, an Error is thrown. */
    yield function () : void {
        $model = new Wrapper(new CoreModel, null, ReflectionProperty::IS_PUBLIC & ~ReflectionProperty::IS_STATIC);
        $e = null;
        try {
            $foo = $model->invisible;
        } catch (Error $e) {
        }
        assert($e instanceof Error);
    };

    /** If we try to modify a readonly property, an Error is thrown. */
    yield function () : void {
        $model = new Wrapper(new CoreModel, null, ReflectionProperty::IS_PUBLIC & ~ReflectionProperty::IS_STATIC);
        $e = null;
        try {
            $model->id = 2;
        } catch (Error $e) {
        }
        assert($e instanceof Error);
    };
};

