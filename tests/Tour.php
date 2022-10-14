<?php

use Peroks\Model\Model;
use Peroks\Model\PropertyType;

/**
 * The tour properties.
 *
 * @property-read string $id The tour id.
 * @property string $name The tour name.
 * @property string $country The tour country.
 * @property string[] $cities The tour cities.
 * @property object $details The tour details.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
class Tour extends Model {

	/**
	 * @var array An array of model properties.
	 */
	protected static array $properties = [
		'id'      => [
			'id'       => 'id',
			'name'     => 'Tour id',
			'desc'     => 'The tour id',
			'type'     => PropertyType::STRING,
			'required' => true,
			'mutable'  => false,
		],
		'name'    => [
			'id'       => 'name',
			'name'     => 'Tour name',
			'desc'     => 'The tour name',
			'type'     => PropertyType::STRING,
			'required' => true,
		],
		'country' => [
			'id'       => 'country',
			'name'     => 'Country',
			'desc'     => 'The tour country',
			'type'     => PropertyType::STRING,
			'required' => true,
		],
		'cities'  => [
			'id'       => 'cities',
			'name'     => 'Cities',
			'desc'     => 'The tour cities',
			'type'     => PropertyType::ARRAY,
			'required' => true,
		],
		'details' => [
			'id'       => 'details',
			'name'     => 'Tour details',
			'desc'     => 'The tour details',
			'type'     => PropertyType::OBJECT,
			'required' => true,
		],
	];
}
