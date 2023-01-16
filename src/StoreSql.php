<?php namespace Peroks\Model;

use PDO;
use PDOException;

class StoreSql implements StoreInterface {

	/**
	 * @var PDO $db The database object.
	 */
	protected PDO $db;

	/**
	 * @inheritDoc
	 */
	public function get( string $id, string $class, bool $create = true ): ?ModelInterface {
		// TODO: Implement get() method.
	}

	/**
	 * @inheritDoc
	 */
	public function collect( array $ids, string $class, bool $create = true ): array {
		// TODO: Implement collect() method.
	}

	/**
	 * @inheritDoc
	 */
	public function exists( string $id, string $class ): bool {
		// TODO: Implement exists() method.
	}

	/**
	 * @inheritDoc
	 */
	public function list( string $class ): array {
		// TODO: Implement list() method.
	}

	/**
	 * @inheritDoc
	 */
	public function filter( string $class, array $filter ): array {
		// TODO: Implement filter() method.
	}

	/**
	 * @inheritDoc
	 */
	public function set( ModelInterface $model ): ModelInterface {
		// TODO: Implement set() method.
	}

	/**
	 * @inheritDoc
	 */
	public function delete( string $id, string $class ): bool {
		// TODO: Implement delete() method.
	}

	/**
	 * @inheritDoc
	 */
	public function save(): bool {
		// TODO: Implement save() method.
	}

	public function build( array $args ): bool {
		$default = [
			'connect' => [],
			'models'  => [],
		];

		$options = (object) array_merge( $default, $args );
		$connect = (object) $options->connect;

		if ( $this->connect( $connect ) ) {
			$models = $this->getAllModels( $options->models );
			$this->createTables( $models );
			$this->updateTables( $models );
			return true;
		}

		return false;
	}

	/* -------------------------------------------------------------------------
	 * Database interface
	 * ---------------------------------------------------------------------- */

	/**
	 * Creates a database connection.
	 *
	 * @param object $connect Connections parameters: host, user, pass, name, port, socket.
	 *
	 * @return bool True on success, null on failure to create a connection.
	 */
	protected function connect( object $connect ): bool {
		$args = [
			PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_PERSISTENT => true,
		];

		try {
			$dsn = "mysql:charset=utf8mb4;host={$connect->host};dbname={$connect->name}";
			$db  = new PDO( $dsn, $connect->user, $connect->pass, $args );
		} catch ( PDOException $e ) {
			$dsn = "mysql:charset=utf8mb4;host={$connect->host}";
			$db  = new PDO( $dsn, $connect->user, $connect->pass, $args );

			$db->exec( $this->createDatabaseQuery( $connect->name ) );
			$db->exec( "USE {$connect->name}" );
		}

		$this->db = $db;
		return true;
	}

	/**
	 * Executes a single query against the database.
	 *
	 * @param string $sql An sql statement.
	 *
	 * @return bool
	 */
	protected function exec( string $sql ): bool {
		$this->db->exec( $sql );
		return true;
	}

	/**
	 * Executes a single query against the database.
	 *
	 * @param string $sql An sql statement.
	 *
	 * @return array[] The query result.
	 */
	protected function query( string $sql, array $param = [], int $mode = PDO::FETCH_ASSOC ): array {
		$statement = $this->db->prepare( $sql );
		$statement->execute( $param );
		return $statement->fetchAll( $mode );
	}

	/**
	 * Quotes symbols, like db, table, column and index names.
	 *
	 * @param string $name The name to quote.
	 *
	 * @return string The quoted name.
	 */
	protected function quote( string $name ): string {
		return '`' . trim( trim( $name ), '`' ) . '`';
	}

	/* -------------------------------------------------------------------------
	 * Execute database statements.
	 * ---------------------------------------------------------------------- */

	/**
	 * Creates a new database with the given name.
	 *
	 * @param string $name The database name.
	 *
	 * @return bool True on success or if the database already exists, false otherwise.
	 */
	protected function createDatabase( string $name ): bool {
		return $this->exec( $this->createDatabaseQuery( $name ) );
	}

	/**
	 * Deletes the database with the given name.
	 *
	 * @param string $name The database name.
	 *
	 * @return bool True on success or if the database doesn't exist, false otherwise.
	 */
	protected function dropDatabase( string $name ): bool {
		return $this->exec( $this->dropDatabaseQuery( $name ) );
	}

	protected function getTables(): array {
		$sql = $this->getTablesQuery();
		return $this->query( $sql, [], PDO::FETCH_COLUMN );
	}

	protected function getColumns( string $table ): array {
		$sql = $this->getColumnsQuery( $table );
		return $this->query( $sql );
	}

