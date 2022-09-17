<?php namespace Peroks\Model;

use ArrayObject;

/**
 * The model base class.
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
	 * @param ModelInterface|object|array $data The model data.
	 */
	public function __construct( $data = [] ) {
		$data  = static::normalize( $data );
		$flags = ArrayObject::STD_PROP_LIST | ArrayObject::ARRAY_AS_PROPS;
		parent::__construct( $data, $flags, 'ArrayIterator' );
	}

	/* -------------------------------------------------------------------------
	 * Public methods
	 * ---------------------------------------------------------------------- */

	/**
	 * Gets the model id.
	 *
	 * @return int|string The model id.
	 */
	public function id() {
		return $this[ static::idProperty() ] ?? '';
	}

	/**
	 * Gets an array representing the model's property values.
	 *
	 * @param string $format Specifies the format of the returned data array.
	 *
	 * @return Property[]|array An array of the model data.
	 */
	public function data( string $format = '' ): array {
		$data = $this->getArrayCopy();

		// Get a compact data array stripped of all null and default values.
		if ( ModelData::COMPACT === $format ) {
			foreach ( static::properties() as $id => $property ) {
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

		// Get values for all properties.
		if ( ModelData::FULL === $format ) {
			return array_intersect_key( $data, static::properties() );
		}

		// Gets the internal data.
		return $data;
	}

	/**
	 * Patches a model with the given data array.
	 *
	 * @param array $data The data to merge into the model.
	 *
	 * @return static The updated model instance.
	 */
	public function patch( array $data ): self {
		foreach ( static::normalize( $data ) as $id => $value ) {
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
			$name  = $property[ PropertyItem::NAME ];
			$type  = $property[ PropertyItem::TYPE ] ?? PropertyType::ANY;

			// Check that all required properties are set.
			if ( is_null( $value ) ) {
				if ( $property[ PropertyItem::REQUIRED ] ?? false ) {
					$error = sprintf( '%s is required', $name );
					throw new ModelException( $error, 400 );
				}
				continue;
			}

			// Check type constraint.
			if ( $type && $type !== gettype( $value ) ) {
				$error = sprintf( '%s must be a %s', $name, $type );
				throw new ModelException( $error, 400 );
			}

			// Validate models.
			if ( $model = $property[ PropertyItem::MODEL ] ?? null ) {

				// Validate a single model instance.
				if ( PropertyType::OBJECT === $type ) {
					if ( empty( is_a( $value, $model ) && $value instanceof ModelInterface ) ) {
						$error = sprintf( '%s must be an instance of %s', $name, $model );
						throw new ModelException( $error, 400 );
					}
					$value->validate();
					continue;
				}

				// Validate an array of model instances.
				if ( PropertyType::ARRAY === $type ) {
					foreach ( $value as $instance ) {
						if ( empty( is_a( $instance, $model ) && $instance instanceof ModelInterface ) ) {
							$error = sprintf( '%s must be an array of %s instances', $name, $model );
							throw new ModelException( $error, 400 );
						}
						$instance->validate();
					}
					continue;
				}
			}

			// Check enumeration constraint.
			if ( $enum = $property[ PropertyItem::ENUM ] ?? [] ) {
				if ( is_scalar( $value ) && empty( in_array( $value, $enum, true ) ) ) {
					$error = sprintf( '%s must be one of %s', $name, join( ', ', $enum ) );
					throw new ModelException( $error, 400 );
				}
				if ( is_array( $value ) && empty( array_intersect( $value, $enum ) === $value ) ) {
					$error = sprintf( '%s must be one of %s', $name, join( ', ', $enum ) );
					throw new ModelException( $error, 400 );
				}
			}

			// Check regex pattern constraint.
			if ( isset( $property[ PropertyItem::PATTERN ] ) ) {
				$pattern = '/' . str_replace( '/', '\\/', $property[ PropertyItem::PATTERN ] ) . '/';

				if ( empty( preg_match( $pattern, $value ) ) ) {
					$error = sprintf( '%s must match the regex pattern %s', $name, $property['pattern'] );
					throw new ModelException( $error, 400 );
				}
			}
		}

		return $this;
	}

	/* -------------------------------------------------------------------------
	 * Public static methods
	 * ---------------------------------------------------------------------- */

	/**
	 * Creates a new model with data from the given model or array.
	 *
	 * @param ModelInterface|array $data The model data.
	 *
	 * @return static A model instance.
	 */
	public static function create( $data = [] ): self {
		return new static( $data );
	}

	/**
	 * Gets the model's property definitions.
	 *
	 * @param string $id The property id.
	 *
	 * @return array An array of property definitions or the given property definition.
	 */
	public static function properties( string $id = '' ): array {
		return $id ? static::$properties[ $id ] : static::$properties;
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
	 * Adds a custom property to a model.
	 *
	 * @param Property $property The custom property.
	 */
	public static function addProperty( Property $property ): void {
		$property->validate();
		static::$properties[ $property->id() ] = $property->data();
	}

	/* -------------------------------------------------------------------------
	 * Protected static methods
	 * ---------------------------------------------------------------------- */

	/**
	 * Normalize data.
	 *
	 * @param ModelInterface|object|array $data The model data.
	 */
	protected static function normalize( $data = [] ): array {
		if ( $data instanceof ModelInterface || $data instanceof ArrayObject ) {
			$data = $data->getArrayCopy();
		}

		if ( is_object( $data ) ) {
			$data = get_object_vars( $data );
		}

		if ( is_array( $data ) ) {
			foreach ( static::properties() as $id => $property ) {
				$type  = $property[ PropertyItem::TYPE ] ?? null;
				$model = $property[ PropertyItem::MODEL ] ?? null;

				if ( empty( array_key_exists( $id, $data ) ) ) {
					$data[ $id ] = $property[ PropertyItem::DEFAULT ] ?? null;
				}

				if ( $model && is_array( $data[ $id ] ) ) {
					if ( $type === PropertyType::OBJECT ) {
						$data[ $id ] = new $model( $data[ $id ] );
					} elseif ( $type === PropertyType::ARRAY ) {
						foreach ( $data[ $id ] as &$value ) {
							$value = new $model( $value );
						}
					}
				}
			}
		}

		return $data;
	}

	/* -------------------------------------------------------------------------
	 * Magic functions
	 * ---------------------------------------------------------------------- */

	/**
	 * Gets the model as a json encoded string.
	 */
	public function __toString(): string {
		return json_encode( $this, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
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
}
