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
interface ModelInterface extends IteratorAggregate, ArrayAccess, Serializable, Countable, JsonSerializable {

	/**
	 * Gets the model id.
	 *
	 * @return int|string The model id.
	 */
	public function id();

	/**
	 * Gets an array representing the model's property values.
	 *
	 * @param string $format Specifies the format of the returned data array.
	 *
	 * @return Property[]|array An array of the model data.
	 */
	public function data( string $format = '' ): array;

	/**
	 * Patches a model with the given data.
	 *
	 * @param ModelInterface|object|array $data The data to merge into the model.
	 *
	 * @return static The updated model instance.
	 */
	public function patch( $data ): self;

	/**
	 * Validates the model values against its property definitions.
	 *
	 * @return static The validated model instance.
	 */
	public function validate(): self;

	/**
	 * Creates a new model with data from the given model or array.
	 *
	 * @param ModelInterface|array $data The model data.
	 *
	 * @return static A model instance.
	 */
	public static function create( $data = [] ): self;

	/**
	 * Gets the model's property definitions.
	 *
	 * @param string $id The property id.
	 *
	 * @return array An array of property definitions or the given property definition.
	 */
	public static function properties( string $id = '' ): array;

	/**
	 * Gets the model's id property.
	 *
	 * @return string The model's id property.
	 */
	public static function idProperty(): string;

	/**
	 * Adds a custom property to a model.
	 *
	 * @param Property $property The custom property.
	 */
	public static function addProperty( Property $property ): void;
}
