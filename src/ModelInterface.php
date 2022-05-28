<?php namespace Peroks\Model;

/**
 * The model interface.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
interface ModelInterface {

	/**
	 * Gets the model id.
	 *
	 * @return int|string The model id.
	 */
	public function id();

	/**
	 * Checks if the model has the given property for reading or writing.
	 *
	 * @param string $id A property id.
	 * @param string $context Check if the property is 'readable' or 'writable'.
	 *
	 * @return bool True if the model has the given property, false otherwise.
	 */
	public function has( string $id, string $context = 'readable' ): bool;

	/**
	 * Gets the internal data and changes arrays merged into one.
	 *
	 * @return array An assoc array of the raw model data.
	 */
	public function raw(): array;

	/**
	 * Gets a full array of all the model's property values.
	 *
	 * @return array An assoc array of the full model data.
	 */
	public function data(): array;

	/**
	 * Merges the data of the given model into this one.
	 *
	 * @param ModelInterface $model The model to get data from.
	 *
	 * @return ModelInterface The merges model.
	 */
	public function merge( ModelInterface $model ): ModelInterface;

	/**
	 * Gets the model's property definitions with values.
	 *
	 * The result of this method can be used to populate input forms for
	 * user interfaces.
	 *
	 * @return Property[] An array of property definitions with values.
	 */
	public function form(): array;

	/**
	 * Validates the model values against its property definitions.
	 *
	 * @return static The validated model instance.
	 */
	public function validate(): self;

	/**
	 * Creates a new model.
	 *
	 * @param array $data The model data.
	 *
	 * @return static A model instance.
	 */
	public static function create( array $data = [] ): self;

	/**
	 * Gets the model's id property.
	 *
	 * @return string The model's id property.
	 */
	public static function primary(): string;
}
