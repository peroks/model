<?php namespace Peroks\Model;

/**
 * The property properties.
 *
 * @property string $id The property id (required).
 * @property string $name The property name (required).
 * @property string $desc The property description (default: null).
 * @property string $type The property type (default: PropertyType::MIXED).
 * @property string $model The class name of a model (default: null).
 * @property string $object The class or interface name to validate an object against (default: null).
 * @property string $foreign The property contains an id of the (foreign) model class name (default: null).
 * @property mixed $default The property default value (default: null).
 * @property bool $required Whether the property is required or not (default: false).
 * @property bool $disabled Whether the property is disabled or not (default: false).
 * @property bool $readable Whether the property is readable or not (default: true).
 * @property bool $writeable Whether the property is writable or not (default: true).
 * @property bool $unique Whether the property value is unique or not (default: false).
 * @property string $pattern A regex pattern to validate a string value against (default: null).
 * @property array $enumeration An enumeration of all valid property values (default: null).
 * @property int|float $min The minimum numeric value or string/array length (default: null).
 * @property int|float $max The maximum numeric value or string/array length (default: null).
 * @property mixed $value The property value (default: null).
 * @property array $properties An array of model property definitions (default: null).
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
		'id'          => [
			PropertyItem::ID       => 'id',
			PropertyItem::NAME     => 'Property id',
			PropertyItem::DESC     => 'The property id',
			PropertyItem::TYPE     => PropertyType::STRING,
			PropertyItem::REQUIRED => true,
		],
		'name'        => [
			PropertyItem::ID       => 'name',
			PropertyItem::NAME     => 'Property name',
			PropertyItem::DESC     => 'The property name',
			PropertyItem::TYPE     => PropertyType::STRING,
			PropertyItem::REQUIRED => true,
		],
		'desc'        => [
			PropertyItem::ID   => 'desc',
			PropertyItem::NAME => 'Property description',
			PropertyItem::DESC => 'The property description',
			PropertyItem::TYPE => PropertyType::STRING,
		],
		'type'        => [
			PropertyItem::ID          => 'type',
			PropertyItem::NAME        => 'Property type',
			PropertyItem::DESC        => 'The property type',
			PropertyItem::TYPE        => PropertyType::STRING,
			PropertyItem::DEFAULT     => PropertyType::MIXED,
			PropertyItem::ENUMERATION => [
				PropertyType::MIXED,
				PropertyType::BOOL,
				PropertyType::INTEGER,
				PropertyType::FLOAT,
				PropertyType::STRING,
				PropertyType::ARRAY,
				PropertyType::OBJECT,
			],
		],
		'model'       => [
			PropertyItem::ID   => 'model',
			PropertyItem::NAME => 'Model class name',
			PropertyItem::DESC => 'The class name of a model',
			PropertyItem::TYPE => PropertyType::STRING,
		],
		'object'      => [
			PropertyItem::ID   => 'object',
			PropertyItem::NAME => 'Object class name',
			PropertyItem::DESC => 'The class or interface name to validate an object against',
			PropertyItem::TYPE => PropertyType::STRING,
		],
		'foreign'     => [
			PropertyItem::ID   => 'foreign',
			PropertyItem::NAME => 'Foreign model class name',
			PropertyItem::DESC => 'The property contains an id of the (foreign) model class name',
			PropertyItem::TYPE => PropertyType::STRING,
		],
		'default'     => [
			PropertyItem::ID   => 'default',
			PropertyItem::NAME => 'Default value',
			PropertyItem::DESC => 'The property default value',
			PropertyItem::TYPE => PropertyType::MIXED,
		],
		'required'    => [
			PropertyItem::ID      => 'required',
			PropertyItem::NAME    => 'Required',
			PropertyItem::DESC    => 'Whether the property is required or not',
			PropertyItem::TYPE    => PropertyType::BOOL,
			PropertyItem::DEFAULT => false,
		],
		'disabled'    => [
			PropertyItem::ID      => 'disabled',
			PropertyItem::NAME    => 'Disabled',
			PropertyItem::DESC    => 'Whether the property is disabled or not',
			PropertyItem::TYPE    => PropertyType::BOOL,
			PropertyItem::DEFAULT => false,
		],
		'readable'    => [
			PropertyItem::ID      => 'readable',
			PropertyItem::NAME    => 'Readable',
			PropertyItem::DESC    => 'Whether the property is readable or not',
			PropertyItem::TYPE    => PropertyType::BOOL,
			PropertyItem::DEFAULT => true,
		],
		'writable'    => [
			PropertyItem::ID      => 'writable',
			PropertyItem::NAME    => 'Writable',
			PropertyItem::DESC    => 'Whether the property is writable or not',
			PropertyItem::TYPE    => PropertyType::BOOL,
			PropertyItem::DEFAULT => true,
		],
		'unique'      => [
			PropertyItem::ID      => 'unique',
			PropertyItem::NAME    => 'Unique',
			PropertyItem::DESC    => 'Whether the property value is unique or not',
			PropertyItem::TYPE    => PropertyType::BOOL,
			PropertyItem::DEFAULT => false,
		],
		'pattern'     => [
			PropertyItem::ID   => 'pattern',
			PropertyItem::NAME => 'Regex validation pattern',
			PropertyItem::DESC => 'A regex pattern to validate the property value against',
			PropertyItem::TYPE => PropertyType::STRING,
		],
		'enumeration' => [
			PropertyItem::ID   => 'enumeration',
			PropertyItem::NAME => 'Enumeration',
			PropertyItem::DESC => 'An enumeration of all valid property values',
			PropertyItem::TYPE => PropertyType::ARRAY,
		],
		'min'         => [
			PropertyItem::ID   => 'min',
			PropertyItem::NAME => 'Min value',
			PropertyItem::DESC => 'The minimum value',
			PropertyItem::TYPE => PropertyType::NUMBER,
		],
		'max'         => [
			PropertyItem::ID   => 'max',
			PropertyItem::NAME => 'Max value',
			PropertyItem::DESC => 'The maximum value',
			PropertyItem::TYPE => PropertyType::NUMBER,
		],
		'value'       => [
			PropertyItem::ID   => 'value',
			PropertyItem::NAME => 'Property value',
			PropertyItem::DESC => 'The property value',
			PropertyItem::TYPE => PropertyType::MIXED,
		],
		'properties'  => [
			PropertyItem::ID   => 'properties',
			PropertyItem::NAME => 'Properties',
			PropertyItem::DESC => 'An array of model property definitions',
			PropertyItem::TYPE => PropertyType::ARRAY,
		],
	];
}
