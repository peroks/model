<?php
/**
 * PropertyType constants.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */

declare( strict_types = 1 );
namespace Peroks\Model;

/**
 * PropertyType constants.
 */
abstract class PropertyType {
	const MIXED    = '';            // Any type, no validation.
	const BOOL     = 'boolean';
	const NUMBER   = 'number';      // Integer or float.
	const INTEGER  = 'integer';
	const FLOAT    = 'double';
	const STRING   = 'string';
	const UUID     = 'uuid';        // A uuid string.
	const URL      = 'url';         // A url.
	const EMAIL    = 'email';       // An email address.
	const DATETIME = 'datetime';    // An ISO 8601 datetime string.
	const DATE     = 'date';        // A date string (Y-m-d).
	const TIME     = 'time';        // A time string (H:i or H:i:s).
	const ARRAY    = 'array';
	const OBJECT   = 'object';
	const FUNCTION = 'function';    // A callable function.
}
