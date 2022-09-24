<?php namespace Peroks\Model;

use ArrayAccess;
use ArrayObject;

/**
 * The model class.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
class Model extends ArrayObject implements ModelInterface {

	/**
	 * @var array An array of custom properties.
	 */
	protected static array $properties = [];

	/**
	 * Constructor.
	 *
	 * @param array|object $data The model data.
	 */
	public function __construct( $data = [] ) {
		$data = static::normalize( $data );
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

		// Get an array of model properties including the property value.
		if ( ModelData::PROPERTIES == $content ) {
			foreach ( $properties as $id => $property ) {
				if ( $property[ PropertyItem::DISABLED ] ?? false ) {
					continue;
				}

				if ( $property[ PropertyItem::READABLE ] ?? true ) {
					$property[ PropertyItem::VALUE ] = $this[ $id ];

					if ( $property[ PropertyItem::VALUE ] instanceof ModelInterface ) {
						$property[ PropertyItem::PROPERTIES ] = $property[ PropertyItem::VALUE ]->data( ModelData::PROPERTIES );
					}
				}
				$result[] = Property::create( $property );
			}
			return $result ?? [];
		}

		// Get an array of the model data values.
		if ( $properties ) {
			return array_intersect_key( $data, static::properties() );
		}

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
		foreach ( static::normalize( $data, false ) as $id => $value ) {
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
			static::validateProperty( $this[ $id ], $property );
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
	 * Adds a new or changes an existing model property.
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
		return parent::exchangeArray( self::normalize( $array ) );
	}

	/* -------------------------------------------------------------------------
	 * Protected static utils
	 * ---------------------------------------------------------------------- */

	/**
	 * Normalize data.
	 *
	 * @param array|object $data The data to populate the model with.
	 * @param bool $include Whether to include default values in the result or not.
	 *
	 * @return array The normalized data.
	 */
	protected static function normalize( $data, bool $include = true ): array {
		$properties = static::properties();

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
				$type  = $property[ PropertyItem::TYPE ] ?? null;
				$model = $property[ PropertyItem::MODEL ] ?? null;
				$value = $data[ $id ] ?? $property[ PropertyItem::DEFAULT ] ?? null;

				if ( $model && is_array( $value ) ) {
					if ( $type === PropertyType::OBJECT ) {
						$value = new $model( $value );
					}
					elseif ( $type === PropertyType::ARRAY ) {
						foreach ( $value as &$item ) {
							$item = new $model( $item );
						}
					}
				}

				if ( $include || static::keyExists( $id, $data ) ) {
					$result[ $id ] = $value;
				}
			}
		}

		return $result ?? [];
	}

	/**
	 * Checks if the given key or index exists in the data.
	 *
	 * @param int|string $key The key to check for.
	 * @param array|ArrayAccess $data An array or object keys to check.
	 *
	 * @return bool True if the key exists in the data.
	 */
	protected static function keyExists( string $key, $data ): bool {
		if ( is_array( $data ) ) {
			return array_key_exists( $key, $data );
		}
		if ( $data instanceof ArrayAccess ) {
			return $data->offsetExists( $key );
		}
		return false;
	}

	/**
	 * Validates a property value against its definitions.
	 *
	 * @param mixed $value The property value to validate.
	 * @param Property|array $property The property definition.
	 */
	protected static function validateProperty( $value, $property ): void {
		$name = $property[ PropertyItem::NAME ];
		$type = $property[ PropertyItem::TYPE ] ?? PropertyType::MIXED;

		// Check that all required properties are set.
		if ( is_null( $value ) ) {
			if ( $property[ PropertyItem::REQUIRED ] ?? false ) {
				$error = sprintf( '%s is required.', $name );
				throw new ModelException( $error, 400 );
			}

			return;
		}

		// Check number type constraint.
		if ( PropertyType::NUMBER === $type ) {
			if ( empty( is_integer( $value ) || is_float( $value ) ) ) {
				$error = sprintf( '%s must be of type %s.', $name, $type );
				throw new ModelException( $error, 400 );
			}
		}

		// Check all other types.
		elseif ( $type && $type !== gettype( $value ) ) {
			$error = sprintf( '%s must be of type %s.', $name, $type );
			throw new ModelException( $error, 400 );
		}

		// Check model constraint.
		if ( isset( $property[ PropertyItem::MODEL ] ) ) {
			$class = $property[ PropertyItem::MODEL ];

			// Validate a single model.
			if ( PropertyType::OBJECT === $type ) {
				static::validateModel( $value, $class, $name );
			}

			// Validate an array of models.
			elseif ( PropertyType::ARRAY === $type ) {
				foreach ( $value as $item ) {
					static::validateModel( $item, $class, $name );
				}
			}

			return;
		}

		// Check object class name constraint.
		if ( isset( $property[ PropertyItem::OBJECT ] ) ) {
			$class = $property[ PropertyItem::OBJECT ];

			// Validate a single object.
			if ( PropertyType::OBJECT === $type ) {
				static::validateObject( $value, $class, $name );
			}

			// Validate an array of objects.
			elseif ( PropertyType::ARRAY === $type ) {
				foreach ( $value as $item ) {
					static::validateObject( $item, $class, $name );
				}
			}

			return;
		}

		// Check regex pattern constraint.
		if ( isset( $property[ PropertyItem::PATTERN ] ) ) {
			if ( is_string( $value ) ) {
				$pattern = $property[ PropertyItem::PATTERN ];
				$regex   = '/' . str_replace( '/', '\\/', $pattern ) . '/';

				if ( empty( preg_match( $regex, $value ) ) ) {
					$error = sprintf( '%s must match the regex pattern %s', $name, $pattern );
					throw new ModelException( $error, 400 );
				}
			}
		}

		// Check enumeration constraint.
		if ( isset( $property[ PropertyItem::ENUM ] ) ) {
			$enum = $property[ PropertyItem::ENUM ];

			// Check enumeration constraint on scalar values.
			if ( is_scalar( $value ) ) {
				if ( empty( in_array( $value, $enum, true ) ) ) {
					$error = sprintf( '%s must be one of %s', $name, join( ', ', $enum ) );
					throw new ModelException( $error, 400 );
				}
			}

			// Check enumeration constraint on arrays.
			elseif ( is_array( $value ) ) {
				if ( empty( array_intersect( $value, $enum ) === $value ) ) {
					$error = sprintf( '%s must be one of %s', $name, join( ', ', $enum ) );
					throw new ModelException( $error, 400 );
				}
			}
		}

		// Check minimum constraint.
		if ( isset( $property[ PropertyItem::MIN ] ) ) {
			$min = $property[ PropertyItem::MIN ];

			// Check minimum constraint on numbers.
			if ( is_int( $value ) || is_float( $value ) ) {
				if ( $value < $min ) {
					$error = sprintf( '%s must be at least %d.', $name, $min );
					throw new ModelException( $error, 400 );
				}
			}

			// Check minimum constraint on strings.
			elseif ( is_string( $value ) ) {
				if ( strlen( $value ) < $min ) {
					$error = sprintf( '%s must contain at least %d characters.', $name, $min );
					throw new ModelException( $error, 400 );
				}
			}

			// Check minimum constraint on arrays.
			elseif ( is_array( $value ) ) {
				if ( count( $value ) < $min ) {
					$error = sprintf( '%s must contain at least %d entries.', $name, $min );
					throw new ModelException( $error, 400 );
				}
			}
		}

		// Check maximum constraint.
		if ( isset( $property[ PropertyItem::MAX ] ) ) {
			$max = $property[ PropertyItem::MAX ];

			// Check maximum constraint on numbers.
			if ( is_int( $value ) || is_float( $value ) ) {
				if ( $value > $max ) {
					$error = sprintf( '%s must be max %d.', $name, $max );
					throw new ModelException( $error, 400 );
				}
			}

			// Check maximum constraint on strings.
			elseif ( is_string( $value ) ) {
				if ( strlen( $value ) > $max ) {
					$error = sprintf( '%s must contain max %d characters.', $name, $max );
					throw new ModelException( $error, 400 );
				}
			}

			// Check maximum constraint on arrays.
			elseif ( is_array( $value ) ) {
				if ( count( $value ) > $max ) {
					$error = sprintf( '%s must contain max %d entries.', $name, $max );
					throw new ModelException( $error, 400 );
				}
			}
		}
	}

	/**
	 * Validates a model.
	 *
	 * @param object $value The model to validate.
	 * @param string $class The model class name.
	 * @param string $property The property name.
	 */
	protected static function validateModel( object $value, string $class, string $property ): void {
		static::validateObject( $value, $class, $property );
		static::validateObject( $value, ModelInterface::class, $property );
		$value->validate();
	}

	/**
	 * Validates an object.
	 *
	 * @param object $value The object to validate.
	 * @param string $class The object class or interface name.
	 * @param string $property The property name.
	 */
	protected static function validateObject( object $value, string $class, string $property ): void {
		if ( empty( is_a( $value, $class ) ) ) {
			$error = sprintf( '%s must be an instance of %s', $property, $class );
			throw new ModelException( $error, 400 );
		}
	}
}
