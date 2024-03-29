<?php

use Peroks\Model\PropertyType;

/**
 * The extended tour properties.
 *
 * @property-read string $id The tour id.
 * @property string $name The tour name.
 * @property string $desc The tour description.
 * @property string $country The tour country.
 * @property string[] $cities The tour cities.
 * @property object $details The tour details.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
class ExtendedTour extends Tour {

	/**
	 * @var array An array of model properties.
	 */
	protected static array $properties = [
		'desc' => [
			'id'       => 'desc',
			'name'     => 'Tour description',
			'desc'     => 'The tour description',
			'type'     => PropertyType::STRING,
			'required' => true,
		],
	];
}
