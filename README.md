# Ornament
PHP7 ORM toolkit, core package

ORM is a fickle beast. Many libraries (e.g. Propel, Doctrine, Eloquent etc)
assume your database should correspond to your models. This is simply not the
case; models contain business logic and may, may not or may in part refer to
database tables, NoSQL databases, flat files, an external API or whatever (the
"R" in ORM should really stand for "resource", not "relational"). The point is:
the models shouldn't care, and there should be no "conventional" mapping through
their names. (A common example would be a model of pages in multiple languages,
where the data might be stored in a `page` table and a `page_i18n` table for the
language-specific data.)

Also, the use of extensive and/or complicated config files sucks. (XML? This
is 2020, people!)

Ornament's design goals are:

- make it super-simple to use vanilla PHP classes as models;
- promote the use of models as "dumb" data containers;
- encourage offloading of storage logic to helper classes ("repositories");
- make models extensible via an easy plugin mechanism.

## Installation
```sh
$ composer require ornament/core
```

You'll likely also want auxiliary packages from the `ornament/*` family.

## Basic usage
Ornament models (or "entities" if you're used to Doctrine-speak) are really
nothing more than vanilla PHP classes; there is no need to extend any base
object of sorts (since you might want to do that in your own framework).

Ornament is a _toolkit_, so it supplies a number of `Trait`s one can `use` and
auxiliary decorator classes to extend your models' behaviour beyond the
ordinary.

The most basic implementation would look as follows:

```php
<?php

use Ornament\Core\Model;

class MyModel
{
    // The generic Model trait that bootstraps this class as an Ornament model;
    // it contains core functionality.
    use Model;

    /**
     * All protected properties on a model are considered read-only.
     */
    protected int $id;

    /**
     * Public properties are read/write. To auto-decorate during setting, use
     * the `Model::set()` method.
     */
    public string $name;

    /**
     * Private properties are just that: private. They're left alone.
     */
    private string $password;
}

// Assuming $source is a handle to a data source (in this case, a PDO
// statement):
$model = MyModel::fromIterable($source->fetch(PDO::FETCH_ASSOC));
echo $model->id; // 1
echo $model->name; // Marijn
echo $model->password; // Error: private property.
$model->name = 'Linus'; // Ok; public property.
$model->id = 2; // Error: read-only property.
```

PHP will take care of type coercion for builtins, while Ornament will handle
more complex casing and decorating so you can also use classes as decorators
(see below for more information).

The above example didn't do much yet except exposing the protected `id` property
as read-only. Note however that Ornament models also prevent mutating undefined
properties; trying to set anything not explicitly set in the class definition
will throw an `Error` mimicking PHP's internal error.

## Annotating and decorating models
Ornament doesn't get _really_ useful until you start _decorating_ your models.
This is done (mostly) by specifying a type hint on a property with a class name
that implements `Ornament\Core\DecoratorInterface`.

## Getters for virtual properties
Ornament models support the concept of _virtual properties_ (which are, by
definition, read-only).

An example of a virtual property would be a model with a `firstname` and
`lastname` property, and a getter for `fullname`. To mark a method as a getter,
attribute it with `#[\Ornament\Core\Getter("property")]`:

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

## Decorator classes
As of version 0.16, Ornament supports three types of decorator classes: simple
backed enums, classes that work by just receiving the value, and decorators
implementing `Ornament\Core\DecoratorInterface`. Generally, your decorators will
extend the `Ornament\Core\Decorator` base class, but you can also use something
like `Carbon\Carbon`.

First, an example using an enum:

```php
<?php

enum MyEnum : int
{
    case cool = 1;
    case stuff = 2;
}

class MyModel
{
    // ...

    public MyEnum $example;
}

// This now fails, since 3 is not in the enum:
$model = MyModel::fromIterable(['example' => 3]);
```

If an enum decorator is marked as nullable, Ornament will use `tryFrom` and the
above example would have not thrown an error, but instead have set
`$model->example` to `null`.

The first argument passed to the constructor is the raw value. If the decorator
extends Ornament's core Decorator class, the second argument is a
`ReflectionProperty` of the property being decorated, which the custom decorator
can use to extract attributes for configuration. Finally, you may add multiple
`Ornament\Core\Construct` attributes specifying additional arguments. In the
earlier example of Carbon, this could specify the time zone, for instance.

It is recommended that a decorating class also supports a `__toString` method,
so one can seamlessly pass decorated properties back to a storage engine.

## PHP, PDO and `fetchObject`
PDO's `fetchObject` and related methods try to be clever by injecting
properties based on fetched database columns _before_ the constructor is called.
PHP 7.4 doesn't like that, since a decorated property will be of the wrong type!

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
worked with `fetchObject`; this is no longer possible on PHP 7.4 so we strongly
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

