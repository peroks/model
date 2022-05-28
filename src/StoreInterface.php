<?php namespace Peroks\Model;

/**
 * Interface for storing and retrieving models from a data store.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
interface StoreInterface {

	/* -------------------------------------------------------------------------
	 * Constructor and destructor
	 * ---------------------------------------------------------------------- */

	public static function load( $source, $options = [] ): self;

	/* -------------------------------------------------------------------------
	 * Retrieving models.
	 * ---------------------------------------------------------------------- */

	/**
	 * Gets a model from the data store.
	 *
	 * @param string $id The model id.
	 * @param string $class The model class name.
	 * @param bool $create Whether to create a new model if not found.
	 *
	 * @return ModelInterface|null A new or existing model of the given class.
	 */
	public function get( string $id, string $class, bool $create = true ): ?ModelInterface;

	/**
	 * Retrieves a collection of model from the data store.
	 *
	 * @param string[] $ids An array of model ids.
	 * @param string $class The model class name.
	 * @param bool $create Whether to create a new model if not found.
	 *
	 * @return ModelInterface[] An array of new or existing models of the given class.
	 */
	public function collect( array $ids, string $class, bool $create = true ): array;

	/**
	 * Checks if a model with the given id exists in the data store.
	 *
	 * @param string $id The model id.
	 * @param string $class The model class name.
	 *
	 * @return bool True if the model is found in the data store.
	 */
	public function exists( string $id, string $class ): bool;

	/* -------------------------------------------------------------------------
	 * List and filter models.
	 * ---------------------------------------------------------------------- */

	/**
	 * Gets a list of all models of the given class.
	 *
	 * @param string $class The model class name.
	 *
	 * @return ModelInterface[] An assoc array of models keyed by the model ids.
	 */
	public function list( string $class ): array;

	/**
	 * Gets a filtered list of models of the given class.
	 *
	 * @param string $class The model class name.
	 * @param array $filter Properties (key/value pairs) to match the stored models.
	 *
	 * @return ModelInterface[] An assoc array of models keyed by the model ids.
	 */
	public function filter( string $class, array $filter ): array;

	/* -------------------------------------------------------------------------
	 * Creating, updating and deleting instances
	 * ---------------------------------------------------------------------- */

	/**
	 * Saves and validates a model in the data store.
	 *
	 * If a model with the same id and class already exists in the data store,
	 * it will be replaced by the new model. The new model is validated before
	 * saving.
	 *
	 * @param ModelInterface $model The model to store.
	 *
	 * @return string The stored model id.
	 */
	public function set( ModelInterface $model ): string;

	/**
	 * Updates and validated a model in the data store.
	 *
	 * If a model with the same id and class already exists in the data store,
	 * it will be updated (patched) with the new model data. The updated model
	 * is validated before saving.
	 *
	 * @param ModelInterface $model The model to store.
	 *
	 * @return string The stored model id.
	 */
	public function update( ModelInterface $model ): string;

	/**
	 * Deletes a model from the data store.
	 *
	 * @param string $id The model id.
	 * @param string $class The model class name.
	 *
	 * @return bool True if the model existed, false otherwise.
	 */
	public function delete( string $id, string $class ): bool;

	/* -------------------------------------------------------------------------
	 * File handling
	 * ---------------------------------------------------------------------- */

	/**
	 * Imports data from a source
	 *
	 * @param Store|array|string $source The source containing the data to import.
	 * @param string $mode How to import the data: merge or replace
	 */
	public function import( $source, string $mode = 'merge' ): void;

	public function export(): array;

	public function open();

	/**
	 * Saves the updated data to a JSON file.
	 *
	 * @return bool True if data changes exists and were saved, false otherwise.
	 */
	public function save(): bool;
}
