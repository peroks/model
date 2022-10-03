# Models: Classes with built-in data validation

The `Model` base class included in this package extends the built-in
PHP `ArrayObject` class with internal data validation.

## Reason why

Models can be used in any context, but they are especially useful in REST API
applications, where validating incoming and outgoing data is often
time-consuming, inconsistent, error-prone and hard to read/maintain.

Instead of writing code to validate data, you can let **models validate
themselves** based on their property definition. You just define the properties
and constraints of each model in one single place, and then validate model
instances throughout your application in a consistent, efficient and clear
manner.

## How to use

### Create a model class

You define the model properties and constraints in a separate class for each
model, in this example a geo point model.

    <?php namespace Your\Namespace;
    
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

    /**
     * The GeoPoint model with altitue.
     * 
     * @property float $altitude The geo point altitude.
     */
    class GeoPointAlt extends GeoPoint {
        
        /**
         * @var array An array of model properties.
         */
        protected static array $properties = [
            'altitude' => [
                'id'   => 'altitude',
                'name' => 'Altitude',
                'desc' => 'The geo point altitude',
                'type' => PropertyType::FLOAT,
            ],
        ];
    }

### Instantiate a model

There are several ways to create a model instance. The model constructor takes
an assoc array or an object (including a model instance).

    $data = [ latitude => 70.6646625, longitude => 23.6807195 ];
    
    a) $geo = new GeoPoint( $data );
    b) $geo = GeoPoint:create( $data );
    c) $geo = GeoPoint:create()->patch( $data );
    d) $geo = GeoPoint:create()->replace( $data );

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

## Built-in items for property definitions and constraints (extendable)

    string    id          The property id (required).
    string    name        The property name (required).
    string    desc        The property description (default: null).
    string    type        The property type (default: PropertyType::MIXED).
    string    model       The class name of a model (default: null).
    string    object      The class or interface name to validate an object against (default: null).
    string    foreign     The property contains an id of the (foreign) model class name (default: null).
    mixed     default     The property default value (default: null).
    bool      required    Whether the property is required or not (default: false).
    bool      disabled    Whether the property is disabled or not (default: false).
    bool      readable    Whether the property is readable or not (default: true).
    bool      writeable   Whether the property is writable or not (default: true).
    bool      unique      Whether the property value is unique or not (default: false).
    string    pattern     A regex pattern to validate the property value against (default: null).
    array     enumeration An enumeration of all valid property values (default: null).
    int|float min         The minimum numeric value or string/array length (default: null).
    int|float max         The maximum numeric value or string/array length (default: null).
    mixed     value       The property value (default: null).
    array     properties  An array of model property definitions (default: null).

## Supported property types

    abstract class PropertyType {
        const MIXED    = '';
        const BOOL     = 'boolean';
        const NUMBER   = 'number';
        const INTEGER  = 'integer';
        const FLOAT    = 'double';
        const STRING   = 'string';
        const UUID     = 'uuid';
        const DATETIME = 'datetime';
        const DATE     = 'date';
        const TIME     = 'time';
        const ARRAY    = 'array';
        const OBJECT   = 'object';
        const FUNCTION = 'function';
    }


## Installing

You need **composer** to download and install peroks/model.
Just run `composer require peroks/model` in your project.