	/**
	 * Creates tables for the given models and their sub-models.
	 *
	 * @param ModelInterface[]|string[] $models An array of models to create tables for.
	 *
	 * @return bool True if all tables were crated or already exist, false otherwise.
	 */
	protected function createTables( array $models ): bool {
		$count = 0;

		foreach ( $models as $model ) {
			$count += (int) $this->createTable( $model );
		}

		return count( $models ) === $count;
	}

	/**
	 * Update tables for the given models and their sub-models.
	 *
	 * @param ModelInterface[]|string[] $models An array of models to update tables for.
	 *
	 * @return bool True if all tables were crated or already exist, false otherwise.
	 */
	protected function updateTables( array $models ): bool {
		$tables = $this->getTables();
		$count  = 0;

		foreach ( $models as $model ) {
			if ( in_array( $this->getTableName( $model ), $tables ) ) {
				$count += (int) $this->updateTable( $model );
			}
			else {
				$count += (int) $this->createTable( $model );
			}
		}

		return count( $models ) === $count;
	}

	/**
	 * Creates a table for the given model.
	 *
	 * @param ModelInterface|string $model The model to create a table for.
	 *
	 * @return bool True if the table was created or already exists, false otherwise.
	 */
	protected function createTable( string $model ): bool {
		$sql = $this->createTableQuery( $model );
		return $this->exec( $sql );
	}

	/**
	 * Creates a table for the given model.
	 *
	 * @param ModelInterface|string $model The model to update a table for.
	 *
	 * @return bool True if the table was successfully updated, false otherwise.
	 */
	protected function updateTable( string $model ): bool {
		$delta = $this->getTableDelta( $model );
		return true;
	}

	/* -------------------------------------------------------------------------
	 * Generate sql queries from models.
	 * ---------------------------------------------------------------------- */

	/**
	 * Generates a query to create a database with the given name.
	 *
	 * @param string $name The database name.
	 *
	 * @return string Sql query to create a database.
	 */
	protected function createDatabaseQuery( string $name ): string {
		return sprintf( 'CREATE DATABASE IF NOT EXISTS %s', $this->quote( $name ) );
	}

	/**
	 * Generates a query to delete a database with the given name.
	 *
	 * @param string $name The database name.
	 *
	 * @return string Sql query to delete a database.
	 */
	protected function dropDatabaseQuery( string $name ): string {
		return sprintf( 'DROP DATABASE IF EXISTS %s', $this->quote( $name ) );
	}

	/* Table queries --------------------------------------------------------- */

	protected function getTablesQuery(): string {
		return 'SHOW TABLES;';
	}

	protected function getColumnsQuery( string $table ): string {
		return sprintf( 'SHOW COLUMNS FROM %s;', $table );
	}

	/**
	 * Generates a query to create a database table for the given model.
	 *
	 * @param ModelInterface|string $model The model to create a database table for.
	 *
	 * @return string Sql query to create a database table.
	 */
	protected function createTableQuery( string $model ): string {
		$properties = $model::properties();
		$primary    = $model::idProperty();
		$index      = $this->getTableIndices( $properties, PropertyItem::INDEX );
		$unique     = $this->getTableIndices( $properties, PropertyItem::UNIQUE );

		// Get column definitions from model properties.
		$columns = array_map( [ $this, 'createColumnQuery' ], $properties );
		$columns = array_values( array_filter( $columns ) );

		// Set primary key.
		if ( $primary && array_key_exists( $primary, $properties ) ) {
			$columns[] = sprintf( 'PRIMARY KEY (%s)', $this->quote( $primary ) );
		}

		// Set table indexes.
		foreach ( $index as $name => $fields ) {
			$fields    = array_map( [ $this, 'quote' ], $fields );
			$columns[] = sprintf( 'INDEX %s (%s)', $this->quote( $name ), join( ', ', $fields ) );
		}

		// Set table unique indexes.
		foreach ( $unique as $name => $fields ) {
			$fields    = array_map( [ $this, 'quote' ], $fields );
			$columns[] = sprintf( 'UNIQUE %s (%s)', $this->quote( $name ), join( ', ', $fields ) );
		}

		$sql   = "\n\t" . join( ",\n\t", $columns ) . "\n";
		$table = $this->quote( $this->getTableName( $model ) );

		return sprintf( 'CREATE TABLE IF NOT EXISTS %s (%s);', $table, $sql );
	}

