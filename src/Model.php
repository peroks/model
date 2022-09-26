<?php namespace Peroks\Model;

use ArrayAccess;
use ArrayObject;
use Traversable;

/**
 * The model class.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
class Model extends ArrayObject implements ModelInterface {

	/**
	 * @var array An array of model properties.
	 */
	protected static array $properties = [];

	/**
	 * Constructor.
	 *
	 * @param array|object $data The model data.
	 */
	public function __construct( $data = [] ) {
		$data = static::normalizeData( $data );
		parent::__construct( $data, ArrayObject::ARRAY_AS_PROPS );
	}

	/* -------------------------------------------------------------------------
	 * Public methods
	 * ---------------------------------------------------------------------- */

	/**
	 * Gets the model's id value.
	 *
	 * @return int|string The model id.
	 */
	public function id() {
		return $this[ static::idProperty() ] ?? '';
	}

	/**
	 * Gets an array representing the model's property values.
	 *
	 * @param string $content Specifies the content of the returned data array.
	 *
	 * @return Property[]|array An array of the model data.
	 */
	public function data( string $content = ModelData::FULL ): array {
		$properties = static::properties();
		$data       = $this->getArrayCopy();

		// Get a compact data array stripped of all null and default values.
		if ( ModelData::COMPACT === $content ) {
			return static::dataCompact( $data, $properties );
		}

		// Get an array of model properties including the property value.
		if ( ModelData::PROPERTIES == $content ) {
			return static::dataProperties( $data, $properties );
		}

		// Get an array of the model data values.
		if ( $properties ) {
			return array_intersect_key( $data, $properties );
		}

		// Get the raw internal data.
		return $data;
	}

	/**
	 * Patches a model with the given data.
	 *
	 * @param array|object $data The data to be merged into the model.
	 *
	 * @return static The updated model instance.
	 */
	public function patch( $data ): self {
		foreach ( static::normalizeData( $data, false ) as $id => $value ) {
			$this[ $id ] = $value;
		}
		return $this;
	}

	/**
	 * Validates the model values against its property definitions.
	 *
	 * @return static The validated model instance.
	 */
	public function validate(): self {
		foreach ( static::properties() as $id => $property ) {
			$value = $this[ $id ];

			if ( is_null( $value ) ) {
				static::validateRequired( $property );
				continue;
			}
			if ( $type = $property[ PropertyItem::TYPE ] ?? PropertyType::MIXED ) {
				static::validateType( $value, $type, $property );
			}
			if ( $class = $property[ PropertyItem::MODEL ] ?? null ) {
				static::validateModel( $value, $class, $property );
			}
			if ( $class = $property[ PropertyItem::OBJECT ] ?? null ) {
				static::validateObject( $value, $class, $property );
			}
			if ( $pattern = $property[ PropertyItem::PATTERN ] ?? null ) {
				static::validatePattern( $value, $pattern, $property );
			}
			if ( $enum = $property[ PropertyItem::ENUMERATION ] ?? null ) {
				static::validateEnumeration( $value, $enum, $property );
			}
			if ( isset( $property[ PropertyItem::MIN ] ) ) {
				static::validateMinimum( $value, $property );
			}
			if ( isset( $property[ PropertyItem::MAX ] ) ) {
				static::validateMaximum( $value, $property );
			}
		}

		return $this;
	}

	/* -------------------------------------------------------------------------
	 * Public static methods
	 * ---------------------------------------------------------------------- */

	/**
	 * Creates a new model with data from the given array or object.
	 *
	 * @param array|object $data The model data.
	 *
	 * @return static A model instance.
	 */
	public static function create( $data = [] ): self {
		return new static( $data );
	}

	/**
	 * Gets the model's properties.
	 *
	 * @return array[] An array of property definitions.
	 */
	public static function properties(): array {

		// Inherit parent properties.
		if ( $parent = get_parent_class( static::class ) ) {
			if ( is_a( $parent, ModelInterface::class, true ) ) {
				if ( $properties = $parent::properties() ) {
					return array_replace( $properties, static::$properties );
				}
			}
		}

		return static::$properties;
	}

	/**
	 * Gets the model's id property.
	 *
	 * @return string The model's id property.
	 */
	public static function idProperty(): string {
		return 'id';
	}

	/**
	 * Gets the model property matching the given id.
	 *
	 * @param string $id The property id.
	 *
	 * @return Property|null The property matching the id.
	 */
	public static function getProperty( string $id ): ?Property {
		$property = static::properties()[ $id ] ?? null;
		return $property ? new Property( $property ) : null;
	}

	/**
	 * Adds a new or overrides an existing model property.
	 *
	 * @param Property $property A custom property.
	 */
	public static function setProperty( Property $property ): void {
		$property->validate();
		static::$properties[ $property->id() ] = $property->data();
	}

	/* -------------------------------------------------------------------------
	 * JsonSerializable implementation
	 * ---------------------------------------------------------------------- */

	/**
	 * Specifies data which should be serialized to JSON.
	 *
	 * @return array
	 */
	public function jsonSerialize(): array {
		return $this->getArrayCopy();
	}

	/* -------------------------------------------------------------------------
	 * Stringable implementation
	 * ---------------------------------------------------------------------- */

	/**
	 * Gets the model as a json encoded string.
	 */
	public function __toString(): string {
		return json_encode( $this, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/* -------------------------------------------------------------------------
	 * ArrayObject overrides
	 * ---------------------------------------------------------------------- */

	/**
	 * Assign a value to a model property.
	 *
	 * @param mixed $key The property id.
	 * @param mixed $value The property value.
	 */
	public function offsetSet( $key, $value ): void {
		$properties = static::properties();
		$writable   = $properties[ PropertyItem::WRITABLE ] ?? true;

		if ( $properties && empty( $writable && array_key_exists( $key, $properties ) ) ) {
			$error = sprintf( 'Setting the %s property in %s is not allowed.', $key, static::class );
			throw new ModelException( $error, 400 );
		}

		parent::offsetSet( $key, $value );
	}

	/**
	 * Deletes a property from the model.
	 *
	 * @param mixed $key The property id.
	 */
	public function offsetUnset( $key ): void {
		$properties = static::properties();

		if ( $properties && array_key_exists( $key, $properties ) ) {
			$error = sprintf( 'Deleting the %s property in %s is not allowed.', $key, static::class );
			throw new ModelException( $error, 400 );
		}

		parent::offsetUnset( $key );
	}

	/**
	 * Adds a value to the model.
	 *
	 * This method doesn't work when the model has properties.
	 * Use Model::offsetSet() instead.
	 *
	 * @param mixed $value The value to append to the model.
	 */
	public function append( $value ): void {
		if ( empty( static::properties() ) ) {
			parent::append( $value );
		}
	}

	/**
	 * Replaces the model data.
	 *
	 * @param array|object $array The new model data.
	 *
	 * @return array The old model data.
	 */
	public function exchangeArray( $array ): array {
		return parent::exchangeArray( self::normalizeData( $array ) );
	}

	/* -------------------------------------------------------------------------
	 * Protected static methods
	 * ---------------------------------------------------------------------- */

	/**
	 * Normalize data.
	 *
	 * @param array|object $data The data to populate the model with.
	 * @param bool $include Whether to include default values in the result or not.
	 *
	 * @return array The normalized data.
	 */
	protected static function normalizeData( $data, bool $include = true ): array {
		$properties = static::properties();
		$result     = [];

		// If no properties are defined, accept all data;
		if ( empty( $properties ) ) {
			return $data;
		}

		if ( $data instanceof ModelInterface ) {
			$data = $data->data();
		}
		elseif ( $data instanceof ArrayObject ) {
			$data = $data->getArrayCopy();
		}
		elseif ( is_object( $data ) && empty( $data instanceof ArrayAccess ) ) {
			$data = get_object_vars( $data );
		}

		if ( is_array( $data ) || $data instanceof ArrayAccess ) {
			foreach ( $properties as $id => $property ) {
				if ( $include || Utils::keyExists( $id, $data ) ) {
					$value         = $data[ $id ] ?? $property[ PropertyItem::DEFAULT ] ?? null;
					$result[ $id ] = static::normalizeProperty( $value, $property );
				}
			}
		}

		return $result;
	}

	/**
	 * Normalize a property value.
	 *
	 * @param mixed $value The property value.
	 * @param Property|array $property The property definition.
	 *
	 * @return mixed The modified property value.
	 */
	protected static function normalizeProperty( $value, $property ) {
		$type = $property[ PropertyItem::TYPE ] ?? null;

		if ( $type === PropertyType::UUID && $value === true ) {
			return Utils::uuid();
		}

		if ( $model = $property[ PropertyItem::MODEL ] ?? null ) {
			if ( $type === PropertyType::OBJECT ) {
				if ( is_array( $value ) ) {
					return new $model( $value );
				}
			}
			elseif ( $type === PropertyType::ARRAY ) {
				if ( is_array( $value ) || $value instanceof Traversable ) {
					foreach ( $value as &$item ) {
						$item = new $model( $item );
					}
				}
			}
		}

		return $value;
	}

	/**
	 * Get a compact data array stripped of all null and default values.
	 *
	 * @param array $data The internal data array.
	 * @param Property[]|array $properties The model properties.
	 *
	 * @return array The compact model data.
	 */
	protected static function dataCompact( array $data, array $properties ): array {
		foreach ( $properties as $id => $property ) {
			if ( array_key_exists( $id, $data ) ) {
				$default = $property[ PropertyItem::DEFAULT ] ?? null;
				$value   = $data[ $id ];

				if ( $value instanceof ModelInterface ) {
					$value = $value->data( ModelData::COMPACT );
				}

				if ( $value !== $default ) {
					$result[ $id ] = $value;
				}
			}
		}

		return $result ?? [];
	}

	/**
	 * Get an array of model properties including the property value.
	 *
	 * @param array $data The internal data array.
	 * @param Property[]|array[] $properties The model properties.
	 *
	 * @return Property[] The model data as an array of properties.
	 */
	protected static function dataProperties( array $data, array $properties ): array {
		foreach ( $properties as $id => $property ) {
			if ( $property[ PropertyItem::DISABLED ] ?? false ) {
				continue;
			}

			if ( $property[ PropertyItem::READABLE ] ?? true ) {
				$property[ PropertyItem::VALUE ] = $data[ $id ];

				if ( $property[ PropertyItem::VALUE ] instanceof ModelInterface ) {
					$property[ PropertyItem::PROPERTIES ] = $property[ PropertyItem::VALUE ]->data( ModelData::PROPERTIES );
				}
			}
			$result[] = Property::create( $property );
		}

		return $result ?? [];
	}

	/**
	 * Checks if a property is required.
	 *
	 * @param Property|array $property The property definition.
	 */
	protected static function validateRequired( $property ): void {
		if ( $property[ PropertyItem::REQUIRED ] ?? false ) {
			$name  = $property[ PropertyItem::NAME ];
			$error = sprintf( '%s is required.', $name );
			throw new ModelException( $error, 400 );
		}
	}

	/**
	 * Checks that the value is of the correct type.
	 *
	 * @param mixed $value The property value to validate.
	 * @param string $type The property type.
	 * @param Property|array $property The property definition.
	 */
	protected static function validateType( $value, string $type, $property ): void {

		// Check number type.
		if ( $type === PropertyType::NUMBER ) {
			if ( empty( is_integer( $value ) || is_float( $value ) ) ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must be of type %s.', $name, $type );
				throw new ModelException( $error, 400 );
			}
		}

		elseif ( in_array( $type, [ PropertyType::DATETIME, PropertyType::DATE, PropertyType::TIME ] ) ) {
			if ( empty( is_string( $value ) && static::validateDateTime( $value, $type ) ) ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must be a valid %s.', $name, $type );
				throw new ModelException( $error, 400 );
			}
		}

		// Check uuid type.
		elseif ( $type === PropertyType::UUID ) {
			if ( empty( is_string( $value ) && strlen( $value ) === 36 ) ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must be of type %s.', $name, $type );
				throw new ModelException( $error, 400 );
			}
		}

		// Check all other types.
		elseif ( $type !== gettype( $value ) ) {
			$name  = $property[ PropertyItem::NAME ];
			$error = sprintf( '%s must be of type %s.', $name, $type );
			throw new ModelException( $error, 400 );
		}
	}

	/**
	 * Validates a model.
	 *
	 * @param mixed $value The property value to validate.
	 * @param string $class The model class.
	 * @param Property|array $property The property definition.
	 *
	 * @throws ModelException
	 */
	protected static function validateModel( $value, string $class, $property ): void {

		// Validate a single model.
		if ( is_object( $value ) ) {
			static::validateClass( $value, $class, $property );
			static::validateClass( $value, ModelInterface::class, $property );
			$value->validate();
		}

		// Validate an array of models.
		elseif ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				static::validateClass( $item, $class, $property );
				static::validateClass( $item, ModelInterface::class, $property );
				$item->validate();
			}
		}
	}

	/**
	 * Validates an object.
	 *
	 * @param mixed $value The property value to validate.
	 * @param string $class The model class.
	 * @param Property|array $property The property definition.
	 */
	protected static function validateObject( $value, string $class, $property ): void {

		// Validate a single object.
		if ( is_object( $value ) ) {
			static::validateClass( $value, $class, $property );
		}

		// Validate an array of object.
		elseif ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				static::validateClass( $item, $class, $property );
			}
		}
	}

	/**
	 * Validates a string against a regex pattern.
	 *
	 * @param mixed $value The property value to validate.
	 * @param string $pattern The property validation pattern.
	 * @param Property|array $property The property definition.
	 */
	protected static function validatePattern( $value, string $pattern, $property ): void {
		$regex = '/' . str_replace( '/', '\\/', $pattern ) . '/';

		// Only strings are validated.
		if ( is_string( $value ) ) {
			if ( empty( preg_match( $regex, $value ) ) ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must match the regex pattern %s', $name, $pattern );
				throw new ModelException( $error, 400 );
			}
		}
	}

	/**
	 * Validates a property value against an enumeration constraint.
	 *
	 * @param mixed $value The property value to validate.
	 * @param array $enum An array of valid values.
	 * @param Property|array $property The property definition.
	 */
	protected static function validateEnumeration( $value, array $enum, $property ): void {

		// Check enumeration constraint on scalar values.
		if ( is_scalar( $value ) ) {
			if ( empty( in_array( $value, $enum, true ) ) ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must be one of %s', $name, join( ', ', $enum ) );
				throw new ModelException( $error, 400 );
			}
		}

		// Check enumeration constraint on arrays.
		elseif ( is_array( $value ) ) {
			if ( empty( array_intersect( $value, $enum ) === $value ) ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must be one of %s', $name, join( ', ', $enum ) );
				throw new ModelException( $error, 400 );
			}
		}
	}

	/**
	 * Validates a property value against a minimum constraint.
	 *
	 * @param mixed $value The property value to validate.
	 * @param Property|array $property The property definition.
	 */
	protected static function validateMinimum( $value, $property ): void {
		$min = $property[ PropertyItem::MIN ];

		// Check minimum constraint on numbers.
		if ( is_int( $value ) || is_float( $value ) ) {
			if ( $value < $min ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must be at least %d.', $name, $min );
				throw new ModelException( $error, 400 );
			}
		}

		// Check minimum constraint on strings.
		elseif ( is_string( $value ) ) {
			if ( strlen( $value ) < $min ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must contain at least %d characters.', $name, $min );
				throw new ModelException( $error, 400 );
			}
		}

		// Check minimum constraint on arrays.
		elseif ( is_array( $value ) ) {
			if ( count( $value ) < $min ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must contain at least %d entries.', $name, $min );
				throw new ModelException( $error, 400 );
			}
		}
	}

	/**
	 * Validates a property value against a maximum constraint.
	 *
	 * @param mixed $value The property value to validate.
	 * @param Property|array $property The property definition.
	 */
	protected static function validateMaximum( $value, $property ): void {
		$max = $property[ PropertyItem::MAX ];

		// Check maximum constraint on numbers.
		if ( is_int( $value ) || is_float( $value ) ) {
			if ( $value > $max ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must be max %d.', $name, $max );
				throw new ModelException( $error, 400 );
			}
		}

		// Check maximum constraint on strings.
		elseif ( is_string( $value ) ) {
			if ( strlen( $value ) > $max ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must contain max %d characters.', $name, $max );
				throw new ModelException( $error, 400 );
			}
		}

		// Check maximum constraint on arrays.
		elseif ( is_array( $value ) ) {
			if ( count( $value ) > $max ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must contain max %d entries.', $name, $max );
				throw new ModelException( $error, 400 );
			}
		}
	}

	/**
	 * Checks that the object is an instance of the given class.
	 *
	 * @param object $value The object to validate.
	 * @param string $class The object class or interface name.
	 * @param Property|array $property The property definition.
	 */
	protected static function validateClass( object $value, string $class, array $property ): void {
		if ( empty( is_a( $value, $class ) ) ) {
			$name  = $property[ PropertyItem::NAME ];
			$error = sprintf( '%s must be an instance of %s', $name, $class );
			throw new ModelException( $error, 400 );
		}
	}

	/**
	 * Checks if the value is a valid date/time.
	 *
	 * @param string $value The value to validate.
	 * @param string $type The property type.
	 *
	 * @return bool
	 */
	protected static function validateDateTime( string $value, string $type ): bool {
		switch ( $type ) {
			case PropertyType::DATETIME:
				return Utils::validateDate( $value );
			case PropertyType::DATE:
				return Utils::validateDate( $value, 'yyyy-mm-dd' );
			case PropertyType::TIME:
				return Utils::validateTime( $value );
		}
		return false;
	}
}
