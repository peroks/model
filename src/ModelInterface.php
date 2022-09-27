<?php namespace Peroks\Model;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Serializable;

/**
 * The model interface.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
interface ModelInterface extends ArrayAccess, IteratorAggregate, Countable, Serializable, JsonSerializable {

	/**
	 * Gets the model's id value.
	 *
	 * @return int|string The model id.
	 */
	public function id();

	/**
	 * Gets an array representing the model's property values.
	 *
	 * @param string $content Specifies the content of the returned data array.
	 *
	 * @return Property[]|array An array of the model data.
	 */
	public function data( string $content = ModelData::FULL ): array;

	/**
	 * Patches a model with the given data.
	 *
	 * @param array|object $data The data to be merged into the model.
	 *
	 * @return static The updated model instance.
	 */
	public function patch( $data ): self;

	/**
	 * Validates the model values against its property definitions.
	 *
	 * @param bool $throwException Whether to throw an exception on validation errors or not.
	 *
	 * @return static|null The validated model instance or null if the validation fails.
	 */
	public function validate( bool $throwException = false ): ?self;

	/**
	 * Creates a new model with data from the given array or object.
	 *
	 * @param array|object $data The model data.
	 *
	 * @return static A model instance.
	 */
	public static function create( $data = [] ): self;

	/**
	 * Gets the model's properties.
	 *
	 * @return array[] An array of property definitions.
	 */
	public static function properties(): array;

	/**
	 * Gets the model's id property.
	 *
	 * @return string The model's id property.
	 */
	public static function idProperty(): string;

	/**
	 * Gets the model property matching the given id.
	 *
	 * @param string $id The property id.
	 *
	 * @return Property|null The property matching the id.
	 */
	public static function getProperty( string $id ): ?Property;

	/**
	 * Adds a new or overrides an existing model property.
	 *
	 * @param Property $property A custom property.
	 */
	public static function setProperty( Property $property ): void;
}
