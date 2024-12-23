<?php
/**
 * Utility and helper class.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */

declare( strict_types = 1 );
namespace Peroks\Model;

use ArrayAccess;
use DateTime;
use DateTimeInterface;

/**
 * Utility and helper class.
 */
class Utils {

	/**
	 * Generates an RFC 4122 compliant Version 4 UUIDs.
	 *
	 * @see https://www.uuidgenerator.net/dev-corner/php
	 *
	 * @return string
	 */
	public static function uuid(): string {
		// Generate 16 bytes (128 bits) of random data.
		$data = random_bytes( 16 );

		// Set version to 0100.
		$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 );

		// Set bits 6-7 to 10.
		$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 );

		// Output the 36 character UUID.
		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}

	/**
	 * Encode data to a json string.
	 *
	 * @param mixed $data The data to encode.
	 *
	 * @return string The encoded json string.
	 */
	public static function encode( mixed $data ): string {
		$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
		return json_encode( $data, $flags );
	}

	/**
	 * Groups an array by the index field.
	 *
	 * @param array $array The array to group.
	 * @param string $index The key to group the array by.
	 * @param string $column The column name to use in the grouped array.
	 */
	public static function group( array $array, string $index, string $column = '' ): array {
		$result = [];

		foreach ( $array as $item ) {
			if ( is_scalar( $key = $item[ $index ] ?? null ) ) {
				if ( empty( $column ) ) {
					$result[ $key ][] = $item;
				} elseif ( array_key_exists( $column, $item ) ) {
					$result[ $key ][] = $item[ $column ];
				}
			}
		}

		return $result;
	}

	/**
	 * Checks if the value is a valid date string.
	 *
	 * @param string $value The date string to validate.
	 * @param string $format The date format to validate against.
	 *
	 * @return bool True if the value is a valid date string, false otherwise.
	 */
	public static function validateDate( string $value, string $format = DateTimeInterface::ATOM ): bool {
		$date = DateTime::createFromFormat( $format, $value );
		return $date && $date->format( $format ) === $value;
	}

	/**
	 * Checks if the value is a valid time string.
	 *
	 * @param string $value The time string to validate.
	 *
	 * @return bool True, if the value is a valid time string, false otherwise.
	 */
	public static function validateTime( string $value ): bool {
		if ( preg_match( '/^(\\d\\d):(\\d\\d)(?::(\\d\\d))?$/', $value, $matches ) ) {
			array_shift( $matches );

			$matches = array_replace( [ 0, 0, 0 ], $matches );
			$matches = array_map( 'intval', $matches );

			return static::inRange( $matches[0], 0, 23 )
				&& static::inRange( $matches[1], 0, 59 )
				&& static::inRange( $matches[2], 0, 59 );
		}

		return false;
	}

	/**
	 * Checks if a value is within the given range.
	 *
	 * @param int $value The value to validate.
	 * @param int $min The minimum value.
	 * @param int $max The maximum value.
	 *
	 * @return bool True if the value is within the given range, false otherwise.
	 */
	public static function inRange( int $value, int $min, int $max ): bool {
		return ( $min <= $value && $value <= $max );
	}

	/**
	 * Checks if the given key or index exists in the data.
	 *
	 * @param int|string $key The key to check for.
	 * @param ArrayAccess|array $data An array or object keys to check.
	 *
	 * @return bool True if the key exists in the data.
	 */
	public static function keyExists( int|string $key, ArrayAccess|array $data ): bool {
		if ( is_array( $data ) ) {
			return array_key_exists( $key, $data );
		}
		if ( $data instanceof ArrayAccess ) {
			return $data->offsetExists( $key );
		}
		return false;
	}

	/**
	 * Checks if the given object or class name is a model.
	 *
	 * @param mixed $model The object or class name to check.
	 */
	public static function isModel( mixed $model ): bool {
		if ( $model ) {
			if ( is_object( $model ) ) {
				return $model instanceof ModelInterface;
			}
			if ( is_string( $model ) ) {
				return is_a( $model, ModelInterface::class, true );
			}
		}
		return false;
	}
}
