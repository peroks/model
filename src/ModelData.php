<?php namespace Peroks\Model;

/**
 * ModelData constants.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
abstract class ModelData {
	const FULL       = 'fUll'; // Get a full data array with default values for missing properties.
	const COMPACT    = 'compact'; // Get a compact data array stripped of all null and default values.
	const PROPERTIES = 'properties'; // Get an array of the model properties.
}
