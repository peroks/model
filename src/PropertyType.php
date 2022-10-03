<?php namespace Peroks\Model;

/**
 * PropertyType constants.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
abstract class PropertyType {
	const MIXED    = '';            // Any type, no validation.
	const BOOL     = 'boolean';
	const NUMBER   = 'number';      // Integer or float.
	const INTEGER  = 'integer';
	const FLOAT    = 'double';
	const STRING   = 'string';
	const UUID     = 'uuid';        // A uuid string.
	const DATETIME = 'datetime';    // A ISO 8601 datetime string.
	const DATE     = 'date';        // A date string (Y-m-d).
	const TIME     = 'time';        // A time string (h:n or h:n:s).
	const ARRAY    = 'array';
	const OBJECT   = 'object';
	const FUNCTION = 'function';    // A callable function.
}
