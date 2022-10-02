<?php namespace Peroks\Model;

/**
 * PropertyType constants.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
abstract class PropertyType {
	const MIXED    = '';
	const BOOL     = 'boolean';
	const NUMBER   = 'number';
	const INTEGER  = 'integer';
	const FLOAT    = 'double';
	const STRING   = 'string';
	const UUID     = 'uuid';
	const DATETIME = 'datetime';
	const DATE     = 'date';
	const TIME     = 'time';
	const ARRAY    = 'array';
	const OBJECT   = 'object';
	const FUNCTION = 'function';
}