	/**
	 * Get column definitions from model properties.
	 *
	 * @param array $property A model property.
	 *
	 * @return string Column definition string.
	 */
	protected function createColumnQuery( array $property ): string {
		$type     = $property[ PropertyItem::TYPE ] ?? PropertyType::MIXED;
		$model    = $property[ PropertyItem::MODEL ] ?? null;
		$foreign  = $property[ PropertyItem::FOREIGN ] ?? null;
		$required = $property[ PropertyItem::REQUIRED ] ?? false;

		// Storing functions is not supported.
		if ( PropertyType::FUNCTION === $type ) {
			return '';
		}

		// Arrays of models require a separate relationship table.
		if ( PropertyType::ARRAY === $type && ( $model || $foreign ) ) {
			if ( Utils::isModel( $model ) || Utils::isModel( $foreign ) ) {
				return '';
			}
		}

		// Replace sub-models with foreign keys.
		if ( PropertyType::OBJECT === $type && Utils::isModel( $model ) ) {
			if ( $primary = $model::getProperty( $model::idProperty() ) ) {
				$query[] = $this->quote( $property[ PropertyItem::ID ] );
				$query[] = $this->getColumnType( $primary->data() );
				return join( ' ', $query );
			}
		}

		$query[] = $this->quote( $property[ PropertyItem::ID ] );
		$query[] = $this->getColumnType( $property );
		$query[] = $required ? 'NOT NULL' : '';

		return join( ' ', array_filter( $query ) );
	}

	/* -------------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------------- */

	/**
	 * Gets the table name corresponding to the given model.
	 */
	protected function getTableName( string $model ): string {
		return str_replace( '\\', '_', $model );
	}

	/**
	 * Gets the deltas between a model and the corresponding table.
	 *
	 * @param ModelInterface|string $model A model class name.
	 *
	 * @return array An assoc array of added, updated and removed properties.
	 */
	protected function getTableDelta( string $model ): array {

		// Generates table columns from model properties.
		$generated = $this->getTableStructure( $model );

		// Read the current table structure from the database.
		$columns = $this->getColumns( $this->getTableName( $model ) );
		$columns = array_column( $columns, null, 'Field' );

		// Get deltas between the new and the current table columns.
		$union   = array_intersect_key( $generated, $columns );
		$removed = array_diff_key( $columns, $generated );
		$added   = array_diff_key( $generated, $columns );
		$updated = [];

		// Get updated columns.
		foreach ( $union as $id => $structure ) {
			if ( $diff = array_diff_assoc( $structure, $columns[ $id ] ) ) {
				$updated[ $id ] = $diff;
			}
		}

		// Get renamed columns.
		// There is no safe way to know if a model property was replaced or renamed.
		// Here we assume that if only the name is different, then the property was renamed.
		foreach ( $removed as $a => $column ) {
			foreach ( $added as $b => $structure ) {
				if ( count( $diff = array_diff_assoc( $structure, $column ) ) === 1 ) {
					$updated[ $a ] = $diff;
					unset( $removed[ $a ] );
					unset( $added[ $b ] );
					break;
				}
			}
		}

		return compact( 'added', 'updated', 'removed' );
	}

	/**
	 * Gets the table structure from a model.
	 *
	 * @param ModelInterface|string $model A model class name.
	 *
	 * @return array[] The corresponding sql table structure.
	 */
	protected function getTableStructure( string $model ): array {
		$properties = $model::properties();
		$primary    = $model::idProperty();
		$unique     = $this->getTableIndices( $properties, PropertyItem::UNIQUE );
		$indices    = $this->getTableIndices( $properties, PropertyItem::INDEX );
		$columns    = [];

		// Generate sql columns for all model properties.
		foreach ( $properties as $property ) {
			$this->getColumnStructure( $property, $columns );
		}

		// Set the primary key.
		if ( array_key_exists( $primary, $properties ) ) {
			$columns[ $primary ]['Key'] = 'PRI';
		}

		// Set unique and index keys.
		foreach ( array_merge( $indices, $unique ) as $fields ) {
			foreach ( $fields as $field ) {
				$columns[ $field ]['Key'] = count( $fields ) === 1 ? 'UNI' : 'MUL';
			}
		}

		return $columns;
	}

	/**
	 * Gets table indices of the given index type.
	 *
	 * @param array $properties Model properties.
	 * @param string $type The index type: 'index' or 'unique'.
	 *
	 * @return array An assoc array keyed by the index name.
	 */
	protected function getTableIndices( array $properties, string $type ): array {
		$indices = array_column( $properties, $type, PropertyItem::ID );
		$indices = array_filter( $indices );
		$result  = [];

		foreach ( $indices as $id => $name ) {
			$result[ $name ][] = $id;
		}

		return $result;
	}

