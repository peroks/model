<?php namespace Peroks\Model;

/**
 * The property properties.
 *
 * @property string $id The property id.
 * @property string $name The property name.
 * @property string $desc The property description.
 * @property string $type The property type (defaults to ANY).
 * @property string $model The class name of a model.
 * @property string $object The class or interface name to validate an object against.
 * @property array $properties A model, object or array property definition.
 * @property bool $required Whether the property is required or not, defaults to false.
 * @property bool $disabled Whether the property is disabled or not, defaults to false.
 * @property bool $readable Whether the property is readable or not, defaults to true.
 * @property bool $writeable Whether the property is writable or not, defaults to false.
 * @property string $pattern A regex pattern to validate the property value against.
 * @property array $enum An array containing all allowed values.
 * @property mixed $default The property default value.
 * @property mixed value The property value.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
class Property extends Model {

	/**
	 * @var array An array of model properties.
	 */
	protected static array $properties = [
		'id'         => [
			PropertyItem::ID       => 'id',
			PropertyItem::NAME     => 'Property id',
			PropertyItem::DESC     => 'The property id',
			PropertyItem::TYPE     => PropertyType::STRING,
			PropertyItem::REQUIRED => true,
		],
		'name'       => [
			PropertyItem::ID       => 'name',
			PropertyItem::NAME     => 'Property name',
			PropertyItem::DESC     => 'The property name',
			PropertyItem::TYPE     => PropertyType::STRING,
			PropertyItem::REQUIRED => true,
		],
		'desc'       => [
			PropertyItem::ID   => 'desc',
			PropertyItem::NAME => 'Property description',
			PropertyItem::DESC => 'The property description',
			PropertyItem::TYPE => PropertyType::STRING,
		],
		'type'       => [
			PropertyItem::ID      => 'type',
			PropertyItem::NAME    => 'Property type',
			PropertyItem::DESC    => 'The property type: boolean, integer, double, string, array, object',
			PropertyItem::TYPE    => PropertyType::STRING,
			PropertyItem::DEFAULT => PropertyType::MIXED,
			PropertyItem::ENUM    => [
				PropertyType::MIXED,
				PropertyType::BOOL,
				PropertyType::INTEGER,
				PropertyType::FLOAT,
				PropertyType::STRING,
				PropertyType::ARRAY,
				PropertyType::OBJECT,
			],
		],
		'model'      => [
			PropertyItem::ID   => 'model',
			PropertyItem::NAME => 'Model class name',
			PropertyItem::DESC => 'The class name of a model',
			PropertyItem::TYPE => PropertyType::STRING,
		],
		'object'     => [
			PropertyItem::ID   => 'object',
			PropertyItem::NAME => 'Object class name',
			PropertyItem::DESC => 'The class or interface name to validate an object against',
			PropertyItem::TYPE => PropertyType::STRING,
		],
		'foreign'    => [
			PropertyItem::ID   => 'foreign',
			PropertyItem::NAME => 'Foreign model',
			PropertyItem::DESC => 'A reference to another model',
			PropertyItem::TYPE => PropertyType::STRING,
		],
		'properties' => [
			PropertyItem::ID   => 'properties',
			PropertyItem::NAME => 'Properties',
			PropertyItem::DESC => 'A model, object or array property definition',
			PropertyItem::TYPE => PropertyType::ARRAY,
		],
		'required'   => [
			PropertyItem::ID      => 'required',
			PropertyItem::NAME    => 'Required',
			PropertyItem::DESC    => 'Whether the property is required or not, defaults to false.',
			PropertyItem::TYPE    => PropertyType::BOOL,
			PropertyItem::DEFAULT => false,
		],
		'disabled'   => [
			PropertyItem::ID      => 'disabled',
			PropertyItem::NAME    => 'Disabled',
			PropertyItem::DESC    => 'Whether the property is disabled or not, defaults to false',
			PropertyItem::TYPE    => PropertyType::BOOL,
			PropertyItem::DEFAULT => false,
		],
		'readable'   => [
			PropertyItem::ID      => 'readable',
			PropertyItem::NAME    => 'Readable',
			PropertyItem::DESC    => 'Whether the property is readable or not, defaults to true',
			PropertyItem::TYPE    => PropertyType::BOOL,
			PropertyItem::DEFAULT => true,
		],
		'writable'   => [
			PropertyItem::ID      => 'writable',
			PropertyItem::NAME    => 'Writable',
			PropertyItem::DESC    => 'Whether the property is writable or not, defaults to true',
			PropertyItem::TYPE    => PropertyType::BOOL,
			PropertyItem::DEFAULT => true,
		],
		'unique'     => [
			PropertyItem::ID      => 'unique',
			PropertyItem::NAME    => 'Unique',
			PropertyItem::DESC    => 'Whether the property value is unique or not, defaults to false',
			PropertyItem::TYPE    => PropertyType::BOOL,
			PropertyItem::DEFAULT => false,
		],
		'pattern'    => [
			PropertyItem::ID   => 'pattern',
			PropertyItem::NAME => 'Regex validation',
			PropertyItem::DESC => 'A regex pattern to validate the property value against',
			PropertyItem::TYPE => PropertyType::STRING,
		],
		'enum'       => [
			PropertyItem::ID   => 'enum',
			PropertyItem::NAME => 'Enumeration',
			PropertyItem::DESC => 'An array containing all allowed values',
			PropertyItem::TYPE => PropertyType::ARRAY,
		],
		'default'    => [
			PropertyItem::ID   => 'default',
			PropertyItem::NAME => 'Default value',
			PropertyItem::DESC => 'The property default value',
			PropertyItem::TYPE => PropertyType::MIXED,
		],
		'value'      => [
			PropertyItem::ID   => 'value',
			PropertyItem::NAME => 'Property value',
			PropertyItem::DESC => 'The property value',
			PropertyItem::TYPE => PropertyType::MIXED,
		],
	];
}
