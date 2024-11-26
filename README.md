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

Here, we indicated (via type-hinting) that the `datecreated` propery should
actually contain a `DateTime` object. It is also readonly, since the creation
date is not very likely to ever change.

Ornament defines a default constructor which assumes one argument: a hash of
key/value pairs for your model's properties.

Decorated properties assume the decorating class accepts the _value_ as its
first constructor argument. Additional arguments may be specified by annotating
the property with one or more `Ornament\Core\Construct` attributes. The
attribute's constructor argument is the actual additional value that should be
passed. For instance, to specify a specific time zone for the `DateTime`
property, one would write:

```php
<?php

use Ornament\Core\Model, Construct;

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

## `FromIterable` trait
Models may `use` the `Ornament\Core\FromIterable` trait for simple
instantiation, also in callbacks:

```php
<?php

use Ornament\Core;

class MyModel
{
    use Core\Model;
    use Core\FromIterable;

    // properties...
}

$model = MyModel::fromIterable($singleRowFromDatabase);
$models = MyModel::fromIterableCollection($multipleRowsFromDatabase);
```

In the above example, the single model is equivalent to
`new MyModel($singleRowFromDatabase)`, and as such doesn't add much.
PHP 7.4 or higher doesn't like that, since a decorated property will be of the
wrong type!

For this reason, it's now considered best practice to use `PDO::FETCH_ASSOC` and
feeding the result through either `Model::fromIterable` (for `fetch`) or
`Model::fromIterableCollection` (for `fetchAll`).

E.g.:

```php
<?php

// ...
return MyModel::fromIterable($stmt->fetch(PDO::FETCH_ASSOC));
```

Versions of Ornament <0.14 did not have this limitation as they specifically
worked with `fetchObject`; this is no longer possible on PHP 7.4+ so we strongly
recommend you upgrade to 0.15 or higher.

## Custom object instantiation
Ornament supplies a constructor that expects key/value pairs of data to inject
into the model. Sometimes this is not what you want; maybe you're extending a
base class that expects each property to be specified as an argument to the
constructor (or whatever, e.g. Laravel's `fill` method).

Default behaviour can be overridden using the `initTransformer` static method,
passing a callback which takes the iterable `$data` as its only argument and
must return the constructed object:

```php
<?php

MyModel::initTransformer(function (iterable $data) : MyModel {
    return new MyModel($data['id'], $data['password']);
});
```

These transformers are on a _per class_ basis. If you need it for _all_ your
models, you should make them extend a base class and call `initTransformer` on
that.

## Loading and persisting models
This is your job. Wait, what? Yes, Ornament is storage engine agnostic. You may
use an RDBMS, interface with a JSON API or store your stuff in Excel files for
all we care. We believe that you shouldn't tie your models to your storage
engine.

Our personal preference is to use "repositories" that handle this. Of course,
you're free to make a base class model for yourself which implements `save()`
or `delete()` methods or whatever.

## Stateful models
Having said that, you're not completely on your own. Models may use the
`Ornament\Core\State` trait to expose some convenience methods:

- `isDirty()`: was the model changed since the last instantiation?
- `isModified(string $property)`: specifically check if a property was modified.
- `isPristine()`: the opposite of `isDirty`.
- `markPristine()`: manually mark the model as pristine, e.g. after storing it.
  Basically this resets the initial state to the current state.

All these methods are public. You can use them in your storage logic to
determine how to proceed (e.g. skip an expensive `UPDATE` operation if the model
`isPristine()` anyway).

## Preventing properties from being decorated
If your models are more than a "simple" data store, there might be properties on
it you explicitly _don't_ want decorated. Note that any private or static
property is already left alone.

To explicitly tell Ornament to skip decoration for a public or protected
property, add the attribute `Ornament\Core\NoDecoration` to it.

