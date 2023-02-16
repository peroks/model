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
	 * Retrieving models.
	 * ---------------------------------------------------------------------- */

	/**
	 * Checks if a model with the given id exists in the data store.
	 *
	 * @param int|string $id The model id.
	 * @param ModelInterface|string $class The model class name.
	 *
	 * @return bool True if the model exists, false otherwise.
	 */
	public function exists( string $id, string $class ): bool;

	/**
	 * Gets a model matching the given id from the data store.
	 *
	 * @param int|string $id The model id.
	 * @param ModelInterface|string $class The model class name.
	 *
	 * @return ModelInterface|null The matching model or null if not found.
	 */
	public function get( $id, string $class ): ?ModelInterface;

	/**
	 * Gets a list of models matching the given ids from the data store.
	 *
	 * @param int[]|string[] $ids An array of model ids.
	 * @param ModelInterface|string $class The model class name.
	 *
	 * @return ModelInterface[] An array of matching models.
	 */
	public function list( array $ids, string $class ): array;

	/**
	 * Gets a filtered list of models from the data store.
	 *
	 * @param ModelInterface|string $class The model class name.
	 * @param array $filter Properties (key/value pairs) to match the stored models.
	 *
	 * @return ModelInterface[] An array of models.
	 */
	public function filter( string $class, array $filter = [] ): array;

	/**
	 * Gets all models of the given class in the data store.
	 *
	 * @param ModelInterface|string $class The model class name.
	 *
	 * @return ModelInterface[] An array of models.
	 */
	public function all( string $class ): array;

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
	 * @param ModelInterface|string $class The model class name.
	 *
	 * @return bool True if the model existed, false otherwise.
	 */
	public function delete( string $id, string $class ): bool;

	/* -------------------------------------------------------------------------
	 * Data store handling
	 * ---------------------------------------------------------------------- */

	/**
	 * Builds a data store if necessary.
	 *
	 * @param array $models The models to add to the data store.
	 * @param array $options An assoc array of options.
	 *
	 * @return bool
	 */
	public function build( array $models, array $options = [] ): bool;

	/**
	 * Flushes model data to permanent storage if necessary.
	 *
	 * @return bool True if data changes exists and were saved, false otherwise.
	 */
	public function flush(): bool;
}
