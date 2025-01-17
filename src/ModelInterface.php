<?php
/**
 * The model interface.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */

declare( strict_types = 1 );
namespace Peroks\Model;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonException;
use JsonSerializable;

/**
 * The model interface.
 */
interface ModelInterface extends ArrayAccess, IteratorAggregate, Countable, JsonSerializable {

	/**
	 * Gets the model's id value.
	 *
	 * @return int|string|null The model id or null.
	 */
	public function id(): int|string|null;

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
	public function save( string $file, int $flags = 0 ): static|null;

	/**
	 * Patches a model with the given data.
	 *
	 * @param array|object|string|null $data The data to be merged into the model.
	 *
	 * @return static The updated model instance.
	 */
	public function patch( mixed $data ): static;

	/**
	 * Replaces the model data with given data.
	 *
	 * @param array|object|string|null $data The data to be inserted into the model.
	 *
	 * @return static The updated model instance.
	 */
	public function replace( mixed $data ): static;

	/**
	 * Validates the model values against its property definitions.
	 *
	 * @param bool $throwException Whether to throw an exception on validation errors or not.
	 *
	 * @return static|null The validated model instance or null if the validation fails.
	 * @throws ModelException
	 */
	public function validate( bool $throwException = false ): static|null;

	/**
	 * Creates a new model with data from the given array or object.
	 *
	 * @param array|object|string|null $data The model data.
	 *
	 * @return static A model instance.
	 */
	public static function create( mixed $data = [] ): static;

	/**
	 * Loads a model from a json file.
	 *
	 * @param string $path The full path to a json file.
	 * @param bool $exception Whether to throw an exception on error or not.
	 * @param int $traverse Number of directories to traverse up to find the file.
	 *
	 * @return static|null A model instance.
	 * @throws ModelException
	 * @throws JsonException
	 */
	public static function load( string $path, bool $exception = false, int $traverse = 0 ): static|null;

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
	public static function getProperty( string $id ): array|null;

	/**
	 * Adds a new or overrides an existing model property.
	 *
	 * @param Property|array $property A custom property.
	 */
	public static function setProperty( Property|array $property ): void;
}
