<?php namespace Peroks\Model;

/**
 * PropertyId constants.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
abstract class PropertyItem {
	const ID         = 'id';
	const NAME       = 'name';
	const DESC       = 'desc';
	const TYPE       = 'type';
	const MODEL      = 'model';
	const PROPERTIES = 'properties';
	const REQUIRED   = 'required';
	const DISABLED   = 'disabled';
	const READABLE   = 'readable';
	const WRITABLE   = 'writable';
	const PATTERN    = 'pattern';
	const ENUM       = 'enum';
	const DEFAULT    = 'default';
	const VALUE      = 'value';
}
