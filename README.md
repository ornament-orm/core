# Ornament
PHP8 object decorator toolkit, core package

When translating data from any storage source to your PHP models, you'll often
want to _augment_ it. For example, a date from a database is simply a string,
but maybe you prefer it as a `DateTime` object, or a `Carbon` instance.

Ornament's aim is to simplify tons of boilerplate code to facilitate this.

## Installation
```sh
$ composer require ornament/core
```

You'll likely also want auxiliary packages from the `ornament/*` family. These
contain predefined types you can use, e.g. the `Bitflag\Property` allowing you
to stuff multiple `true/false` values in a single integer.

### Manual installation ###
Yadiyadi, `git clone` or whatever and add the path for the `Ornament\Core`
namespace to your `psr-4` autoload config. But really, don't do that unless
you're masochistic.

## Basic usage
Ornament models are nothing more than vanilla PHP classes; there is no need to
extend any master or god class of sorts (you might want to do that in your own
framework of choice).

At the very least, you'll want to `use` the `Ornament\Core\Model` trait:

```php
<?php

use Ornament\Core\Model;

class FooModel
{
    use Model;

    public readonly DateTime $datecreated;
}
```

Here, we indicated (via type-hinting) that the `datecreated` property should
actually contain a `DateTime` object. It is also readonly, since the creation
date is not very likely to ever change.

Ornament defines a default constructor which assumes one argument: a hash of
key/value pairs for your model's properties. If you require a constructor for
your model (e.g. because you need to inject dependencies) you may alias the
default constructor and call it manually. An example:

```php
<?php

use Ornament\Core\Model;

class FooModel
{
    use Model {
        Model::__construct as ornamentConstruct;
    }

    public function __construct(private MyDependency $foo, ?iterable $input = null)
    {
        $this->ornamentConstruct($input);
    }
}
```

Decorated properties assume the decorating class accepts the _value_ as its
first constructor argument. Additional arguments may be specified by annotating
the property with one or more `Ornament\Core\Construct` attributes. The
attribute's constructor argument is the actual additional value that should be
passed. For instance, to specify a specific time zone for the `DateTime`
property, one would write:

```php
<?php

use Ornament\Core\{ Model, Construct };

class FooModel
{
    use Model;

    #[Construct(new DateTimeZone('Europe/Amsterdam'))]
    public readonly DateTime $datecreated;
}
```

Lastly, Ornament defines a `Decorator` class that custom decorators may extend.
The difference from a "vanilla" decorator is that extensions of the `Decorator`
come with some utility methods, and receive by default a _second_ constructor
argument: a `ReflectionProperty` of the original target field (so your custom
decorator can also refer to any attributes your project may have defined for
that specific property - or whatever other black magic you need to do).

Extensions of the `Decorator` class can also add `Construct` attributes like
above. These will be passed as the third etc. arguments to the constructor.

## Types
Properties specifying one of the basic, built-in types (e.g. `int` or `string`)
will have their values coerced to the correct type.

## Enums
Properties may also be type-hinted as a backed enum:

```php
<?php

enum MyEnum
{
    case foo = 1;
    case bar = 2;
}

class MyModel
{
    public MyEnum $baz;
}

// Fails: 3 is not in the enum!
$model = new MyModel(['baz' => 3]);
```

If the enum property is _nullable_, an invalid value will not throw an error,
but cause `null` to be set instead.

## Getters for virtual properties
Ornament models support the concept of _virtual properties_ (which are, by
definition, read-only).

An example of a virtual property would be a model with a `firstname` and
`lastname` property, and a getter for `fullname`. To mark a method as a getter,
attribute it with `#[\Ornament\Core\Getter("propertyName")]`:

```php
<?php

class MyModel
{
    // ...

    #[\Ornament\Core\Getter("fullname")]
    protected function exampleGetter() : string
    {
        return $this->firstname.' '.$this->lastname;
    }
}
```

The name and visibility of a getter (usually) don't matter; a best practice is
to mark them as `protected` so they cannot be called from outside, and to give
them a reasonably descriptive name for your own sanity (in the above example,
`getFullname` would have been better).


## PHP, PDO and `fetchObject`
PDO's `fetchObject` and related methods try to be clever by injecting
properties based on fetched database columns _before_ the constructor is called.
Hence, do not use `PDO::FETCH_OBJECT`; instead, use `PDO::FETCH_ASSOC`.

## Stateful models
Models may use the `Ornament\Core\State` trait to expose some convenience
methods:

- `isDirty()`: was the model changed since instantiation?
- `isModified(string $property)`: specifically check if a property was modified.
- `isPristine()`: the opposite of `isDirty`.
- `markPristine()`: manually mark the model as pristine, e.g. after storing it.
  Basically this resets the initial state to the current state.

All these methods are public. You can use them in your storage logic to
determine how to proceed (e.g. skip an expensive `UPDATE` operation if the model
`isPristine()` anyway).

## Preventing properties from being decorated
If your models are more than a "simple" data store, there might be properties on
it you explicitly _don't_ want decorated. Note that any static property is
already left alone.

To explicitly tell Ornament to skip decoration for a public or protected
property, add the attribute `Ornament\Core\NoDecoration` to it.

