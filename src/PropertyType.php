<?php namespace Peroks\Model;

/**
 * PropertyType constants.
 *
 * @author Per Egil Roksvaag
 * @copyright Silverscreen Tours GmbH
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
