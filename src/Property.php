<?php namespace Peroks\Model;

/**
 * The property properties.
 *
 * @property-read string $id The property id.
 * @property-read string $name The property name.
 * @property-read string $desc The property description.
 * @property-read string $type The property type (defaults to ANY).
 * @property-read string $model The class name of another model.
 * @property array $properties A model, object or array property definition.
 * @property-read bool $required Whether the property is required or not, defaults to false.
 * @property-read bool $disabled Whether the property is disabled or not, defaults to false.
 * @property-read bool $readable Whether the property is readable or not, defaults to true.
 * @property-read bool $writeable Whether the property is writable or not, defaults to false.
 * @property-read string $pattern A regex pattern to validate the property value against.
 * @property-read array $enum An array containing all allowed values.
 * @property-read mixed $default The property default value.
 * @property mixed value The property value.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
class Property extends Model {

	// Property ids.
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

	// Property types.
	const TYPE_ANY     = '';
	const TYPE_BOOL    = 'boolean';
	const TYPE_INTEGER = 'integer';
	const TYPE_FLOAT   = 'double';
	const TYPE_STRING  = 'string';
	const TYPE_ARRAY   = 'array';
	const TYPE_OBJECT  = 'object';

	/**
	 * Gets the model's property definitions.
	 *
	 * @param string $id A property definition id.
	 *
	 * @return array An array of property definitions or the given property definition.
	 */
	public static function properties( string $id = '' ): array {
		$properties = [
			'id'         => [
				'id'       => 'id',
				'name'     => 'Property id',
				'desc'     => 'The property id',
				'type'     => self::TYPE_STRING,
				'required' => true,
			],
			'name'       => [
				'id'       => 'name',
				'name'     => 'Property name',
				'desc'     => 'The property name',
				'type'     => self::TYPE_STRING,
				'required' => true,
			],
			'desc'       => [
				'id'   => 'desc',
				'name' => 'Property description',
				'desc' => 'The property description',
				'type' => self::TYPE_STRING,
			],
			'type'       => [
				'id'      => 'type',
				'name'    => 'Property type',
				'desc'    => 'The property type: boolean, integer, double, string, array, object',
				'type'    => self::TYPE_STRING,
				'default' => self::TYPE_ANY,
				'enum'    => [
					self::TYPE_ANY,
					self::TYPE_BOOL,
					self::TYPE_INTEGER,
					self::TYPE_FLOAT,
					self::TYPE_STRING,
					self::TYPE_ARRAY,
					self::TYPE_OBJECT,
				],
			],
			'model'      => [
				'id'   => 'model',
				'name' => 'Model class name',
				'desc' => 'The class name of another model',
				'type' => self::TYPE_STRING,
			],
			'properties' => [
				'id'       => 'properties',
				'name'     => 'Properties',
				'desc'     => 'A model, object or array property definition',
				'type'     => self::TYPE_ARRAY,
				'writable' => true,
			],
			'required'   => [
				'id'      => 'required',
				'name'    => 'Required',
				'desc'    => 'Whether the property is required or not, defaults to false.',
				'type'    => self::TYPE_BOOL,
				'default' => false,
			],
			'disabled'   => [
				'id'      => 'disabled',
				'name'    => 'Disabled',
				'desc'    => 'Whether the property is disabled or not, defaults to false',
				'type'    => self::TYPE_BOOL,
				'default' => false,
			],
			'readable'   => [
				'id'      => 'readable',
				'name'    => 'Readable',
				'desc'    => 'Whether the property is readable or not, defaults to true',
				'type'    => self::TYPE_BOOL,
				'default' => true,
			],
			'writable'   => [
				'id'      => 'writable',
				'name'    => 'Writable',
				'desc'    => 'Whether the property is writable or not, defaults to false',
				'type'    => self::TYPE_BOOL,
				'default' => false,
			],
			'pattern'    => [
				'id'   => 'pattern',
				'name' => 'Regex validation',
				'desc' => 'A regex pattern to validate the property value against',
				'type' => self::TYPE_STRING,
			],
			'enum'       => [
				'id'   => 'enum',
				'name' => 'Enumeration',
				'desc' => 'An array containing all allowed values',
				'type' => self::TYPE_ARRAY,
			],
			'default'    => [
				'id'   => 'default',
				'name' => 'Default value',
				'desc' => 'The property default value',
				'type' => self::TYPE_ANY,
			],
			'value'      => [
				'id'       => 'value',
				'name'     => 'Property value',
				'desc'     => 'The property value',
				'type'     => self::TYPE_ANY,
				'writable' => true,
			],
		];

		return $id ? $properties[ $id ] : $properties;
	}
}