	protected function getColumnStructure( array $data, array &$columns ): array {
		$property = Property::create( $data );
		$column   = [];

		// Storing functions is not supported.
		if ( PropertyType::FUNCTION === $property->type ) {
			return $column;
		}

		// Arrays of models require a separate relationship table.
		if ( PropertyType::ARRAY === $property->type && ( $property->model || $property->foreign ) ) {
			if ( Utils::isModel( $property->model ) || Utils::isModel( $property->foreign ) ) {
				return $column;
			}
		}

		if ( PropertyType::UUID === $property->type && true === $property->default ) {
			$property->default = null;
		}

		// Replace sub-models with foreign keys.
		if ( PropertyType::OBJECT === $property->type && Utils::isModel( $property->model ) ) {
			if ( $primary = $property->model::getProperty( $property->model::idProperty() ) ) {
				return $columns[ $property->id ] = [
					'Field'   => $property[ PropertyItem::ID ],
					'Type'    => $this->getColumnType( $primary->data() ),
					'Null'    => $property->required ? 'NO' : 'YES',
					'Key'     => '', // PRI, MUL, UNI
					'Default' => is_scalar( $property->default ) ? $property->default : null,
					'Extra'   => '',
				];
			}
		}

		return $columns[ $property->id ] = [
			'Field'   => $property[ PropertyItem::ID ],
			'Type'    => $this->getColumnType( $property->data( ModelData::COMPACT ) ),
			'Null'    => $property->required ? 'NO' : 'YES',
			'Key'     => '', // PRI, MUL, UNI
			'Default' => is_scalar( $property->default ) ? $property->default : null,
			'Extra'   => '',
		];
	}

	/**
	 * Gets the column data type.
	 *
	 * @param array $property A model property.
	 *
	 * @return string The sql data type.
	 */
	protected function getColumnType( array $property ): string {
		$type = $property[ PropertyItem::TYPE ] ?? PropertyType::MIXED;
		$max  = $property[ PropertyItem::MAX ] ?? null;

		switch ( $type ) {
			case PropertyType::MIXED:
				return 'varbinary(255)';
			case PropertyType::BOOL:
				return 'bool';
			case PropertyType::INTEGER:
				return 'bigint';
			case PropertyType::FLOAT:
			case PropertyType::NUMBER:
				return 'decimal(32,10)';
			case PropertyType::STRING:
				return ( $max && $max <= 255 ) ? sprintf( 'varchar(%d)', $max ) : 'text';
			case PropertyType::UUID:
				return 'char(36)';
			case PropertyType::URL:
			case PropertyType::EMAIL:
				return ( $max && $max <= 255 ) ? sprintf( 'varchar(%d)', $max ) : 'varchar(255)';
			case PropertyType::DATETIME:
				return 'varchar(32)';
			case PropertyType::DATE:
			case PropertyType::TIME:
				return 'varchar(10)';
			case PropertyType::OBJECT:
			case PropertyType::ARRAY:
				return 'text';
		}
		return '';
	}

	/**
	 * Extract all sub-models from the given model.
	 *
	 * @param ModelInterface|string $model A model class name.
	 * @param bool $include Whether to include the given model in the result or not.
	 *
	 * @return ModelInterface|string[] An array of sub-model classes of the given model class.
	 */
	protected function getSubModels( string $model, bool $include = true ): array {
		$result = $include ? [ $model ] : [];

		foreach ( $model::properties() as $property ) {
			$foreign = $property[ PropertyItem::MODEL ] ?? $property[ PropertyItem::FOREIGN ] ?? null;

			if ( $foreign && is_a( $foreign, ModelInterface::class, true ) ) {
				if ( empty( in_array( $foreign, $result, true ) ) ) {
					$result = array_merge( $result, $this->getSubModels( $foreign ) );
				}
			}
		}

		return $result;
	}

	/**
	 * Extract all sub-models from the given models.
	 *
	 * @param ModelInterface[]|string[] $models An array of model class names.
	 * @param ModelInterface[]|string[] $result An array of all model and sub-model class names.
	 *
	 * @return ModelInterface[]|string[] An array of all model and sub-model class names.
	 */
	protected function getAllModels( array $models, array &$result = [] ): array {
		$result = $result ?: $models;

		foreach ( $models as $model ) {
			foreach ( $model::properties() as $property ) {
				$foreign = $property[ PropertyItem::MODEL ] ?? $property[ PropertyItem::FOREIGN ] ?? null;

				if ( $foreign && is_a( $foreign, ModelInterface::class, true ) ) {
					if ( empty( in_array( $foreign, $result, true ) ) ) {
						$result[] = $foreign;
						$this->getAllModels( [ $foreign ], $result );
					}
				}
			}
		}

		return $result;
	}
}
