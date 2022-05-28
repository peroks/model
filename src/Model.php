<?php namespace Peroks\Model;

use Exception;
use Iterator;
use JsonSerializable;

/**
 * The abstract model base class.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
abstract class Model implements ModelInterface, Iterator, JsonSerializable {

	/**
	 * An assoc array of the model properties.
	 */
	protected array $data = [];

	/**
	 * An assoc array of changed model properties.
	 */
	protected array $changes = [];

	/**
	 * The current position of the model's iterator.
	 */
	protected int $position = 0;

	/* -------------------------------------------------------------------------
	 * Magic functions
	 * ---------------------------------------------------------------------- */

	/**
	 * Constructor.
	 *
	 * @param array $data The model data as key/value pairs.
	 */
	public function __construct( array $data = [] ) {
		$this->data = static::normalize( $data );
	}

	/**
	 * Magic getter.
	 *
	 * @param string $id The property id.
	 *
	 * @return mixed The property value.
	 */
	public function __get( string $id ) {
		return $this->get( $id );
	}

	/**
	 * Magic setter.
	 *
	 * @param string $id The property id.
	 * @param mixed The property value.
	 */
	public function __set( string $id, $value ): void {
		$this->set( $id, $value );
	}

	/**
	 * Emulates isset().
	 *
	 * @param string $id The property id.
	 *
	 * @return bool True if the property is set, false otherwise.
	 */
	public function __isset( string $id ): bool {
		$value = $this->get( $id );
		return isset( $value );
	}

	/**
	 * Gets the model as a json encoded string.
	 */
	public function __toString(): string {
		return json_encode( $this, JSON_UNESCAPED_UNICODE, JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Prepares the model data for serialization.
	 *
	 * @return array The model's data and current changes.
	 */
	public function __serialize(): array {
		return get_object_vars( $this );
	}

	/**
	 * Restores the model from serialization.
	 *
	 * @param array $restored The previously serialized data.
	 */
	public function __unserialize( array $restored ) {
		$this->data     = $restored['data'];
		$this->changes  = $restored['changes'];
		$this->position = $restored['position'];
	}

	/* -------------------------------------------------------------------------
	 * Internal functions
	 * ---------------------------------------------------------------------- */

	/**
	 * Gets a model property.
	 *
	 * @param string $id The property id.
	 * @param array $property The property definition.
	 *
	 * @return mixed The property value.
	 */
	protected function get( string $id, array $property = [] ) {
		$property = $property ?: static::properties( $id );
		$value    = $this->changes[ $id ] ?? $this->data[ $id ] ?? $property[ Property::DEFAULT ] ?? null;

		// Shortcut non-readable properties and null values.
		if ( empty( $property[ Property::READABLE ] ?? true ) || is_null( $value ) ) {
			return null;
		}

		// Maybe convert arrays to models or objects.
		if ( is_array( $value ) ) {
			$type  = $property[ Property::TYPE ] ?? null;
			$model = $property[ Property::MODEL ] ?? null;

			if ( $model ) {

				// Convert an array to an array of models.
				if ( $value && Property::TYPE_ARRAY === $type ) {
					return array_map( [ $model, 'create' ], $value );
				}

				// Convert an array to a model.
				if ( Property::TYPE_OBJECT === $type ) {
					return $model::create( $value );
				}
			}

			// Convert an array to a standard object.
			if ( Property::TYPE_OBJECT === $type ) {
				return (object) $value;
			}
		}

		return $value;
	}

	/**
	 * Sets a model property.
	 *
	 * @param string $id The property id.
	 * @param $value
	 *
	 * @return bool True if the property was set, false otherwise.
	 */
	protected function set( string $id, $value ): bool {
		if ( $this->has( $id, Property::WRITABLE ) ) {
			$this->changes[ $id ] = $value;
			return true;
		}
		return false;
	}

	/**
	 * Strips surplus properties from the given data.
	 *
	 * @param array $data The given model data.
	 *
	 * @return array The normalized model data.
	 */
	protected static function normalize( array $data ): array {
		return array_intersect_key( $data, static::properties() );
	}

	/**
	 * Gets the model's property definitions.
	 *
	 * @param string $id The property id.
	 *
	 * @return array An array of property definitions or the given property definition.
	 */
	protected static function properties( string $id = '' ): array {
		return [];
	}

	/* -------------------------------------------------------------------------
	 * ModelInterface implementation
	 * ---------------------------------------------------------------------- */

	/**
	 * Gets the model id.
	 *
	 * @return int|string The model id.
	 */
	public function id() {
		return $this->get( static::primary() );
	}

	/**
	 * Checks if the model has the given property for reading or writing.
	 *
	 * @param string $id The property id.
	 * @param string $context Check if the property is 'readable' or 'writable'.
	 *
	 * @return bool True if the model has the given property, false otherwise.
	 */
	public function has( string $id, string $context = Property::READABLE ): bool {
		$properties = static::properties();

		if ( Property::READABLE === $context && array_key_exists( $id, $properties ) ) {
			return $properties[ $id ][ $context ] ?? true;
		}

		if ( Property::WRITABLE === $context ) {
			return $properties[ $id ][ $context ] ?? false;
		}

		return false;
	}

	/**
	 * Gets the internal data and changes arrays merged into one.
	 *
	 * @return array An assoc array of the raw model data.
	 */
	public function raw(): array {
		return array_replace( $this->data, $this->changes );
	}

	/**
	 * Gets a full array of all the model's property values.
	 *
	 * @return array An assoc array of the full model data.
	 */
	public function data(): array {
		foreach ( static::properties() as $id => $property ) {
			$result[ $id ] = $this->changes[ $id ] ?? $this->data[ $id ] ?? $property[ Property::DEFAULT ] ?? null;
		}
		return $result ?? [];
	}

	/**
	 * Merges the data of the given model into this one.
	 *
	 * @param ModelInterface $model The model to get data from.
	 *
	 * @return static The merged model.
	 */
	public function merge( ModelInterface $model ): self {
		$this->changes = array_replace( $this->changes, static::normalize( $model->raw() ) );
		return $this;
	}

	/**
	 * Gets the model's property definitions with values.
	 *
	 * The result of this method can be used to populate input forms for
	 * user interfaces.
	 *
	 * @return Property[] An array of property definitions with values.
	 */
	public function form(): array {
		foreach ( static::properties() as $id => $property ) {
			$property[ Property::VALUE ] = $this->get( $id, $property );

			if ( $property[ Property::VALUE ] instanceof ModelInterface ) {
				$property[ Property::PROPERTIES ] = $property[ Property::VALUE ]->form();
			}
			$result[] = Property::create( $property );
		}
		return $result ?? [];
	}

	/**
	 * Validates the model values against its property definitions.
	 *
	 * @return static The validated model instance.
	 */
	public function validate(): self {
		foreach ( static::properties() as $id => $property ) {
			$value = $this->get( $id, $property );
			$name  = $property[ Property::NAME ];
			$type  = $property[ Property::TYPE ] ?? Property::TYPE_ANY;

			// Check that all required properties are set.
			if ( is_null( $value ) ) {
				if ( $property[ Property::REQUIRED ] ?? false ) {
					$error = sprintf( '%s is required', $name );
					throw new Exception( $error, 400 );
				}
				continue;
			}

			// Check type constraint.
			if ( $type && $type !== gettype( $value ) ) {
				$error = sprintf( '%s must be a %s', $name, $type );
				throw new Exception( $error, 400 );
			}

			// Validate models.
			if ( $model = $property[ Property::MODEL ] ?? null ) {

				// Validate a single model instance.
				if ( Property::TYPE_OBJECT === $type ) {
					if ( empty( is_a( $value, $model ) && $value instanceof ModelInterface ) ) {
						$error = sprintf( '%s must be an instance of %s', $name, $model );
						throw new Exception( $error, 400 );
					}
					$value->validate();
					continue;
				}

				// Validate an array of model instances.
				if ( Property::TYPE_ARRAY === $type ) {
					foreach ( $value as $instance ) {
						if ( empty( is_a( $instance, $model ) && $instance instanceof ModelInterface ) ) {
							$error = sprintf( '%s must be an array of %s instances', $name, $model );
							throw new Exception( $error, 400 );
						}
						$instance->validate();
					}
					continue;
				}
			}

			// Check enumeration constraint.
			if ( $enum = $property[ Property::ENUM ] ?? [] ) {
				if ( is_scalar( $value ) && empty( in_array( $value, $enum, true ) ) ) {
					$error = sprintf( '%s must be one of %s', $name, join( ', ', $enum ) );
					throw new Exception( $error, 400 );
				}
				if ( is_array( $value ) && empty( array_intersect( $value, $enum ) === $value ) ) {
					$error = sprintf( '%s must be one of %s', $name, join( ', ', $enum ) );
					throw new Exception( $error, 400 );
				}
			}

			// Check regex pattern constraint.
			if ( isset( $property[ Property::PATTERN ] ) ) {
				$pattern = '/' . str_replace( '/', '\\/', $property[ Property::PATTERN ] ) . '/';

				if ( empty( preg_match( $pattern, $value ) ) ) {
					$error = sprintf( '%s must match the regex pattern %s', $name, $property['pattern'] );
					throw new Exception( $error, 400 );
				}
			}
		}

		return $this;
	}

	/**
	 * Creates a new model.
	 *
	 * @param array $data The model data.
	 *
	 * @return static A model instance.
	 */
	public static function create( array $data = [] ): self {
		return new static( $data );
	}

	/**
	 * Gets the model's id property.
	 *
	 * @return string The model's id property.
	 */
	public static function primary(): string {
		return 'id';
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
		foreach ( static::properties() as $id => $property ) {
			$result[ $id ] = $this->get( $id, $property );
		}
		return $result ?? [];
	}

	/* -------------------------------------------------------------------------
	 * Iterator implementation
	 * ---------------------------------------------------------------------- */

	/**
	 * Returns the current element.
	 *
	 * @return mixed
	 */
	public function current() {
		$properties = array_values( static::properties() );
		$property   = $properties[ $this->position ];
		return $this->get( $property[ Property::ID ], $properties );
	}

	/**
	 * Returns the key of the current element.
	 *
	 * @return int|string|null
	 */
	public function key() {
		$properties = array_keys( static::properties() );
		return $properties[ $this->position ] ?? null;
	}

	/**
	 * Moves forward to next element
	 */
	public function next(): void {
		$this->position++;
	}

	/**
	 * Rewinds the Iterator to the first element.
	 */
	public function rewind(): void {
		$this->position = 0;
	}

	/**
	 * Checks if current position is valid.
	 */
	public function valid(): bool {
		return is_string( $this->key() );
	}
}
