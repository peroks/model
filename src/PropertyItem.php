<?php namespace Peroks\Model;

/**
 * PropertyId constants.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
abstract class PropertyItem {
	const ID          = 'id';
	const NAME        = 'name';
	const DESC        = 'desc';
	const TYPE        = 'type';
	const MODEL       = 'model';
	const OBJECT      = 'object';
	const FOREIGN     = 'foreign';
	const DEFAULT     = 'default';
	const REQUIRED    = 'required';
	const DISABLED    = 'disabled';
	const READABLE    = 'readable';
	const WRITABLE    = 'writable';
	const UNIQUE      = 'unique';
	const PATTERN     = 'pattern';
	const ENUMERATION = 'enumeration';
	const MIN         = 'min';
	const MAX         = 'max';
	const VALUE       = 'value';
	const PROPERTIES  = 'properties';
}
