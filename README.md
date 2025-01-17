# Models: Classes with built-in data validation

The `Model` class extends the built-in PHP `ArrayObject` class with internal
data validation and json support.

## Reason why

Models can be used in any context, but they are especially useful in REST API
applications, where validating incoming and outgoing data is often
time-consuming, inconsistent, error-prone and hard to read/maintain.

Instead of bloating your project with code for validating data, you can
let **models validate themselves** based on their **property definition**.
You just define the properties and constraints of each model in **one single
place**, and then validate model instances throughout your application in a
consistent, efficient and clear manner.

Models are similar to **database tables**, where each **model property**
corresponds to a **table column**.
Install the [Model Store](https://packagist.org/packages/peroks/model-store)
package to automatically create database tables based on your models.

## How to use

### Create a model class

You define the model properties and constraints in a **separate class** for each
model. Each model class must contain the static `$properties` property and
extend the `Model` class or a subclass.

In the example below, we create a geo point model with two properties:
**latitude** and **longitude**. Both properties are **required**, and they must
be **floating** numbers in the range of **-90 to 90** (latitude)
and **-180 to 180** (longitude).

```php
<?php

use Peroks\Model\Model;
use Peroks\Model\PropertyType;

/**
 * The GeoPoint model class.
 *
 * @property float $latitude The geo point latitude.
 * @property float $longitude The geo point longitude.
 */
class GeoPoint extends Model {

    /**
     * @var array An array of model properties.
     */
    protected static array $properties = [
        'latitude'  => [
            'id'       => 'latitude',
            'name'     => 'Latitude',
            'desc'     => 'The geo point latitude',
            'type'     => PropertyType::FLOAT,
            'required' => true,
            'min'      => -90,
            'max'      => 90,
        ],
        'longitude' => [
            'id'       => 'longitude',
            'name'     => 'Longitude',
            'desc'     => 'The geo point longitude',
            'type'     => PropertyType::FLOAT,
            'required' => true,
            'min'      => -180,
            'max'      => 180,
        ],
    ];
}
```

You can extend models like any other class. The properties are inherited
from the parent classes.

```php
<?php

use Peroks\Model\PropertyType;

/**
 * The GeoPoint model with altitude. The altitude is optional.
 *
 * @property float $altitude The geo point altitude.
 */
class GeoPointWithAltitude extends GeoPoint {

    /**
     * @var array An array of model properties.
     */
    protected static array $properties = [
        'altitude' => [
            'id'   => 'altitude',
            'name' => 'Altitude',
            'desc' => 'The geo point altitude',
            'type' => PropertyType::NUMBER, // int or float.
        ],
    ];
}
```

### Create a model instance

There are several ways to create a model instance. The model constructor takes
an assoc array, an object (including a model instance) or a json string.
All the options below produce the same result.

```
$data = [ latitude => 70.6646625, longitude => 23.6807195 ];
$json = '{"latitude": 70.6646625, "longitude": 23.6807195}';

a) $geo = new GeoPoint( $data );
b) $geo = GeoPoint:create( $data );
c) $geo = GeoPoint:create( (object) $data );
d) $geo = GeoPoint:create( $json );
e) $geo = GeoPoint:create()->patch( $data );
f) $geo = GeoPoint:create()->replace( $data );
g) $geo = GeoPoint:load( 'geopoint.json' );
```

Or you just create an empty model and add the property values later on.

```php
$geo = new GeoPoint();
$geo->latitude  = 70.6646625;
$geo->longitude = 23.6807195;
```

Just like the `ArrayObject` parent class, you can also set (and get) model
properties like an array.

```php
$geo = new GeoPoint();
$geo['latitude']  = 70.6646625;
$geo['longitude'] = 23.6807195;
```

### Model validation

Since each model knows its property definitions and constraints, validating
a model is a breeze.

```php
$data = [ latitude => 70.6646625, longitude => 23.6807195 ];

// Returns the model instance on success or null on failure.
$geo = GeoPoint:create( $data )->validate(); // Returns the model instance.
$geo = GeoPoint:create()->validate(); // Returns null.
$geo = GeoPoint:create( [ latitude => 70.6646625 ] )->validate(); // Returns null
```

Alternatively, you can let the validation throw a `ModelException` on failure.

```php
$data = [ latitude => 70.6646625, longitude => 23.6807195 ];

// Returns the model instance on success or throws a ModelException on failure.
$geo = GeoPoint:create( $data )->validate( true ); // Returns the model instance.
$geo = GeoPoint:create()->validate( true ); // Throws ModelException.
$geo = GeoPoint:create( [ latitude => 70.6646625 ] )->validate( true ); // Throws ModelException.
```

Models are not validated on creation, only when `Model::validate()` is called.

### Getting the model data

You can access the model data as an object or array

```php
$geo = GeoPoint:create( [ latitude => 70.6646625, longitude => 23.6807195 ] );
$latitude  = $geo->latitude;
$longitude = $geo['longitude'];
```

or get the model data as an assoc array.

```php
$geo = GeoPoint:create( [ latitude => 70.6646625, longitude => 23.6807195 ] );
$data = $geo->data();
```

### JSON encoding

You can easily convert a model to JSON.

```php
$geo = GeoPoint:create( [ latitude => 70.6646625, longitude => 23.6807195 ] );
a) $json = json_encode( $geo );
b) $json = (string) $geo;
```

### Nested models

Models can contain other models. You just add `model` with a class name to an
`object` or `array` property.

```php
<?php

use Peroks\Model\Model;
use Peroks\Model\PropertyType;

/**
 * The Travel model.
 *
 * @property GeoPoint $from Where the travel starts.
 * @property GeoPoint $to Where the travel ends.
 */
class Travel extends Model {

    /**
     * @var array An array of model properties.
     */
    protected static array $properties = [
        'from' => [
            'id'      => 'from',
            'name'    => 'From geo point',
            'type'    => PropertyType::OBJECT,
            'model'   => GeoPoint::class,
            'default' => [ latitude => 70.6646625, longitude => 23.6807195 ],
            'require' => true,
        ],
        'to' => [
            'id'      => 'to',
            'name'    => 'To geo point',
            'type'    => PropertyType::OBJECT,
            'model'   => GeoPoint::class,
            'default' => [ latitude => 59.8521293, longitude => 10.6590668 ],
            'require' => true,
        ],
    ];
}
```

If you add default values for sub-models, they are also created when the main
model is created. On validation, sub-models are validated recursively too.

```php
// Validates the travel model and all sub-models.
$travel = Tarvel::create()->validate( true ); // Returns a valid Travel model.
$from   = $travel->from; // Returns a GeoPont model, already validated.
```

Nested models are especially useful for importing **complex data structures**
from **external sources**. With models, decoding, converting and validating
external data is a **one-liner**.

```php
// Decode, convert and validate external data structures.
$json   = $client->import(); // Json encoded string from an api call.
$travel = Tarvel::create( $json )->validate( true );
```

## Supported property items

```php
abstract class PropertyItem {
    const ID          = 'id';           // string, The property id (required).
    const NAME        = 'name';         // string, The property name (required).
    const DESC        = 'desc';         // string, The property description (default: null).
    const TYPE        = 'type';         // string, The property type (default: PropertyType::MIXED).
    const MODEL       = 'model';        // string, The class name of a model (default: null).
    const OBJECT      = 'object';       // string, The class or interface name to validate an object against (default: null).
    const FOREIGN     = 'foreign';      // string, The property contains an id of the (foreign) model class name (default: null).
    const MATCH       = 'match';        // string, The property of the (foreign) model to match the own id (default: null).
    const DEFAULT     = 'default';      // mixed, The property default value (default: null).
    const REQUIRED    = 'required';     // bool, Whether the property is required or not (default: false).
    const READABLE    = 'readable';     // bool, Whether the property is readable or not (default: true).
    const WRITABLE    = 'writable';     // bool, Whether the property is writable or not (default: true).
    const MUTABLE     = 'mutable';      // bool, Whether the property is mutable (changeable) or not (default: true).
    const PRIMARY     = 'primary';      // bool, Whether the property is the model's primary key or not (default: false).
    const INDEX       = 'index';        // string, The index name, properties with the same name are combined (default: null).
    const UNIQUE      = 'unique';       // string, The unique index name, properties with the same name are combined (default: null).
    const PATTERN     = 'pattern';      // string, A regex pattern to validate a string value against (default: null).
    const ENUMERATION = 'enumeration';  // array, An enumeration of all valid property values (default: null).
    const MIN         = 'min';          // int|float, The minimum numeric value or string/array length (default: null).
    const MAX         = 'max';          // int|float, The maximum numeric value or string/array length (default: null).
    const VALUE       = 'value';        // mixed, The property value (default: null).
    const PROPERTIES  = 'properties';   // array, An array of model property definitions (default: null).
}
```

The `PRIMARY`, `INDEX`, `UNIQUE`, `FOREIGN` and `MATCH` property items are only
used by `peroks/model-store`, a separate package for storing models in databases.
There, they are used for creating indices and constraints.

`VALUE` and `PROPERTIES` are ony used for exporting model data
including the model definition with `Model::data( ModelData::PROPERTIES )`.

## Supported property types

```php
abstract class PropertyType {
    const MIXED    = '';            // Any type, no validation.
    const BOOL     = 'boolean';
    const NUMBER   = 'number';      // Integer or float.
    const INTEGER  = 'integer';
    const FLOAT    = 'double';
    const STRING   = 'string';
    const UUID     = 'uuid';        // A uuid string.
    const URL      = 'url';         // A url.
    const EMAIL    = 'email';       // An email address.
    const DATETIME = 'datetime';    // An ISO 8601 datetime string.
    const DATE     = 'date';        // A date string (Y-m-d).
    const TIME     = 'time';        // A time string (H:i or H:i:s).
    const ARRAY    = 'array';
    const OBJECT   = 'object';
    const FUNCTION = 'function';    // A callable function.
}
```

## Installing

You need **composer** to download and install this package.
Just run `composer require peroks/model` in your project.
