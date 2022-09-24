<?php namespace Peroks\Model;

use ArrayAccess;

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
