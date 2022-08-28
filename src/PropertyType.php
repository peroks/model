<?php namespace Peroks\Model;

/**
 * PropertyType constants.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
abstract class PropertyType {
	const ANY     = '';
	const BOOL    = 'boolean';
	const INTEGER = 'integer';
	const FLOAT   = 'double';
	const STRING  = 'string';
	const ARRAY   = 'array';
	const OBJECT  = 'object';
}