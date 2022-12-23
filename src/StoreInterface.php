<?php namespace Peroks\Model;

/**
 * Interface for storing and retrieving models from a data store.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
interface StoreInterface {

	const SET_REPLACE = 'replace';  // Replace the existing model with the new one.
	const SET_PATCH   = 'patch';    // Patch (update) the existing model with the new one.
	const SET_MERGE   = 'merge';    // Recursively merge data from the new model into the existing one.

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
	public function get( string $id, string $class, bool $create = false ): ?ModelInterface;

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
	 * Updating and deleting models
	 * ---------------------------------------------------------------------- */

	/**
	 * Saves and validates a model in the data store.
	 *
	 * @param ModelInterface $model The model to store.
	 *
	 * @return ModelInterface The stored model.
	 */
	public function set( ModelInterface $model ): ModelInterface;

	/**
	 * Deletes a model from the data store.
	 *
	 * @param string $id The model id.
	 * @param string $class The model class name.
	 *
	 * @return bool True if the model existed, false otherwise.
	 */
	public function delete( string $id, string $class ): bool;

	/**
	 * Saves the updated data to a JSON file.
	 *
	 * @return bool True if data changes exists and were saved, false otherwise.
	 */
	public function save(): bool;
}
