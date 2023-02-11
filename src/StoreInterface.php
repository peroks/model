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
	 * @param string $id The model id.
	 * @param ModelInterface|string $class The model class name.
	 *
	 * @return bool True if the model is found in the data store.
	 */
	public function exists( string $id, string $class ): bool;

	/**
	 * Gets a model from the data store.
	 *
	 * @param int|string $id The model id.
	 * @param ModelInterface|string $class The model class name.
	 * @param bool $restore Whether to restore the model including all sub-model or not.
	 *
	 * @return ModelInterface|null A new or existing model of the given class.
	 */
	public function get( $id, string $class, bool $restore = true ): ?ModelInterface;

	/**
	 * Retrieves a collection of model from the data store.
	 *
	 * @param int[]|string[] $ids An array of model ids.
	 * @param ModelInterface|string $class The model class name.
	 * @param bool $restore Whether to restore the models including all sub-models or not.
	 *
	 * @return ModelInterface[] An array of new or existing models of the given class.
	 */
	public function collect( array $ids, string $class, bool $restore = true ): array;

	/**
	 * Gets a list of all models of the given class.
	 *
	 * @param ModelInterface|string $class The model class name.
	 * @param bool $restore Whether to restore the models including all sub-models or not.
	 *
	 * @return ModelInterface[] An array of models.
	 */
	public function list( string $class, bool $restore = true ): array;

	/**
	 * Gets a filtered list of models of the given class.
	 *
	 * @param ModelInterface|string $class The model class name.
	 * @param array $filter Properties (key/value pairs) to match the stored models.
	 * @param bool $restore Whether to restore the models including all sub-models or not.
	 *
	 * @return ModelInterface[] An array of models.
	 */
	public function filter( string $class, array $filter, bool $restore = true ): array;

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

	/**
	 * Completely restores the given model including all sub-models.
	 *
	 * @param ModelInterface $model The model to restore.
	 *
	 * @return ModelInterface The completely restored model.
	 */
	public function restore( ModelInterface $model ): ModelInterface;

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
