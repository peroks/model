# Models: Classes with built-in data validation

The `Model` base class included in this package extends the built-in
PHP `ArrayObject` class with internal data validation.

## Reason why

Models can be used in any context, but they are especially useful in REST API
applications, where validating incoming and outgoing data is often
time-consuming, inconsistent, error-prone and hard to read/maintain.

Instead of writing code to validate data, you can let **models validate
themselves** based on their **property definition**. You just define the
properties and constraints of each model in one single place, and then validate
model instances throughout your application in a consistent, efficient and clear
manner.

A model is similar to a database table, where each model property corresponds to
a table column. This project aims to automatically create database tables based
on models in future versions.

## How to use

### Create a model class

You define the model properties and constraints in a **separate class** for each
model. Each model class must contain the static `$properties` property and
extend the `Model` class or subclass.

In the examples below, we work with a geo point model.

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

You can also extend models like any other class. The properties are inherited
from the parent classes.

    <?php
    
    use Peroks\Model\Model;
    use Peroks\Model\PropertyType;
    
    /**
     * The GeoPoint model with altitue. The altitude is optional.
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

### Instantiate a model

There are several ways to create a model instance. The model constructor takes
an assoc array, an object (including a model instance) or a json string.
All the options below create the same geo model instance.

    $data = [ latitude => 70.6646625, longitude => 23.6807195 ];
    $json = '{"latitude": 70.6646625, "longitude": 23.6807195}';
    
    a) $geo = new GeoPoint( $data );
    b) $geo = GeoPoint:create( $data );
    c) $geo = GeoPoint:create( (object) $data );
    d) $geo = GeoPoint:create( $json );
    e) $geo = GeoPoint:create()->patch( $data );
    f) $geo = GeoPoint:create()->replace( $data );

Or you just create an empty model and add the property values later on.

    $geo = new GeoPoint();
    $geo->latitude = 70.6646625;
    $geo->longitude = 23.6807195;

Just like the `ArrayObject` parent class, you can also set (and get) model
properties like an array.

    $geo = new GeoPoint();
    $geo['latitude'] = 70.6646625;
    $geo['longitude'] = 23.6807195;

### Model validation

Since each model knows its property definitions and constraints, validating
a model is a breeze.

    $data = [ latitude => 70.6646625, longitude => 23.6807195 ];
    
    // Returns the model instance on success or null on failure.
    $geo = GeoPoint:create( $data )->validate(); // Returns the model instance.
    $geo = GeoPoint:create()->validate(); // Returns null.
    $geo = GeoPoint:create( [ latitude => 70.6646625 ] )->validate(); // Returns null

Alternatively, you can let the validation throw a `ModelException` on failure.

    $data = [ latitude => 70.6646625, longitude => 23.6807195 ];
    
    // Returns the model instance on success or throws a ModelException on failure.
    $geo = GeoPoint:create( $data )->validate( true ); // Returns the model instance.
    $geo = GeoPoint:create()->validate( true ); // Throws ModelExeption.
    $geo = GeoPoint:create( [ latitude => 70.6646625 ] )->validate( true ); // Throws ModelExeption.

Models are not validated on creation, only when the `validate()` method is called.

### Getting the model data

You can access the model data as an object or array

    $geo = GeoPoint:create( [ latitude => 70.6646625, longitude => 23.6807195 ] );
    $latitude = $geo->latitude;
    $longitude = $geo['longitude'];

or get the model data as an assoc array.

    $geo = GeoPoint:create( [ latitude => 70.6646625, longitude => 23.6807195 ] );
    $data = $geo->data();

### JSON encoding

You can easily convert a model to JSON.

    $geo = GeoPoint:create( [ latitude => 70.6646625, longitude => 23.6807195 ] );
    a) $json = json_encode( $geo );
    b) $json = (string) $geo;

### Nested models

Models can contain other models. You just add a `model` with a class name to an
`object` or `array` property.

    <?php
    
    use Peroks\Model\Model;
    use Peroks\Model\PropertyType;
    
    /**
     * The GeoPoint model with altitue. The altitude is optional.
     * 
     * @property float $altitude The geo point altitude.
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

If you add default values for sub-models, they are also created when the main
model is created. On validation, sub-models are validated recursively too.

    // Validates the travel model and all sub-models. 
    $travel = Tarvel::create()->validate(); // Returns a validated Travel model.
    $from   = $travel->from; // Returns a GeoPont model, already validated.

Nested models are especially useful for importing **complex data structures**
from an **external source**. Decoding, converting and validating external data
is a **one-liner**.

    // Decode, convert and validate external data structures.
    $json   = $client->import(); // Json encoded string from an api call.
    $travel = Tarvel::create( $json )->validate( true );

## Built-in items for property definitions and constraints

    abstract class PropertyItem {
        const ID          = 'id';           // string, The property id (required).
        const NAME        = 'name';         // string, The property name (required).
        const DESC        = 'desc';         // string, The property description (default: null).
        const TYPE        = 'type';         // string, The property type (default: PropertyType::MIXED).
        const MODEL       = 'model';        // string, The class name of a model (default: null).
        const OBJECT      = 'object';       // string, The class or interface name to validate an object against (default: null).
        const FOREIGN     = 'foreign';      // string, The property contains an id of the (foreign) model class name (default: null).
        const DEFAULT     = 'default';      // mixed, The property default value (default: null).
        const REQUIRED    = 'required';     // bool, Whether the property is required or not (default: false).
        const DISABLED    = 'disabled';     // bool, Whether the property is disabled or not (default: false).
        const READABLE    = 'readable';     // bool, Whether the property is readable or not (default: true).
        const WRITABLE    = 'writable';     // bool, Whether the property is writable or not (default: true).
        const UNIQUE      = 'unique';       // bool, Whether the property value is unique or not (default: false).
        const INDEX       = 'index';        // bool, Whether the property is a db index or not (default: false).
        const PATTERN     = 'pattern';      // string, A regex pattern to validate a string value against (default: null).
        const ENUMERATION = 'enumeration';  // array, An enumeration of all valid property values (default: null).
        const MIN         = 'min';          // int|float, The minimum numeric value or string/array length (default: null).
        const MAX         = 'max';          // int|float, The maximum numeric value or string/array length (default: null).
        const VALUE       = 'value';        // mixed, The property value (default: null).
        const PROPERTIES  = 'properties';   // array, An array of model property definitions (default: null).
    }

Currently, the `foreign`, `unique` and `index` property items are not in use.
There is no validation based on these items. They are intended for creating
database tables in later versions.

## Supported property types

    abstract class PropertyType {
        const MIXED    = '';            // Any type, no validation.
        const BOOL     = 'boolean';
        const NUMBER   = 'number';      // Integer or float.
        const INTEGER  = 'integer';
        const FLOAT    = 'double';
        const STRING   = 'string';
        const UUID     = 'uuid';        // A uuid string.
        const DATETIME = 'datetime';    // An ISO 8601 datetime string.
        const DATE     = 'date';        // A date string (Y-m-d).
        const TIME     = 'time';        // A time string (h:n or h:n:s).
        const ARRAY    = 'array';
        const OBJECT   = 'object';
        const FUNCTION = 'function';    // A callable function, array or string.
    }

## Installing

You need **composer** to download and install peroks/model.
Just run `composer require peroks/model` in your project.
