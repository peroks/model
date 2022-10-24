<?php namespace Peroks\Model;

use ArrayAccess;
use DateTime;

/**
 * Utility and helper class.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
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

		// Set version to 0100
		$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 );

		// Set bits 6-7 to 10
		$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 );

		// Output the 36 character UUID.
		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}

	/**
	 * Checks if the value is a valid date string.
	 *
	 * @param string $value The date string to validate.
	 * @param string $format The date format to validate against.
	 *
	 * @return bool True if the value is a valid date string, false otherwise.
	 */
	public static function validateDate( string $value, string $format = DateTime::ATOM ): bool {
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
		if ( preg_match( '/^(\d\d):(\d\d)(?::(\d\d))?$/', $value, $matches ) ) {
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
	 * @param array|ArrayAccess $data An array or object keys to check.
	 *
	 * @return bool True if the key exists in the data.
	 */
	public static function keyExists( string $key, $data ): bool {
		if ( is_array( $data ) ) {
			return array_key_exists( $key, $data );
		}
		if ( $data instanceof ArrayAccess ) {
			return $data->offsetExists( $key );
		}
		return false;
	}
}
