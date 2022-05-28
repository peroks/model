<?php namespace Peroks\Model;

/**
 * Simple class for storing and retrieving models from a JSON data store.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
class Store implements StoreInterface {

	/**
	 * @var array Stored data.
	 */
	protected array $data = [];

	/**
	 * @var array Changed data
	 */
	protected array $changes = [];

	/**
	 * @var object Global options.
	 */
	protected object $options;

	/**
	 * @var string JSON source file.
	 */
	protected string $source;

	/* -------------------------------------------------------------------------
	 * Constructor and destructor
	 * ---------------------------------------------------------------------- */

	public static function load( $source, $options = [] ): self {
		return new static( $source, $options );
	}

	public function __construct( $source, $options = [] ) {
		$this->source = $source;
		$this->init( $options );
		$this->open();
	}

	public function __destruct() {
		$this->save();
	}

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
	public function get( string $id, string $class, bool $create = true ): ?ModelInterface {
		if ( $create || $this->exists( $id, $class ) ) {
			$data = array_replace( $this->data[ $class ][ $id ] ?? [], $this->changes[ $class ][ $id ] ?? [] );
			return new $class( $data );
		}
		return null;
	}

	/**
	 * Retrieves a collection of model from the data store.
	 *
	 * @param string[] $ids An array of model ids.
	 * @param string $class The model class name.
	 * @param bool $create Whether to create a new model if not found.
	 *
	 * @return ModelInterface[] An array of new or existing models of the given class.
	 */
	public function collect( array $ids, string $class, bool $create = true ): array {
		foreach ( $ids as $id ) {
			$result[ $id ] = $this->get( $id, $class, $create );
		}
		return array_filter( $result ?? [] );
	}

	/**
	 * Checks if a model with the given id exists in the data store.
	 *
	 * @param string $id The model id.
	 * @param string $class The model class name.
	 *
	 * @return bool True if the model is found in the data store.
	 */
	public function exists( string $id, string $class ): bool {
		return isset( $this->data[ $class ][ $id ] )
			|| isset( $this->changes[ $class ][ $id ] );
	}

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
	public function list( string $class ): array {
		$result = array_map( [ $class, 'create' ], array_replace(
			$this->data[ $class ] ?? [],
			$this->changes[ $class ] ?? []
		) );

		/** @var ModelInterface $class */
		return array_column( $result, null, $class::primary() );
	}

	/**
	 * Gets a filtered list of models of the given class.
	 *
	 * @param string $class The model class name.
	 * @param array $filter Properties (key/value pairs) to match the stored models.
	 *
	 * @return ModelInterface[] An assoc array of models keyed by the model ids.
	 */
	public function filter( string $class, array $filter ): array {
		$all = $this->list( $class );

		if ( empty( $filter ) ) {
			return $all;
		}

		return array_filter( $all, function( ModelInterface $model ) use ( $filter ): bool {
			return array_intersect_assoc( $filter, $model->data() ) === $filter;
		} );
	}

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
	public function set( ModelInterface $model ): string {
		$id    = $model->id();
		$class = get_class( $model );
		$data  = $model->validate()->raw();

		$this->changes[ $class ][ $id ] = static::array_filter_null( $data );
		return $id;
	}

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
	public function update( ModelInterface $model ): string {
		$id     = $model->id();
		$class  = get_class( $model );
		$stored = $this->get( $id, $class )->merge( $model );
		$data   = $stored->validate()->raw();

		$this->changes[ $class ][ $id ] = static::array_filter_null( $data );
		return $id;
	}

	/**
	 * Deletes a model from the data store.
	 *
	 * @param string $id The model id.
	 * @param string $class The model class name.
	 */
	public function delete( string $id, string $class ): void {
		if ( $this->exists( $id, $class ) ) {
			$this->data[ $class ][ $id ]    = null;
			$this->changes[ $class ][ $id ] = null;
		}
	}

	/* -------------------------------------------------------------------------
	 * Class initialization
	 * ---------------------------------------------------------------------- */

	/**
	 * @param array|object $options
	 */
	public function init( $options ): void {
		$default = [
			'force_add'    => false,
			'force_update' => false,
			'json_encode'  => JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
			'json_decode'  => JSON_THROW_ON_ERROR,
		];

		$this->options = (object) array_replace( $default, (array) $options );
	}

	/* -------------------------------------------------------------------------
	 * File handling
	 * ---------------------------------------------------------------------- */

	/**
	 * Reads a JSON source file into the data store.
	 *
	 * @return array The JSON decoded data.
	 */
	public function read( $source ): array {
		if ( is_readable( $source ) ) {
			if ( $content = file_get_contents( $source ) ) {
				return json_decode( $content, true, 64, $this->options->json_decode );
			}
		}
		return [];
	}

	/**
	 * Imports data from a source
	 *
	 * @param Store|array|string $source The source containing the data to import.
	 * @param string $mode How to import the data: merge or replace
	 */
	public function import( $source, string $mode = 'merge' ): void {
		if ( is_array( $source ) ) {
			$data = $source;
		} elseif ( $source instanceof self ) {
			$data = $source->export();
		} elseif ( is_readable( $source ) ) {
			$data = $this->read( $source );
		} elseif ( is_string( $source ) ) {
			if ( $result = json_decode( $source, true ) && JSON_ERROR_NONE == json_last_error() ) {
				$data = $result;
			}
		}

		/** @var ModelInterface $class */
		foreach ( $data ?? [] as $class => $pairs ) {
			foreach ( $pairs as $inst ) {
				$this->set( $class::create( $inst ) );
			}
		}
	}

	public function export(): array {
		return $this->merge( $this->data );
	}

	/**
	 * Writes the data store to a JSON file.
	 *
	 * @param array $data The data to be stored as a JSON source.
	 *
	 * @return bool True if successful, false otherwise.
	 */
	public function write( $source, array $data ): bool {
		if ( $content = json_encode( $data, $this->options->json_encode, 64 ) ) {
			return is_int( file_put_contents( $source, $content, LOCK_EX ) );
		}
		return false;
	}

	/**
	 * Merges the data with the changes.
	 *
	 * @param array $data The data to be merged.
	 */
	public function merge( array $data ): array {
		foreach ( $this->changes as $class => $pairs ) {
			foreach ( $pairs as $id => $inst ) {
				$data[ $class ][ $id ] = $inst;
			}
		}

		foreach ( $data as $class => &$pairs ) {
			$pairs = array_filter( $pairs );
			static::sort( $pairs );
		}

		return $data;
	}

	public function open() {
		$this->data = $this->read( $this->source );
	}

	/**
	 * Saves the updated data to a JSON file.
	 *
	 * @return bool True if data changes exists and were saved, false otherwise.
	 */
	public function save(): bool {
		if ( empty( $this->changes ) ) {
			return false;
		}

		$data = $this->read( $this->source );
		$data = $this->merge( $data );
		$this->write( $this->source, $data );

		$this->data    = $data;
		$this->changes = [];

		return true;
	}

	/* -------------------------------------------------------------------------
	 * Utils
	 * ---------------------------------------------------------------------- */

	public static function sort( array &$data ) {
		ksort( $data, SORT_NATURAL );
	}

	/**
	 * Removes all null values from an array.
	 *
	 * @param array|object $array The source array.
	 *
	 * @return array The modified array.
	 */
	public static function array_filter_null( $array ): array {
		return array_filter( (array) $array, function( $value ) {
			return isset( $value );
		} );
	}
}
