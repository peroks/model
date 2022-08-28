<?php namespace Peroks\Model;

/**
 * Utils and helpers for models.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
class Utils {

	/* -------------------------------------------------------------------------
	 * Magic functions
	 * ---------------------------------------------------------------------- */

	/**
	 * Converts models and objects to arrays.
	 *
	 * @param ModelInterface|object|array $data Model data.
	 *
	 * @return array
	 */
	public static function &toArray( $data ): array {
		if ( $data instanceof ModelInterface ) {
			return $data->getReference();
		}

		if ( is_array( $data ) || ( $data instanceof \Traversable ) ) {
			foreach ( $data as &$value ) {
				if ( is_object( $value ) || is_array( $value ) ) {
					$value = static::toArray( $value );
				}
			}
			return $data;
		}

		if ( $data instanceof \stdClass ) {
			$data = get_object_vars( $data );

			foreach ( $data as &$value ) {
				if ( is_object( $value ) || is_array( $value ) ) {
					$value = static::toArray( $value );
				}
			}
		}


		return $data;
	}
}
