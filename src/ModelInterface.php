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
	 * @return int|string|null The model id or null.
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
	 * Saves the model to a file as json.
	 *
	 * @param string $file The file to save the model to.
	 * @param int $flags Save flags, see file_put_contents() for details.
	 *
	 * @return static|null The saved model instance on success or null on failure.
	 */
	public function save( string $file, int $flags = 0 ): ?self;

	/**
	 * Patches a model with the given data.
	 *
	 * @param array|object|string|null $data The data to be merged into the model.
	 *
	 * @return static The updated model instance.
	 */
	public function patch( $data ): self;

	/**
	 * Replaces the model data with given data.
	 *
	 * @param array|object|string|null $data The data to be inserted into the model.
	 *
	 * @return static The updated model instance.
	 */
	public function replace( $data ): self;

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
	 * @param array|object|string|null $data The model data.
	 *
	 * @return static A model instance.
	 */
	public static function create( $data = [] ): self;

	/**
	 * Loads a model from a json file.
	 *
	 * @param string $file The full path to a json file.
	 * @param bool $throwException Whether to throw an exception on error or not.
	 *
	 * @return static|null A model instance.
	 * @throws ModelException
	 */
	public static function load( string $file, bool $throwException = false ): ?self;

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
	 * @return array|null The property array matching the id or null if not existing.
	 */
	public static function getProperty( string $id ): ?array;

	/**
	 * Adds a new or overrides an existing model property.
	 *
	 * @param Property|array $property A custom property.
	 */
	public static function setProperty( $property ): void;
}
