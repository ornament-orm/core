<?php

use Ornament\Core\{ Model, Getter, Decorator, Construct };
use Gentry\Gentry\Wrapper;

class CoreModel
{
    use Model;

    public readonly int $id;

    public string $name = 'Marijn';

    private bool $invisible = true;
}

class SubtractOne extends Decorator
{
    public function getSource() : int
    {
        return $this->_source - 1;
    }
}

class DecoratedModel
{
    use Model;

    public SubtractOne $field;

    /**
     * @return string
     */
    #[Getter("virtual_property")]
    protected function getVirtualPropertyDemo() :? string
    {
        return (string)($this->field ?? '');
    }
}

class DateTimeModel
{
    use Model;

    #[Construct(new DateTimeZone('Asia/Tokyo'))]
    public readonly DateTime $datecreated;
}

/**
 * Tests for core Ornament functionality.
 */
return function () : Generator {
    $flag = ReflectionProperty::IS_PUBLIC & ~ReflectionProperty::IS_STATIC;
    /** Core models should have only the basic functionality: expose properties via magic getters and setters */
    yield function () use ($flag) : void {
        $model = new Wrapper(new CoreModel(['id' => '1']), null, $flag);
        assert(isset($model->id));
        assert($model->id === 1);
        assert(!isset($model->invisible1));
        assert(!isset($model->invisible2));
    };

    /** Models can successfully register and apply decorations. */
    yield function () use ($flag) : void {
        $model = new Wrapper(new DecoratedModel, null, $flag);
        $model->set('field', 2);
        assert((int)"{$model->field}" === 1);
        assert($model->virtual_property === "1");
    };

    /** Additional constructor arguments are correctly passed. */
    yield function () use ($flag) : void {
        $model = new Wrapper(new DateTimeModel(['datecreated' => '1978-07-13']), null, $flag);
        assert($model->datecreated instanceof DateTime);
        assert($model->datecreated->getTimezone()->getName() === 'Asia/Tokyo');
    };

    /** If we try to access a private property, an Error is thrown. */
    yield function () use ($flag) : void {
        $model = new Wrapper(new CoreModel, null, $flag);
        $e = null;
        try {
            $foo = $model->invisible;
        } catch (Error $e) {
        }
        assert($e instanceof Error);
    };

    /** If we try to modify a readonly property, an Error is thrown. */
    yield function () use ($flag) : void {
        $model = new Wrapper(new CoreModel, null, $flag);
        $e = null;
        try {
            $model->id = 2;
        } catch (Error $e) {
        }
        assert($e instanceof Error);
    };
};

