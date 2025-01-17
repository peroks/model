<?php
/**
 * PropertyItem constants.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */

declare( strict_types = 1 );
namespace Peroks\Model;

/**
 * PropertyItem constants.
 */
abstract class PropertyItem {
	const ID          = 'id';           // string, The property id (required).
	const NAME        = 'name';         // string, The property name (required).
	const DESC        = 'desc';         // string, The property description (default: null).
	const TYPE        = 'type';         // string, The property type (default: PropertyType::MIXED).
	const MODEL       = 'model';        // string, The class name of a model (default: null).
	const OBJECT      = 'object';       // string, The class or interface name to validate an object against (default: null).
	const FOREIGN     = 'foreign';      // string, The property contains an id of the (foreign) model class name (default: null).
	const MATCH       = 'match';        // string, The property of the (foreign) model to match the own id (default: null).
	const DEFAULT     = 'default';      // mixed, The property default value (default: null).
	const REQUIRED    = 'required';     // bool, Whether the property is required or not (default: false).
	const READABLE    = 'readable';     // bool, Whether the property is readable or not (default: true).
	const WRITABLE    = 'writable';     // bool, Whether the property is writable or not (default: true).
	const MUTABLE     = 'mutable';      // bool, Whether the property is mutable (changeable) or not (default: true).
	const PRIMARY     = 'primary';      // bool, Whether the property is the model's primary key or not (default: false).
	const INDEX       = 'index';        // string, The index name, properties with the same name are combined (default: null).
	const UNIQUE      = 'unique';       // string, The unique index name, properties with the same name are combined (default: null).
	const PATTERN     = 'pattern';      // string, A regex pattern to validate a string value against (default: null).
	const ENUMERATION = 'enumeration';  // array, An enumeration of all valid property values (default: null).
	const MIN         = 'min';          // int|float, The minimum numeric value or string/array length (default: null).
	const MAX         = 'max';          // int|float, The maximum numeric value or string/array length (default: null).
	const VALUE       = 'value';        // mixed, The property value (default: null).
	const PROPERTIES  = 'properties';   // array, An array of model property definitions (default: null).
}
