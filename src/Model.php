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
	 * @var string The model's id property.
	 */
	protected static string $idProperty = '';

	/**
	 * @var array An array of model properties.
	 */
	protected static array $properties = [];

	/**
	 * @var array An array of cached model properties.
	 */
	protected static array $models = [];

	/**
	 * Constructor.
	 *
	 * @param array|object|string|null $data The model data.
	 */
	public function __construct( $data = [] ) {
		$data = static::prepareData( $data );
		parent::__construct( $data, ArrayObject::ARRAY_AS_PROPS );
	}

	/* -------------------------------------------------------------------------
	 * Public methods
	 * ---------------------------------------------------------------------- */

	/**
	 * Gets the model's id value.
	 *
	 * @return int|string|null The model id or null.
	 */
	public function id() {
		return $this[ static::idProperty() ] ?? null;
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

		// Get a compact data array stripped of all default values.
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
	 * Saves the model to a file as json.
	 *
	 * @param string $file The file to save the model to.
	 * @param int $flags Save flags, see file_put_contents() for details.
	 *
	 * @return static|null The saved model instance on success or null on failure.
	 */
	public function save( string $file, int $flags = 0 ): ?self {
		if ( file_put_contents( $file, $this, $flags ) ) {
			return $this;
		}
		return null;
	}

	/**
	 * Patches a model with the given data.
	 *
	 * @param array|object|string|null $data The data to be merged into the model.
	 *
	 * @return static The updated model instance.
	 */
	public function patch( $data ): self {
		$existing = $this->getArrayCopy();

		foreach ( static::prepareData( $data, false ) as $id => $value ) {
			$existing[ $id ] = $value;
		}

		return $this->replace( $existing );
	}

	/**
	 * Replaces the model data with given data.
	 *
	 * @param array|object|string|null $data The data to be inserted into the model.
	 *
	 * @return static The updated model instance.
	 */
	public function replace( $data ): self {
		$this->exchangeArray( $data );
		return $this;
	}

	/**
	 * Validates the model values against its property definitions.
	 *
	 * @param bool $throwException Whether to throw an exception on validation errors or not.
	 *
	 * @return static|null The validated model instance or null if the validation fails.
	 */
	public function validate( bool $throwException = false ): ?self {
		foreach ( static::properties() as $id => $property ) {
			$value = $this[ $id ];

			try {
				if ( is_null( $value ) ) {
					if ( $property[ PropertyItem::REQUIRED ] ?? false ) {
						static::validateRequired( $id, $property );
					}
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

				// Allow for custom validation in subclasses.
				static::validateProperty( $value, $id, $property );

			} catch ( ModelException $e ) {
				if ( $throwException ) {
					throw $e;
				}
				return null;
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
	 * @param array|object|string|null $data The model data.
	 *
	 * @return static A model instance.
	 */
	public static function create( $data = [] ): self {
		return new static( $data );
	}

	/**
	 * Loads a model from a json file.
	 *
	 * @param string $file The full path to a json file.
	 * @param bool $throwException Whether to throw an exception on error or not.
	 *
	 * @return static|null A model instance.
	 * @throws ModelException
	 */
	public static function load( string $file, bool $throwException = false ): ?self {
		if ( $file && is_readable( $file ) ) {
			$flags   = $throwException ? JSON_THROW_ON_ERROR : 0;
			$content = file_get_contents( $file );
			$content = json_decode( $content, true, 512, $flags );
			return new static( $content );
		}

		if ( $throwException ) {
			$error = sprintf( 'The file %s is not found or is not readable in %s', $file, static::class );
			throw new ModelException( $error, 500 );
		}

		return null;
	}

	/**
	 * Gets the model's properties.
	 *
	 * @return array[] An array of property definitions.
	 */
	public static function properties(): array {
		$properties = static::$models[ static::class ] ?? null;

		// Return cached properties if available.
		if ( isset( $properties ) ) {
			return $properties;
		}

		// Inherit parent properties.
		if ( $parent = get_parent_class( static::class ) ) {
			if ( is_a( $parent, ModelInterface::class, true ) ) {
				$properties = static::$properties + $parent::properties();
			}
		}

		return static::$models[ static::class ] = $properties ?? static::$properties;
	}

	/**
	 * Gets the model's id property.
	 *
	 * @return string The model's id property.
	 */
	public static function idProperty(): string {
		return static::$idProperty;
	}

	/**
	 * Gets the model property matching the given id.
	 *
	 * @param string $id The property id.
	 *
	 * @return array|null The property array matching the id or null if not existing.
	 */
	public static function getProperty( string $id ): ?array {
		if ( $id ) {
			return static::properties()[ $id ] ?? null;
		}
		return null;
	}

	/**
	 * Adds a new or overrides an existing model property.
	 *
	 * @param Property|array $property A custom property.
	 */
	public static function setProperty( $property ): void {
		static::$properties[ $property['id'] ] = $property;
		unset( static::$models[ static::class ] );
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
	 * Checks if a property exists and is not null.
	 *
	 * @param mixed $key The property id.
	 *
	 * @return bool True if the property exists and is not null.
	 */
	public function offsetExists( $key ): bool {
		return parent::offsetExists( $key ) && $this->offsetGet( $key ) !== null;
	}

	/**
	 * Assign a value to a model property.
	 *
	 * @param mixed $key The property id.
	 * @param mixed $value The property value.
	 */
	public function offsetSet( $key, $value ): void {
		$properties = static::properties();

		if ( $properties ) {
			$writable = $properties[ $key ][ PropertyItem::WRITABLE ] ?? true;
			$mutable  = $properties[ $key ][ PropertyItem::MUTABLE ] ?? true;

			// Throw an exception if no property id is given.
			if ( empty( $key ) ) {
				$error = sprintf( 'Setting a value without a property id is not allowed in %s.', static::class );
				throw new ModelException( $error, 400 );
			}

			// Throw an exception if the property doesn't exist in this model.
			if ( empty( array_key_exists( $key, $properties ) ) ) {
				$error = sprintf( 'Property %s not found in %s.', $key, static::class );
				throw new ModelException( $error, 400 );
			}

			// Throw an exception for read-only properties.
			if ( empty( $writable ) ) {
				$name  = $properties[ $key ][ PropertyItem::NAME ];
				$error = sprintf( 'Setting "%s" (%s) in %s is not allowed.', $key, $name, static::class );
				throw new ModelException( $error, 400 );
			}

			// Don't update immutable properties if they are already set (not null).
			if ( empty( $mutable ) && isset( $this[ $key ] ) ) {
				return;
			}
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
	 * @param array|object|string|null $array The new model data.
	 *
	 * @return array The old model data.
	 */
	public function exchangeArray( $array ): array {
		return parent::exchangeArray( static::prepareData( $array ) );
	}

	/* -------------------------------------------------------------------------
	 * Protected static methods
	 * ---------------------------------------------------------------------- */

	/**
	 * Prepare data before inserting it into the model.
	 *
	 * @param array|object|string|null $data The data to populate the model with.
	 * @param bool $include Whether to include default values in the result or not.
	 *
	 * @return array The data prepared for the model.
	 */
	protected static function prepareData( $data, bool $include = true ): array {
		$properties = static::properties();
		$result     = [];

		// Convert null values.
		if ( is_null( $data ) ) {
			$data = [];
		}

		// Decode a json string.
		if ( is_string( $data ) ) {
			$data = json_decode( $data, true ) ?: [];
		}

		// Convert objects to array unless they support array access.
		if ( is_object( $data ) && empty( $data instanceof ArrayAccess ) ) {
			$data = get_object_vars( $data );
		}

		// If no properties are defined, accept all data;
		if ( empty( $properties ) ) {
			return $data;
		}

		if ( is_array( $data ) || $data instanceof ArrayAccess ) {
			foreach ( $properties as $id => $property ) {
				if ( $include || Utils::keyExists( $id, $data ) ) {
					$value         = $data[ $id ] ?? $property[ PropertyItem::DEFAULT ] ?? null;
					$result[ $id ] = static::prepareProperty( $value, $property );
				}
			}
		}

		return $result;
	}

	/**
	 * Prepare a property value before inserting it into the model.
	 *
	 * @param mixed $value The property value.
	 * @param Property|array $property The property definition.
	 *
	 * @return mixed The prepared property value.
	 */
	protected static function prepareProperty( $value, $property ) {
		$type = $property[ PropertyItem::TYPE ] ?? null;

		if ( PropertyType::UUID === $type && true === $value ) {
			return Utils::uuid();
		}

		if ( $model = $property[ PropertyItem::MODEL ] ?? null ) {
			if ( PropertyType::OBJECT === $type ) {
				if ( isset( $value ) && empty( $value instanceof $model ) ) {
					if ( is_string( $value ) && is_array( $temp = json_decode( $value, true ) ) ) {
						$value = $temp;
					}
					if ( is_array( $value ) || is_object( $value ) ) {
						return new $model( $value );
					}
				}
			} elseif ( PropertyType::ARRAY === $type && $value ) {
				if ( is_string( $value ) && is_array( $temp = json_decode( $value ) ) ) {
					$value = $temp;
				}
				if ( is_array( $value ) || $value instanceof Traversable ) {
					return array_map( function( $item ) use ( $model ) {
						return isset( $item ) && empty( $item instanceof $model ) ? new $model( $item ) : $item;
					}, $value );
				}
			}
		} elseif ( PropertyType::OBJECT === $type ) {
			if ( is_array( $value ) ) {
				return (object) $value;
			}
			if ( is_string( $value ) && is_object( $temp = json_decode( $value ) ) ) {
				return $temp;
			}
		} elseif ( PropertyType::ARRAY === $type && $value ) {
			if ( is_string( $value ) && is_array( $temp = json_decode( $value ) ) ) {
				return $temp;
			}
		} elseif ( PropertyType::INTEGER === $type ) {
			if ( is_string( $value ) && is_numeric( $value ) ) {
				return (int) $value;
			}
		} elseif ( PropertyType::FLOAT === $type ) {
			if ( is_string( $value ) && is_numeric( $value ) ) {
				return (float) $value;
			}
		} elseif ( PropertyType::BOOL === $type ) {
			if ( is_string( $value ) && is_numeric( $value ) ) {
				$value = (int) $value;
			}
			if ( is_int( $value ) ) {
				return (bool) $value;
			}
		}

		return $value;
	}

	/**
	 * Get a compact data array stripped of all default values.
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

				if ( isset( $property[ PropertyItem::MODEL ] ) ) {
					if ( $value instanceof ModelInterface ) {
						$value = $value->data( ModelData::COMPACT );
					} elseif ( is_array( $value ) ) {
						foreach ( $value as &$item ) {
							$item = $item->data( ModelData::COMPACT );
						}
					}
				}

				if ( $value !== $default ) {
					$result[ $id ] = $value;
				}
			}
		}

		// Fallback if the model has no properties.
		if ( empty( $properties ) ) {
			foreach ( $data as $id => $value ) {
				if ( $value instanceof ModelInterface ) {
					$value = $value->data( ModelData::COMPACT );
				}
				$result[ $id ] = $value;
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
			if ( $property[ PropertyItem::READABLE ] ?? true ) {
				$property[ PropertyItem::VALUE ] = $data[ $id ];

				if ( isset( $property[ PropertyItem::MODEL ] ) ) {
					if ( $property[ PropertyItem::VALUE ] instanceof ModelInterface ) {
						$property[ PropertyItem::PROPERTIES ] = $property[ PropertyItem::VALUE ]->data( ModelData::PROPERTIES );
					} elseif ( is_array( $property[ PropertyItem::VALUE ] ) ) {
						foreach ( $property[ PropertyItem::VALUE ] as &$item ) {
							$item = $item->data( ModelData::PROPERTIES );
						}
					}
				}
			}

			$result[] = Property::create( $property );
		}

		// Fallback if the model has no properties.
		if ( empty( $properties ) ) {
			foreach ( $data as $id => $value ) {
				if ( $value instanceof ModelInterface ) {
					$value = $value->data( ModelData::PROPERTIES );
				}
				$result[] = Property::create( [ 'id' => $id, 'name' => $id, 'value' => $value ] );
			}
		}

		return $result ?? [];
	}

	/**
	 * Checks if a property is required.
	 *
	 * @param string $id The property id.
	 * @param Property|array $property The property definition.
	 */
	protected static function validateRequired( string $id, $property ): void {
		$name  = $property[ PropertyItem::NAME ];
		$error = sprintf( 'The property %s (%s) is required in %s', $id, $name, static::class );
		throw new ModelException( $error, 400 );
	}

	/**
	 * Checks that the value is of the correct type.
	 *
	 * @param mixed $value The property value to validate.
	 * @param string $type The property type.
	 * @param Property|array $property The property definition.
	 */
	protected static function validateType( $value, string $type, $property ): void {

		// Check for float or integer values.
		if ( PropertyType::NUMBER === $type ) {
			if ( empty( is_integer( $value ) || is_float( $value ) ) ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must be a %s, found %s in %s', $name, $value, $type, static::class );
				throw new ModelException( $error, 400 );
			}
		} // Check callable functions.
		elseif ( PropertyType::FUNCTION === $type ) {
			if ( empty( is_callable( $value ) ) ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must be a callable %s, found %s in %s', $name, $type, $value, static::class );
				throw new ModelException( $error, 400 );
			}
		} // Check uuid string.
		elseif ( PropertyType::UUID === $type ) {
			if ( empty( is_string( $value ) && strlen( $value ) === 36 ) ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must be a valid %s string, found %s in %s', $name, $type, $value, static::class );
				throw new ModelException( $error, 400 );
			}
		} // Check url.
		elseif ( PropertyType::URL === $type ) {
			if ( empty( is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ) ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must be a valid %s, found %s in %s', $name, $type, $value, static::class );
				throw new ModelException( $error, 400 );
			}
		} // Check email address.
		elseif ( PropertyType::EMAIL === $type ) {
			if ( empty( is_string( $value ) && filter_var( $value, FILTER_VALIDATE_EMAIL ) ) ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must be a valid %s address, found %s in %s', $name, $type, $value, static::class );
				throw new ModelException( $error, 400 );
			}
		} // Check date/time strings.
		elseif ( in_array( $type, [ PropertyType::DATETIME, PropertyType::DATE, PropertyType::TIME ] ) ) {
			if ( is_string( $value ) && empty( static::validateDateTime( $value, $type ) ) ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must be a valid %s string, found %s in %s', $name, $type, $value, static::class );
				throw new ModelException( $error, 400 );
			}
		} // Check all other types.
		elseif ( $type !== gettype( $value ) ) {
			$name  = $property[ PropertyItem::NAME ];
			$error = sprintf( '%s must be of type %s, found %s in %s', $name, $type, var_export( $value, true ), static::class );
			throw new ModelException( $error, 400 );
		}
	}

	/**
	 * Validates a model.
	 *
	 * @param mixed $value The property value to validate.
	 * @param string $class The model class.
	 * @param Property|array $property The property definition.
	 */
	protected static function validateModel( $value, string $class, $property ): void {

		// Validate a single model.
		if ( is_object( $value ) ) {
			static::validateClass( $value, $class, $property );
			static::validateClass( $value, ModelInterface::class, $property );
			$value->validate( true );
		} // Validate an array of models.
		elseif ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				static::validateClass( $item, $class, $property );
				static::validateClass( $item, ModelInterface::class, $property );
				$item->validate( true );
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
		} // Validate an array of object.
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
				$error = sprintf( '%s must match the regex pattern %s, found %s in %s', $name, $pattern, $value, static::class );
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
				$error = sprintf( '%s must be one of %s, found %s in %s', $name, join( ', ', $enum ), $value, static::class );
				throw new ModelException( $error, 400 );
			}
		} // Check enumeration constraint on arrays.
		elseif ( is_array( $value ) ) {
			if ( empty( array_intersect( $value, $enum ) === $value ) ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must be one of %s in %s', $name, join( ', ', $enum ), static::class );
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
				$error = sprintf( '%s must be at least %d, found %s in %s', $name, $min, $value, static::class );
				throw new ModelException( $error, 400 );
			}
		} // Check minimum constraint on strings.
		elseif ( is_string( $value ) ) {
			if ( strlen( $value ) < $min ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must contain at least %d characters, found %s in %s', $name, $min, $value, static::class );
				throw new ModelException( $error, 400 );
			}
		} // Check minimum constraint on arrays.
		elseif ( is_array( $value ) ) {
			if ( count( $value ) < $min ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must contain at least %d entries in %s', $name, $min, static::class );
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
				$error = sprintf( '%s must be max %d, found %s in %s', $name, $max, $value, static::class );
				throw new ModelException( $error, 400 );
			}
		} // Check maximum constraint on strings.
		elseif ( is_string( $value ) ) {
			if ( strlen( $value ) > $max ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must contain max %d characters, found %s in %s', $name, $max, $value, static::class );
				throw new ModelException( $error, 400 );
			}
		} // Check maximum constraint on arrays.
		elseif ( is_array( $value ) ) {
			if ( count( $value ) > $max ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s must contain max %d entries in %s', $name, $max, static::class );
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
	protected static function validateClass( object $value, string $class, $property ): void {
		if ( empty( is_a( $value, $class ) ) ) {
			$name  = $property[ PropertyItem::NAME ];
			$error = sprintf( '%s must be an instance of %s, found %s in %s', $name, $class, get_class( $value ), static::class );
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
				return Utils::validateDate( $value ) || Utils::validateDate( $value, 'Y-m-d\TH:i:s\Z' );
			case PropertyType::DATE:
				return Utils::validateDate( $value, 'Y-m-d' );
			case PropertyType::TIME:
				return Utils::validateTime( $value );
		}
		return false;
	}

	/**
	 * Support for custom validation of properties in subclasses.
	 *
	 * @param mixed $value The value to validate.
	 * @param string $id The property id.
	 * @param Property|array $property The property definition.
	 */
	protected static function validateProperty( $value, string $id, $property ): void {}
}
