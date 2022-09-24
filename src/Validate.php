<?php namespace Peroks\Model;

/**
 * Model validation class.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
class Validate {

	/**
	 * Checks that required properties are set.
	 *
	 * @param mixed $value The property value to validate.
	 * @param Property|array $property The property definition.
	 */
	public static function required( $value, $property ): void {
		if ( $property[ PropertyItem::REQUIRED ] ?? false ) {
			if ( is_null( $value ) ) {
				$name  = $property[ PropertyItem::NAME ];
				$error = sprintf( '%s is required.', $name );
				throw new ModelException( $error, 400 );
			}
		}
	}

	/**
	 * Checks that the value is of the correct type.
	 *
	 * @param mixed $value The property value to validate.
	 * @param Property|array $property The property definition.
	 */
	public static function type( $value, $property ): void {
		if ( $type = $property[ PropertyItem::TYPE ] ?? PropertyType::MIXED ) {

			// Check number type.
			if ( $type === PropertyType::NUMBER ) {
				if ( empty( is_integer( $value ) || is_float( $value ) ) ) {
					$name  = $property[ PropertyItem::NAME ];
					$error = sprintf( '%s must be of type %s.', $name, $type );
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
	}

	/**
	 * Validates a model.
	 *
	 * @param mixed $value The property value to validate.
	 * @param Property|array $property The property definition.
	 */
	public static function model( $value, $property ): void {
		if ( $class = $property[ PropertyItem::MODEL ] ?? null ) {

			// Validate a single model.
			if ( is_object( $value ) ) {
				static::classname( $value, $class, $property );
				static::classname( $value, ModelInterface::class, $property );
				$value->validate();
			}

			// Validate an array of models.
			elseif ( is_array( $value ) ) {
				foreach ( $value as $item ) {
					static::classname( $item, $class, $property );
					static::classname( $item, ModelInterface::class, $property );
					$item->validate();
				}
			}
		}
	}

	/**
	 * Validates an object.
	 *
	 * @param mixed $value The property value to validate.
	 * @param Property|array $property The property definition.
	 */
	public static function object( $value, $property ): void {
		if ( $class = $property[ PropertyItem::OBJECT ] ?? null ) {

			// Validate a single object.
			if ( is_object( $value ) ) {
				static::classname( $value, $class, $property );
			}

			// Validate an array of object.
			elseif ( is_array( $value ) ) {
				foreach ( $value as $item ) {
					static::classname( $item, $class, $property );
				}
			}
		}
	}

	/**
	 * Validates a string against a regex pattern.
	 *
	 * @param mixed $value The property value to validate.
	 * @param Property|array $property The property definition.
	 */
	public static function pattern( $value, $property ): void {
		if ( $pattern = $property[ PropertyItem::PATTERN ] ?? null ) {
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
	}

	/**
	 * Validates a property value against an enumeration constraint.
	 *
	 * @param mixed $value The property value to validate.
	 * @param Property|array $property The property definition.
	 */
	public static function enumeration( $value, $property ): void {
		if ( $enum = $property[ PropertyItem::ENUM ] ?? null ) {

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
	}

	/**
	 * Validates a property value against a minimum constraint.
	 *
	 * @param mixed $value The property value to validate.
	 * @param Property|array $property The property definition.
	 */
	public static function minimum( $value, $property ): void {
		if ( isset( $property[ PropertyItem::MIN ] ) ) {
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
	}

	/**
	 * Validates a property value against a maximum constraint.
	 *
	 * @param mixed $value The property value to validate.
	 * @param Property|array $property The property definition.
	 */
	public static function maximum( $value, $property ): void {
		if ( isset( $property[ PropertyItem::MAX ] ) ) {
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
	}

	/**
	 * Checks that the object is an instance of the given class.
	 *
	 * @param object $value The object to validate.
	 * @param string $class The object class or interface name.
	 * @param Property|array $property The property definition.
	 */
	public static function classname( object $value, string $class, array $property ): void {
		if ( empty( is_a( $value, $class ) ) ) {
			$name  = $property[ PropertyItem::NAME ];
			$error = sprintf( '%s must be an instance of %s', $name, $class );
			throw new ModelException( $error, 400 );
		}
	}
}
