<?php namespace Peroks\Model;

use PDO, PDOException, PDOStatement;

/**
 * Class for storing and retrieving models from a SQL database.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
class StoreSql implements StoreInterface {

	/**
	 * @var PDO|object $db The database object.
	 */
	protected object $db;

	/**
	 * @var string The database name for this store.
	 */
	protected string $dbname;

	/**
	 * @var array An array of prepared query statements.
	 */
	protected array $queries = [];

	/**
	 * @var array A temp array of model relations.
	 */
	protected array $relations = [];

	public function __construct( object $connect ) {
		$this->dbname = $connect->name;
		$this->connect( $connect );
	}

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
	public function exists( string $id, string $class ): bool {
		$query  = $this->existsRowStatement( $class );
		$result = $this->select( $query, [ $id ] );
		return (bool) $result;
	}

	/**
	 * Gets a model from the data store.
	 *
	 * @param int|string $id The model id.
	 * @param ModelInterface|string $class The model class name.
	 * @param bool $restore Whether to restore the model including all sub-model or not.
	 *
	 * @return ModelInterface|null A new or existing model of the given class.
	 */
	public function get( $id, string $class, bool $restore = true ): ?ModelInterface {
		$query = $this->selectRowStatement( $class );
		$rows  = $this->select( $query, [ $id ] );

		if ( $rows ) {
			$model = new $class( $rows[0] );
			return $restore ? $this->restore( $model ) : $model;
		}

		return null;
	}

	/**
	 * Retrieves a collection of model from the data store.
	 *
	 * @param int[]|string[] $ids An array of model ids.
	 * @param ModelInterface|string $class The model class name.
	 * @param bool $restore Whether to restore the models including all sub-models or not.
	 *
	 * @return ModelInterface[] An array of new or existing models of the given class.
	 */
	public function collect( array $ids, string $class, bool $restore = true ): array {
		$query = $this->collectRowsStatement( $class, count( $ids ) );
		$rows  = $this->select( $query, array_values( $ids ) );

		// Convert table rows to models.
		array_walk( $rows, fn( &$row ) => $row = new $class( $row ) );

		if ( $restore ) {
			array_walk( $rows, [ $this, 'restore' ] );
		}

		return $rows;
	}

	/**
	 * Gets a list of all models of the given class.
	 *
	 * @param ModelInterface|string $class The model class name.
	 * @param bool $restore Whether to restore the models including all sub-models or not.
	 *
	 * @return ModelInterface[] An array of models.
	 */
	public function list( string $class, bool $restore = true ): array {
		$query = $this->selectListStatement( $class );
		$rows  = $this->select( $query );

		// Convert table rows to models.
		array_walk( $rows, fn( &$row ) => $row = new $class( $row ) );

		if ( $restore ) {
			array_walk( $rows, [ $this, 'restore' ] );
		}

		return $rows;
	}

	/**
	 * Gets a filtered list of models of the given class.
	 *
	 * @param ModelInterface|string $class The model class name.
	 * @param array $filter Properties (key/value pairs) to match the stored models.
	 * @param bool $restore Whether to restore the models including all sub-models or not.
	 *
	 * @return ModelInterface[] An array of models.
	 */
	public function filter( string $class, array $filter, bool $restore = true ): array {
		$query = $this->selectFilterStatement( $class, $filter );
		$rows  = $this->select( $query, $filter );

		// Convert table rows to models.
		array_walk( $rows, fn( &$row ) => $row = new $class( $row ) );

		if ( $restore ) {
			array_walk( $rows, [ $this, 'restore' ] );
		}

		return $rows;
	}

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
	public function set( ModelInterface $model ): ModelInterface {
		$model->validate( true );

		$class = get_class( $model );
		$query = $this->exists( $model->id(), $class )
			? $this->updateRowStatement( $class )
			: $this->insertRowStatement( $class );

		$values    = $this->getModelValues( $model );
		$relations = $this->getModelRelations( $model );
		$rows      = $this->update( $query, $values );

		foreach ( $relations as $child => $list ) {
			$this->updateRelation( $model, $child, $list );
		}

		return $model;
	}

	/**
	 * Deletes a model from the data store.
	 *
	 * @param string $id The model id.
	 * @param ModelInterface|string $class The model class name.
	 *
	 * @return bool True if the model existed, false otherwise.
	 */
	public function delete( string $id, string $class ): bool {
		$query  = $this->deleteRowStatement( $class );
		$result = $this->update( $query, [ $id ] );
		return (bool) $result;
	}

	/**
	 * Completely restores the given model including all sub-models.
	 *
	 * @param ModelInterface $model The model to restore.
	 *
	 * @return ModelInterface The completely restored model.
	 */
	public function restore( ModelInterface $model ): ModelInterface {
		$properties = static::getForeignProperties( $model::properties() );

		foreach ( $properties as $id => $property ) {
			$child = $property[ PropertyItem::MODEL ];
			$value = &$model[ $id ];

			if ( PropertyType::ARRAY === $property[ PropertyItem::TYPE ] ) {
				$select = $this->selectChildrenStatement( get_class( $model ), $child );
				$rows   = $this->select( $select, (array) $model->id() );
				$value  = array_map( fn( $row ) => $this->restore( new $child( $row ) ), $rows );
			} elseif ( $value ) {
				$value = $this->get( $value, $child );
			}
		}

		return $model->validate( true );
	}

	/* -------------------------------------------------------------------------
	 * Data store handling
	 * ---------------------------------------------------------------------- */

	/**
	 * Builds a data store is necessary.
	 *
	 * @param array $models The models to add to the data store.
	 * @param array $options An assoc array of options.
	 *
	 * @return bool
	 */
	public function build( array $models, array $options = [] ): bool {
		$models = $this->getAllModels( $models );
		return (bool) $this->buildDatabase( $models );
	}

	/**
	 * Flushes model data to permanent storage is necessary.
	 *
	 * @return bool True if data changes exists and were saved, false otherwise.
	 */
	public function flush(): bool {
		return true;
	}

	/* -------------------------------------------------------------------------
	 * Database abstraction layer
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
			PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_PERSISTENT       => true,
			PDO::ATTR_EMULATE_PREPARES => false,
		];

		// Delete database.
		if ( false ) {
			$dsn = "mysql:charset=utf8mb4;host={$connect->host}";
			$db  = new PDO( $dsn, $connect->user, $connect->pass, $args );
			$db->exec( $this->dropDatabaseQuery( $connect->name ) );
		}

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
	 * @param string $query An sql statement.
	 *
	 * @return int
	 */
	protected function exec( string $query ): int {
		return $this->db->exec( $query );
	}

	/**
	 * Executes a single query against the database.
	 *
	 * @param string $query A sql query.
	 *
	 * @return array[] The query result.
	 */
	protected function query( string $query, array $param = [] ): array {
		$statement = $this->db->prepare( $query );
		$statement->execute( $param );
		return $statement->fetchAll( PDO::FETCH_ASSOC );
	}

	/**
	 * Prepares a statement for execution and returns a statement object.
	 *
	 * @param string $query A valid sql statement template.
	 *
	 * @return PDOStatement|object
	 */
	protected function prepare( string $query ): object {
		return $this->db->prepare( $query );
	}

	protected function select( object $statement, array $param = [] ): array {
		$statement->execute( $param );
		return $statement->fetchAll( PDO::FETCH_ASSOC );
	}

	/**
	 * Inserts, updates or deletes a row.
	 *
	 * @param PDOStatement|object $statement A prepared update query.
	 * @param array $param An array of values for the prepared sql statement being executed.
	 *
	 * @return int The number of updated rows.
	 */
	protected function update( object $statement, array $param = [] ): int {
		$statement->execute( $param );
		return $statement->rowCount();
	}

	/**
	 * Quotes db, table, column and index names.
	 *
	 * @param string $name The name to quote.
	 *
	 * @return string The quoted name.
	 */
	protected function name( string $name ): string {
		return '`' . trim( trim( $name ), '`' ) . '`';
	}

	/**
	 * Quotes a string for use in a query.
	 *
	 * @param string $value The string to be quoted.
	 *
	 * @return string The quoted string.
	 */
	protected function quote( string $value ): string {
		return $this->db->quote( $value );
	}

	/* -------------------------------------------------------------------------
	 * Create and drop databases
	 * ---------------------------------------------------------------------- */

	/**
	 * Generates a query to create a database with the given name.
	 *
	 * @param string $name The database name.
	 *
	 * @return string Sql query to create a database.
	 */
	protected function createDatabaseQuery( string $name ): string {
		$name  = $this->name( $name );
		$sql[] = "CREATE DATABASE IF NOT EXISTS {$name}";
		$sql[] = 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
		return join( "\n", $sql );
	}

	/**
	 * Creates a new database with the given name.
	 *
	 * @param string $name The database name.
	 *
	 * @return int True on success or if the database already exists, false otherwise.
	 */
	protected function createDatabase( string $name ): int {
		$query = $this->createDatabaseQuery( $name );
		return $this->exec( $query );
	}

	/**
	 * Generates a query to delete a database with the given name.
	 *
	 * @param string $name The database name.
	 *
	 * @return string Sql query to delete a database.
	 */
	protected function dropDatabaseQuery( string $name ): string {
		return sprintf( 'DROP DATABASE IF EXISTS %s', $this->name( $name ) );
	}

	/**
	 * Deletes the database with the given name.
	 *
	 * @param string $name The database name.
	 *
	 * @return int True on success or if the database doesn't exist, false otherwise.
	 */
	protected function dropDatabase( string $name ): int {
		$query = $this->dropDatabaseQuery( $name );
		return $this->exec( $query );
	}

	/**
	 * Creates or update tables for the given models and their sub-models.
	 *
	 * @param ModelInterface[]|string[] $models An array of models to update tables for.
	 *
	 * @return int The number of created or altered tables.
	 */
	protected function buildDatabase( array $models ): int {
		$count = 0;

		// Create or alter model tables (columns + indexes).
		foreach ( $models as $name ) {
			$count += $this->createTable( $name ) ?: $this->alterTable( $name );
		}

		// Create or alter relation tables (columns + indexes).
		foreach ( array_keys( $this->relations ) as $name ) {
			$count += $this->createTable( $name ) ?: $this->alterTable( $name );
		}

		// Merge all model class names and relation table names.
		$all = array_merge( $models, array_keys( $this->relations ) );

		// Set foreign keys after all tables, columns and indexes are in place.
		foreach ( $all as $name ) {
			$count += $this->alterForeign( $name );
		}

		return $count;
	}

	/* -------------------------------------------------------------------------
	 * Show, create and alter tables
	 * ---------------------------------------------------------------------- */

	protected function showTablesQuery(): string {
		return 'SHOW TABLES';
	}

	protected function showTables(): array {
		$query = $this->showTablesQuery();
		return $this->query( $query );
	}

	protected function showTableNames(): array {
		foreach ( $this->showTables() as $table ) {
			$result[] = current( $table );
		}
		return $result ?? [];
	}

	/**
	 * Generates a query to create a database table for the given model.
	 *
	 * This method only creates table columns, not indexes.
	 *
	 * @param ModelInterface|string $class The model to create a database table for.
	 *
	 * @return string Sql query to create a database table.
	 */
	protected function createTableQuery( string $class ): string {
		if ( Utils::isModel( $class ) ) {
			$columns = $this->getModelColumns( $class );
			$indexes = $this->getModelIndexes( $class );
		} else {
			$columns = $this->getRelationColumns( $class );
			$indexes = $this->getRelationIndexes( $class );
		}

		// Create columns.
		foreach ( $columns as $column ) {
			$sql[] = $this->defineColumnQuery( $column );
		}

		// Create indexes.
		foreach ( $indexes as $index ) {
			$sql[] = $this->defineIndexQuery( $index );
		}

		if ( isset( $sql ) ) {
			$sql   = "\n\t" . join( ",\n\t", $sql ) . "\n";
			$table = $this->name( $this->getTableName( $class ) );
			return sprintf( 'CREATE TABLE IF NOT EXISTS %s (%s)', $table, $sql );
		}

		$table = $this->name( $this->getTableName( $class ) );
		return sprintf( 'CREATE TABLE IF NOT EXISTS %s', $table );
	}

	/**
	 * Generates a query to create a table for the given model.
	 *
	 * @param ModelInterface|string $class The model to create a table for.
	 *
	 * @return int True if the table was created or already exists, false otherwise.
	 */
	protected function createTable( string $class ): int {
		$query = $this->createTableQuery( $class );
		return $this->exec( $query );
	}

	/**
	 * Generates a query to alter a database table to match the given model.
	 *
	 * @param ModelInterface|string $class The model to create a database table for.
	 *
	 * @return string Sql query to update a database table.
	 */
	protected function alterTableQuery( string $class ): string {
		$columns = $this->getDeltaColumns( $class );
		$indexes = $this->getDeltaIndexes( $class );

		// Drop indexes.
		foreach ( array_keys( $indexes['drop'] ) as $name ) {
			$sql[] = sprintf( 'DROP INDEX %s', $this->name( $name ) );
		}

		// Drop columns.
		foreach ( array_keys( $columns['drop'] ) as $name ) {
			$sql[] = sprintf( 'DROP COLUMN %s', $this->name( $name ) );
		}

		// Alter columns.
		foreach ( $columns['alter'] as $old => $column ) {
			$sql[] = $old === $column['name']
				? sprintf( 'MODIFY COLUMN %s', $this->defineColumnQuery( $column ) )
				: sprintf( 'CHANGE COLUMN %s %s', $this->name( $old ), $this->defineColumnQuery( $column ) );
		}

		// Create columns.
		foreach ( $columns['create'] as $column ) {
			$sql[] = sprintf( 'ADD COLUMN %s', $this->defineColumnQuery( $column ) );
		}

		// Create indexes.
		foreach ( $indexes['create'] as $index ) {
			$sql[] = sprintf( 'ADD %s', $this->defineIndexQuery( $index ) );
		}

		if ( isset( $sql ) ) {
			$sql   = "\n" . join( ",\n", $sql );
			$table = $this->name( $this->getTableName( $class ) );
			return sprintf( 'ALTER TABLE %s %s', $table, $sql );
		}

		return '';
	}

	/**
	 * Creates a table for the given model.
	 *
	 * @param ModelInterface|string $class The model to update a table for.
	 *
	 * @return int True if the table was successfully updated, false otherwise.
	 */
	protected function alterTable( string $class ): int {
		if ( $query = $this->alterTableQuery( $class ) ) {
			return $this->exec( $query );
		}
		return 0;
	}

	/* -------------------------------------------------------------------------
	 * Show and define table columns.
	 * ---------------------------------------------------------------------- */

	protected function showColumnsQuery( string $table ): string {
		return sprintf( 'SHOW COLUMNS FROM %s', $this->name( $table ) );
	}

	protected function showColumns( string $table ): array {
		$query = $this->showColumnsQuery( $table );
		return $this->query( $query );
	}

	protected function defineColumnQuery( array $column ): string {
		$type     = $column['type'];
		$required = $column['required'] ?? null;
		$default  = $column['default'] ?? null;

		// Cast default value.
		if ( isset( $default ) ) {
			if ( is_string( $default ) ) {
				$default = $this->quote( $default );
			}
		} elseif ( empty( $required ) && 'TEXT' !== $type ) {
			$default = 'NULL';
		}

		return join( ' ', array_filter( [
			$this->name( $column['name'] ),
			$type,
			$required ? 'NOT NULL' : null,
			isset( $default ) ? "DEFAULT {$default}" : null,
		] ) );
	}

	protected function getTableColumns( string $table ): array {
		$columns = $this->showColumns( $table );
		$result  = [];

		foreach ( $columns as $column ) {
			$name    = $column['Field'];
			$default = $column['Default'];

			// Normalise default values.
			if ( isset( $default ) ) {
				if ( 'decimal(32,10)' === $column['Type'] ) {
					$default = (float) $default;
				}
			}

			$result[ $name ] = [
				'name'     => $name,
				'type'     => $column['Type'],
				'required' => $column['Null'] === 'NO',
				'default'  => $default,
			];
		}

		return $result;
	}

	/**
	 * Get model columns.
	 *
	 * @param ModelInterface|string $class
	 *
	 * @return array
	 */
	protected function getModelColumns( string $class ): array {
		$properties = $class::properties();
		$result     = [];

		foreach ( $properties as $id => $property ) {
			if ( Utils::isColumn( $property ) ) {
				$type    = $property[ PropertyItem::TYPE ] ?? PropertyType::MIXED;
				$child   = $property[ PropertyItem::MODEL ] ?? null;
				$default = $property[ PropertyItem::DEFAULT ] ?? null;

				if ( empty( is_scalar( $default ) ) ) {
					$default = null;
				}

				if ( PropertyType::UUID === $type && true === $default ) {
					$default = null;
				}

				if ( is_bool( $default ) ) {
					$default = (int) $default;
				}

				$result[ $id ] = [
					'name'     => $id,
					'type'     => $this->getColumnType( $property ),
					'required' => $property[ PropertyItem::REQUIRED ] ?? false,
					'default'  => $default,
				];

				// Replace sub-models with foreign keys.
				if ( PropertyType::OBJECT === $type && Utils::isModel( $child ) ) {
					if ( $primary = $child::getProperty( $child::idProperty() ) ) {
						$result[ $id ]['type'] = $this->getColumnType( $primary );
					}
				}
			} elseif ( Utils::isRelation( $property ) ) {
				$this->addRelation( $class, $property[ PropertyItem::MODEL ] );
			}
		}

		return $result;
	}

	/**
	 * Gets the column data type.
	 *
	 * @param Property|array $property A model property.
	 *
	 * @return string The sql data type.
	 */
	protected function getColumnType( $property ): string {
		$type = $property[ PropertyItem::TYPE ] ?? PropertyType::MIXED;

		switch ( $type ) {
			case PropertyType::MIXED:
				return 'varbinary(255)';
			case PropertyType::BOOL:
				return 'tinyint(1)';
			case PropertyType::INTEGER:
				return 'bigint(20)';
			case PropertyType::FLOAT:
			case PropertyType::NUMBER:
				return 'decimal(32,10)';
			case PropertyType::UUID:
				return 'char(36)';
			case PropertyType::STRING:
			case PropertyType::URL:
			case PropertyType::EMAIL:
				$unique  = $property[ PropertyItem::UNIQUE ] ?? null;
				$index   = $property[ PropertyItem::INDEX ] ?? null;
				$default = $property[ PropertyItem::DEFAULT ] ?? null;
				$max     = $property[ PropertyItem::MAX ] ?? PHP_INT_MAX;
				$max     = isset( $unique ) || isset( $index ) || isset( $default ) ? min( 255, $max ) : $max;

				return ( $max <= 255 ) ? sprintf( 'varchar(%d)', $max ) : 'text';
			case PropertyType::DATETIME:
				return 'varchar(32)';
			case PropertyType::DATE:
				return 'varchar(10)';
			case PropertyType::TIME:
				return 'varchar(8)';
			case PropertyType::OBJECT:
			case PropertyType::ARRAY:
				return 'text';
		}
		return '';
	}

	/**
	 * @param ModelInterface|string $class
	 *
	 * @return array
	 */
	protected function getDeltaColumns( string $class ): array {
		$tableColumns = $this->getTableColumns( $this->getTableName( $class ) );
		$modelColumns = Utils::isModel( $class )
			? $this->getModelColumns( $class )
			: $this->getRelationColumns( $class );

		$common = array_intersect_key( $modelColumns, $tableColumns );
		$drop   = array_diff_key( $tableColumns, $modelColumns );
		$create = array_diff_key( $modelColumns, $tableColumns );
		$alter  = [];

		// Get altered columns.
		foreach ( $common as $name => $column ) {
			if ( array_diff_assoc( $column, $tableColumns[ $name ] ) ) {
				$alter[ $name ] = $column;
			}
		}

		// Get renamed columns.
		// There is no safe way to know if a model property was replaced or renamed.
		// Here we assume that if the column type remains the same, then the property was renamed.
		foreach ( $create as $name => $modelColumn ) {
			foreach ( $drop as $old => $tableColumn ) {
				if ( $modelColumn['type'] === $tableColumn['type'] ) {
					$alter[ $old ] = $modelColumn;
					unset( $create[ $name ] );
					unset( $drop[ $old ] );
					break;
				}
			}
		}

		return compact( 'drop', 'alter', 'create' );
	}

	/* -------------------------------------------------------------------------
	 * Show and define table indexes
	 * ---------------------------------------------------------------------- */

	protected function showIndexesQuery( string $table ): string {
		return vsprintf( 'SHOW INDEXES FROM %s', [
			$this->name( $table ),
		] );
	}

	protected function showIndexes( string $table ): array {
		$query = $this->showIndexesQuery( $table );
		return $this->query( $query );
	}

	protected function defineIndexQuery( array $index ): string {
		$name    = $this->name( $index['name'] );
		$columns = join( ', ', array_map( [ $this, 'name' ], $index['columns'] ) );

		switch ( $index['type'] ?? 'INDEX' ) {
			case 'PRIMARY':
				return sprintf( 'PRIMARY KEY (%s)', $columns );
			case 'UNIQUE':
				return sprintf( 'UNIQUE %s (%s)', $name, $columns );
			default:
				return sprintf( 'INDEX %s (%s)', $name, $columns );
		}
	}

	protected function getTableIndexes( string $table ): array {
		$indexes = $this->showIndexes( $table );
		$indexes = Utils::group( $indexes, 'Key_name' );
		$result  = [];

		foreach ( $indexes as $name => $index ) {
			if ( 'PRIMARY' === $index[0]['Key_name'] ) {
				$type = 'PRIMARY';
			} else {
				$type = $index[0]['Non_unique'] ? 'INDEX' : 'UNIQUE';
			}

			$result[ $name ] = [
				'name'    => $name,
				'type'    => $type,
				'columns' => array_column( $index, 'Column_name' ),
			];
		}

		return $result;
	}

	/**
	 * Get model indexes.
	 *
	 * @param ModelInterface|string $class
	 *
	 * @return array
	 */
	protected function getModelIndexes( string $class ): array {
		$properties = $class::properties();
		$primary    = $class::idProperty();
		$result     = [];

		// Set primary key.
		if ( $primary && array_key_exists( $primary, $properties ) ) {
			$result['PRIMARY'] = [
				'name'    => 'PRIMARY',
				'type'    => 'PRIMARY',
				'columns' => [ $primary ],
			];
		}

		foreach ( $properties as $id => $property ) {
			if ( Utils::isColumn( $property ) ) {

				// Set index.
				if ( $name = $property[ PropertyItem::INDEX ] ?? null ) {
					$result[ $name ]['name']      = $name;
					$result[ $name ]['type']      = 'INDEX';
					$result[ $name ]['columns'][] = $id;
				}

				// Set unique index.
				if ( $name = $property[ PropertyItem::UNIQUE ] ?? null ) {
					$result[ $name ]['name']      = $name;
					$result[ $name ]['type']      = 'UNIQUE';
					$result[ $name ]['columns'][] = $id;
				}

				// Set indexes for foreign keys.
				if ( Utils::isForeign( $property ) ) {
					$result[ $id ] = $result[ $id ] ?? [
						'name'    => $id,
						'type'    => 'INDEX',
						'columns' => [ $id ],
					];
				}
			}
		}

		return $result;
	}

	/**
	 * @param ModelInterface|string $class
	 *
	 * @return array
	 */
	protected function getDeltaIndexes( string $class ): array {
		$tableIndexes = $this->getTableIndexes( $this->getTableName( $class ) );
		$modelIndexes = Utils::isModel( $class )
			? $this->getModelIndexes( $class )
			: $this->getRelationIndexes( $class );

		$common = array_intersect_key( $modelIndexes, $tableIndexes );
		$drop   = array_diff_key( $tableIndexes, $modelIndexes );
		$create = array_diff_key( $modelIndexes, $tableIndexes );

		// Check for differences.
		foreach ( $common as $name => $modelIndex ) {
			$tableIndex = $tableIndexes[ $name ];
			$modelJson  = Utils::encode( $modelIndex );
			$tableJson  = Utils::encode( $tableIndex );

			if ( $modelJson !== $tableJson ) {
				$drop[ $name ]   = $tableIndex;
				$create[ $name ] = $modelIndex;
			}
		}

		return compact( 'drop', 'create' );
	}

	/* -------------------------------------------------------------------------
	 * Show and define foreign keys
	 * ---------------------------------------------------------------------- */

	protected function showForeignQuery( string $table ): string {
		$sql[] = 'SELECT * FROM information_schema.KEY_COLUMN_USAGE as kcu';
		$sql[] = 'JOIN   information_schema.REFERENTIAL_CONSTRAINTS as rc';
		$sql[] = 'ON     kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME';
		$sql[] = 'WHERE  kcu.TABLE_SCHEMA = ? AND rc.CONSTRAINT_SCHEMA = ?';
		$sql[] = 'AND    kcu.TABLE_NAME = ? AND rc.TABLE_NAME = ?';

		return join( "\n", $sql );
	}

	protected function showForeign( string $table ): array {
		$query  = $this->prepare( $this->showForeignQuery( $table ) );
		$values = [ $this->dbname, $this->dbname, $table, $table ];

		return $this->select( $query, $values );
	}

	protected function alterForeignQuery( string $class ): array {
		$foreign = $this->getDeltaForeign( $class );

		// Drop foreign keys.
		foreach ( array_keys( $foreign['drop'] ) as $name ) {
			$drop[] = sprintf( 'DROP FOREIGN KEY %s', $this->name( $name ) );
		}

		// Create foreign keys.
		foreach ( $foreign['create'] as $index ) {
			$create[] = sprintf( 'ADD %s', $this->defineForeignQuery( $index ) );
		}

		if ( isset( $drop ) ) {
			$drop  = "\n" . join( ",\n", $drop );
			$table = $this->name( $this->getTableName( $class ) );
			$sql[] = sprintf( 'ALTER TABLE %s %s', $table, $drop );
		}

		if ( isset( $create ) ) {
			$create = "\n" . join( ",\n", $create );
			$table  = $this->name( $this->getTableName( $class ) );
			$sql[]  = sprintf( 'ALTER TABLE %s %s', $table, $create );
		}

		return $sql ?? [];
	}

	protected function alterForeign( string $class ): bool {
		foreach ( $this->alterForeignQuery( $class ) as $query ) {
			$this->exec( $query );
		}

		return isset( $query );
	}

	protected function defineForeignQuery( array $index ): string {

		// Index name and columns.
		$name    = $this->name( $index['name'] );
		$columns = join( ', ', array_map( [ $this, 'name' ], $index['columns'] ) );

		// Reference table and columns.
		$table  = $this->name( $index['table'] );
		$fields = join( ', ', array_map( [ $this, 'name' ], $index['fields'] ) );

		// ON UPDATE / DELETE actions.
		$update = $index['update'] ?? null;
		$delete = $index['delete'] ?? null;

		$sql[] = sprintf( 'CONSTRAINT %s FOREIGN KEY (%s)', $name, $columns );
		$sql[] = sprintf( 'REFERENCES %s (%s)', $table, $fields );

		if ( $update ) {
			$sql[] = sprintf( 'ON UPDATE %s', $update );
		}

		if ( $delete ) {
			$sql[] = sprintf( 'ON DELETE %s', $delete );
		}

		return join( ' ', $sql );
	}

	protected function getTableForeign( string $table ): array {
		$constraints = $this->showForeign( $table );
		$constraints = array_column( $constraints, null, 'CONSTRAINT_NAME' );
		$result      = [];

		foreach ( $constraints as $name => $constraint ) {
			$result[ $name ] = [
				'name'    => $name,
				'type'    => 'FOREIGN',
				'columns' => [ $constraint['COLUMN_NAME'] ],
				'table'   => $constraint['REFERENCED_TABLE_NAME'],
				'fields'  => [ $constraint['REFERENCED_COLUMN_NAME'] ],
				'update'  => $constraint['UPDATE_RULE'],
				'delete'  => $constraint['DELETE_RULE'],
			];
		}

		return $result;
	}

	/**
	 * Get model constraints.
	 *
	 * @param ModelInterface|string $class
	 *
	 * @return array
	 */
	protected function getModelForeign( string $class ): array {
		foreach ( $class::properties() as $id => $property ) {
			if ( Utils::isForeign( $property ) ) {
				$model    = $property[ PropertyItem::MODEL ] ?? null;
				$foreign  = $property[ PropertyItem::FOREIGN ] ?? $model;
				$required = $property[ PropertyItem::REQUIRED ] ?? false;
				$name     = $this->getTableName( $class ) . '_' . $id;

				$result[ $name ] = [
					'name'    => $name,
					'type'    => 'FOREIGN',
					'columns' => [ $id ],
					'table'   => $this->getTableName( $foreign ),
					'fields'  => [ $foreign::idProperty() ],
					'update'  => 'CASCADE',
					'delete'  => $required ? 'CASCADE' : 'SET NULL',
				];
			}
		}

		return $result ?? [];
	}

	/**
	 * @param ModelInterface|string $class
	 *
	 * @return array
	 */
	protected function getDeltaForeign( string $class ): array {
		$tableConstraints = $this->getTableForeign( $this->getTableName( $class ) );
		$modelConstraints = Utils::isModel( $class )
			? $this->getModelForeign( $class )
			: $this->getRelationForeign( $class );

		$common = array_intersect_key( $modelConstraints, $tableConstraints );
		$drop   = array_diff_key( $tableConstraints, $modelConstraints );
		$create = array_diff_key( $modelConstraints, $tableConstraints );

		// Check for differences.
		foreach ( $common as $name => $modelConstraint ) {
			$tableConstraint = $tableConstraints[ $name ];
			$modelJson       = Utils::encode( $modelConstraint );
			$tableJson       = Utils::encode( $tableConstraint );

			if ( $modelJson !== $tableJson ) {
				$drop[ $name ]   = $modelConstraint;
				$create[ $name ] = $tableConstraint;
			}
		}

		return compact( 'drop', 'create' );
	}

	/* -------------------------------------------------------------------------
	 * Model relations
	 * ---------------------------------------------------------------------- */

	protected function getRelationName( string $class, string $child ): string {
		$right = current( array_reverse( explode( '\\', $child ) ) );
		return $class . '\\' . $right;
	}

	protected function getRelationColumns( string $relation ): array {
		return $this->relations[ $relation ]['columns'] ?? [];
	}

	protected function getRelationIndexes( string $relation ): array {
		return $this->relations[ $relation ]['indexes'] ?? [];
	}

	protected function getRelationForeign( string $relation ): array {
		return $this->relations[ $relation ]['foreign'] ?? [];
	}

	/**
	 * Adds a model relation table to the processing stack.
	 *
	 * @param ModelInterface|string $class
	 * @param ModelInterface|string $child
	 */
	protected function addRelation( string $class, string $child ): bool {
		$left     = current( array_reverse( explode( '\\', $class ) ) );
		$right    = current( array_reverse( explode( '\\', $child ) ) );
		$relation = $class . '\\' . $right;
		$reverse  = $child . '\\' . $left;

		// No need to continue when the relation already exists.
		if ( array_key_exists( $relation, $this->relations ) ) {
			return true;
		}

		$modelPrimary = $class::getProperty( $class::idProperty() );
		$childPrimary = $child::getProperty( $child::idProperty() );

		// A valid primary key is required for both sides of the relation table.
		if ( empty( $modelPrimary ) || empty( $childPrimary ) ) {
			return false;
		}

		$leftColumn   = $left;
		$rightColumn  = $right;
		$leftForeign  = $this->getTableName( $relation );
		$rightForeign = $this->getTableName( $reverse );

		$columns[ $leftColumn ] = [
			'name'     => $leftColumn,
			'type'     => $this->getColumnType( $modelPrimary ),
			'required' => true,
			'default'  => null,
		];

		$columns[ $rightColumn ] = [
			'name'     => $rightColumn,
			'type'     => $this->getColumnType( $childPrimary ),
			'required' => true,
			'default'  => null,
		];

		$indexes[ $leftColumn ] = [
			'name'    => $leftColumn,
			'type'    => 'INDEX',
			'columns' => [ $leftColumn ],
		];

		$indexes[ $rightColumn ] = [
			'name'    => $rightColumn,
			'type'    => 'INDEX',
			'columns' => [ $rightColumn ],
		];

		$foreign[ $leftForeign ] = [
			'name'    => $leftForeign,
			'type'    => 'FOREIGN',
			'columns' => [ $leftColumn ],
			'table'   => $this->getTableName( $class ),
			'fields'  => [ $class::idProperty() ],
			'update'  => 'CASCADE',
			'delete'  => 'CASCADE',
		];

		$foreign[ $rightForeign ] = [
			'name'    => $rightForeign,
			'type'    => 'FOREIGN',
			'columns' => [ $rightColumn ],
			'table'   => $this->getTableName( $child ),
			'fields'  => [ $child::idProperty() ],
			'update'  => 'CASCADE',
			'delete'  => 'CASCADE',
		];

		$this->relations[ $relation ] = compact( 'columns', 'indexes', 'foreign' );
		return true;
	}

	/**
	 * Updates a relation between parent and child.
	 *
	 * @param ModelInterface $model
	 * @param ModelInterface|string $child
	 * @param ModelInterface[] $list
	 *
	 * @return bool
	 */
	protected function updateRelation( ModelInterface $model, string $child, array $list ): bool {
		$left  = current( array_reverse( explode( '\\', get_class( $model ) ) ) );
		$right = current( array_reverse( explode( '\\', $child ) ) );
		$table = $this->getTableName( get_class( $model ) . '\\' . $right );

		$list = array_map( [ $this, 'set' ], $list );
		$list = array_column( $list, null, $child::idProperty() );

		$select   = $this->selectRelationStatement( $table, $left );
		$existing = $this->select( $select, [ $model->id() ] );
		$existing = array_column( $existing, null, $model::idProperty() );
		$common   = array_intersect_key( $list, $existing );

		if ( count( $common ) < count( $existing ) ) {
			$delete = $this->deleteRelationStatement( $table, $left );
			$insert = $this->insertRelationStatement( $table );
			$rows   = $this->update( $delete, [ $model->id() ] );

			foreach ( $list as $item ) {
				$rows = $this->update( $insert, [ $model->id(), $item->id() ] );
			}
		} elseif ( count( $common ) < count( $list ) ) {
			$insert = $this->insertRelationStatement( $table );
			$added  = array_diff_key( $list, $existing );

			foreach ( $added as $item ) {
				$rows = $this->update( $insert, [ $model->id(), $item->id() ] );
			}
		}

		return true;
	}

	/* -------------------------------------------------------------------------
	 * Select, insert and delete relations.
	 * ---------------------------------------------------------------------- */

	/**
	 * Gets a prepared select statement for the given relation table.
	 *
	 * @param string $table A relation table name.
	 *
	 * @return PDOStatement
	 */
	protected function selectRelationStatement( string $table, string $left ): object {
		if ( empty( $this->queries[ $table ]['select'] ) ) {
			$query = sprintf( 'SELECT * FROM %s WHERE %s = ?', $this->name( $table ), $this->name( $left ) );;
			$this->queries[ $table ]['select'] = $this->prepare( $query );
		}
		return $this->queries[ $table ]['select'];
	}

	/**
	 * Gets a prepared insert statement for the given relation table.
	 *
	 * @param string $table A relation table name.
	 *
	 * @return PDOStatement
	 */
	protected function insertRelationStatement( string $table ): object {
		if ( empty( $this->queries[ $table ]['insert'] ) ) {
			$query = sprintf( 'INSERT INTO %s VALUES (?, ?)', $this->name( $table ) );;
			$this->queries[ $table ]['insert'] = $this->prepare( $query );
		}
		return $this->queries[ $table ]['insert'];
	}

	/**
	 * Gets a prepared delete statement for the given relation table.
	 *
	 * @param string $table A relation table name.
	 *
	 * @return PDOStatement
	 */
	protected function deleteRelationStatement( string $table, string $left ): object {
		if ( empty( $this->queries[ $table ]['delete'] ) ) {
			$query = sprintf( 'DELETE FROM %s WHERE %s = ?', $this->name( $table ), $this->name( $left ) );;
			$this->queries[ $table ]['delete'] = $this->prepare( $query );
		}
		return $this->queries[ $table ]['delete'];
	}

	/**
	 * Gets a prepared select statement for the given relation table.
	 *
	 * @param ModelInterface|string $class A relation table name.
	 * @param ModelInterface|string $child A relation table name.
	 *
	 * @return PDOStatement
	 */
	protected function selectChildrenStatement( string $class, string $child ): object {
		$left  = current( array_reverse( explode( '\\', $class ) ) );
		$right = current( array_reverse( explode( '\\', $child ) ) );
		$table = $this->getTableName( $class . '\\' . $right );

		if ( empty( $this->queries[ $table ]['children'] ) ) {
			$name    = $this->name( $table );
			$source  = $this->name( $this->getTableName( $child ) );
			$primary = $this->name( $child::idProperty() );
			$left    = $this->name( $left );
			$right   = $this->name( $right );

			$sql[] = "SELECT C.*";
			$sql[] = "FROM   {$name} as R";
			$sql[] = "JOIN   {$source} as C";
			$sql[] = "ON     R.{$right} = C.{$primary}";
			$sql[] = "WHERE  R.{$left} = ?";

			$sql = join( "\n", $sql );;
			$this->queries[ $table ]['children'] = $this->prepare( $sql );
		}
		return $this->queries[ $table ]['children'];
	}

	/* -------------------------------------------------------------------------
	 * Select, insert, update and delete rows.
	 * ---------------------------------------------------------------------- */

	/**
	 * Generates an exists query template.
	 *
	 * @param string $table
	 * @param string $primary
	 *
	 * @return string
	 */
	protected function existsRowQuery( string $table, string $primary ): string {
		return vsprintf( 'SELECT %1$s FROM %2$s WHERE %1$s = ?', [
			$this->name( $primary ),
			$this->name( $table ),
		] );
	}

	/**
	 * Gets a prepared statement to check if a model exists.
	 *
	 * @param ModelInterface|string $class The model class name.
	 *
	 * @return PDOStatement|null
	 */
	protected function existsRowStatement( string $class ): ?object {
		$table = $this->getTableName( $class );

		if ( empty( $this->queries[ $table ]['exists'] ) ) {
			$query = $this->existsRowQuery( $table, $class::idProperty() );;
			$this->queries[ $table ]['exists'] = $this->prepare( $query );
		}
		return $this->queries[ $table ]['exists'];
	}

	/**
	 * Generates a select query template.
	 *
	 * @param string $table
	 * @param string $primary
	 *
	 * @return string
	 */
	protected function selectRowQuery( string $table, string $primary ): string {
		$table   = $this->name( $table );
		$primary = $this->name( $primary );

		return "SELECT * FROM {$table} WHERE {$primary} = ?";
	}

	/**
	 * Gets a prepared select statement for the given model.
	 *
	 * @param ModelInterface|string $class The model class name.
	 *
	 * @return PDOStatement|null
	 */
	protected function selectRowStatement( string $class ): ?object {
		$table = $this->getTableName( $class );

		if ( empty( $this->queries[ $table ]['select'] ) ) {
			$primary = $class::idProperty();
			$query   = $this->selectRowQuery( $table, $primary );;
			$this->queries[ $table ]['select'] = $this->prepare( $query );
		}
		return $this->queries[ $table ]['select'];
	}

	/**
	 * Generates a select query template.
	 *
	 * @param string $table
	 * @param string $primary
	 * @param int $count
	 *
	 * @return string
	 */
	protected function collectRowsQuery( string $table, string $primary, int $count ): string {
		$table   = $this->name( $table );
		$primary = $this->name( $primary );
		$ids     = join( ', ', array_fill( 0, $count, '?' ) );

		return "SELECT * FROM {$table} WHERE {$primary} IN ({$ids})";
	}

	/**
	 * Gets a prepared statement to check if a model exists.
	 *
	 * @param ModelInterface|string $class The model class name.
	 *
	 * @return PDOStatement|null
	 */
	protected function collectRowsStatement( string $class, int $count ): ?object {
		$table   = $this->getTableName( $class );
		$primary = $class::idProperty();
		$query   = $this->collectRowsQuery( $table, $primary, $count );

		return $this->prepare( $query );
	}

	/**
	 * Generates an exists query template.
	 *
	 * @param string $table
	 *
	 * @return string
	 */
	protected function selectListQuery( string $table ): string {
		$table = $this->name( $table );
		return "SELECT * FROM {$table}";
	}

	/**
	 * Gets a prepared statement to check if a model exists.
	 *
	 * @param ModelInterface|string $class The model class name.
	 *
	 * @return PDOStatement|null
	 */
	protected function selectListStatement( string $class ): ?object {
		$table = $this->getTableName( $class );

		if ( empty( $this->queries[ $table ]['list'] ) ) {
			$query = $this->selectListQuery( $table );;
			$this->queries[ $table ]['list'] = $this->prepare( $query );
		}
		return $this->queries[ $table ]['list'] ?? null;
	}

	/**
	 * Generates an exists query template.
	 *
	 * @param string $table
	 * @param string $primary
	 *
	 * @return string
	 */
	protected function selectFilterQuery( string $table, array $filter ): string {
		$table = $this->name( $table );
		$sql   = [];

		foreach ( array_keys( $filter ) as $column ) {
			$sql[] = sprintf( '(%s = :%s)', $this->name( $column ), $column );
		}

		$sql = join( "\nAND ", $sql );
		return "SELECT * FROM {$table} \nWHERE {$sql}";
	}

	/**
	 * Gets a prepared statement to check if a model exists.
	 *
	 * @param ModelInterface|string $class The model class name.
	 *
	 * @return PDOStatement|null
	 */
	protected function selectFilterStatement( string $class, array $filter ): ?object {
		$table = $this->getTableName( $class );
		$query = $this->selectFilterQuery( $table, $filter );
		return $this->prepare( $query );
	}

	/**
	 * Generates an update query template.
	 *
	 * @param string $table
	 * @param array $properties
	 *
	 * @return string
	 */
	protected function insertRowQuery( string $table, array $properties ): string {
		$columns = [];
		$values  = [];

		foreach ( $properties as $id => $property ) {
			if ( Utils::isColumn( $property ) ) {
				$columns[] = $this->name( $id );
				$values[]  = ':' . $id;
			}
		}

		$table   = $this->name( $table );
		$columns = join( ', ', $columns );
		$values  = join( ', ', $values );

		return "INSERT INTO {$table} ({$columns}) VALUES ({$values})";
	}

	/**
	 * Gets a prepared update statement for the given model.
	 *
	 * @param ModelInterface|string $class The model class name.
	 *
	 * @return PDOStatement
	 */
	protected function insertRowStatement( string $class ): object {
		$table = $this->getTableName( $class );

		if ( empty( $this->queries[ $table ]['insert'] ) ) {
			$properties = $class::properties();
			$query      = $this->insertRowQuery( $table, $properties );;
			$this->queries[ $table ]['insert'] = $this->prepare( $query );
		}
		return $this->queries[ $table ]['insert'];
	}

	/**
	 * Generates an update query template.
	 *
	 * @param string $table
	 * @param array $properties
	 * @param string $primary
	 *
	 * @return string
	 */
	protected function updateRowQuery( string $table, array $properties, string $primary ): string {
		$sql = [];

		foreach ( $properties as $id => $property ) {
			if ( Utils::isColumn( $property ) && $id !== $primary ) {
				$name  = $this->name( $id );
				$sql[] = "{$name} = :{$id}";
			}
		}

		$table = $this->name( $table );
		$where = $this->name( $primary );
		$sql   = "\n\t" . join( ",\n\t", $sql ) . "\n";

		return "UPDATE {$table} SET {$sql}WHERE {$where} = :{$primary}";
	}

	/**
	 * Gets a prepared update statement for the given model.
	 *
	 * @param ModelInterface|string $class The model class name.
	 *
	 * @return PDOStatement
	 */
	protected function updateRowStatement( string $class ): object {
		$table = $this->getTableName( $class );

		if ( empty( $this->queries[ $table ]['update'] ) ) {
			$properties = $class::properties();
			$primary    = $class::idProperty();
			$query      = $this->updateRowQuery( $table, $properties, $primary );

			$this->queries[ $table ]['update'] = $this->prepare( $query );
		}
		return $this->queries[ $table ]['update'];
	}

	/**
	 * Generates an delete query template.
	 *
	 * @param string $table
	 * @param string $primary
	 *
	 * @return string
	 */
	protected function deleteRowQuery( string $table, string $primary ): string {
		$table   = $this->name( $table );
		$primary = $this->name( $primary );

		return "DELETE FROM {$table} WHERE {$primary} = ?";
	}

	/**
	 * Gets a prepared update statement for the given model.
	 *
	 * @param ModelInterface|string $class The model class name.
	 *
	 * @return PDOStatement
	 */
	protected function deleteRowStatement( string $class ): object {
		$table = $this->getTableName( $class );

		if ( empty( $this->queries[ $table ]['delete'] ) ) {
			$primary = $class::idProperty();
			$query   = $this->deleteRowQuery( $table, $primary );;
			$this->queries[ $table ]['delete'] = $this->prepare( $query );
		}
		return $this->queries[ $table ]['delete'];
	}

	/* -------------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------------- */

	/**
	 * Gets the table name corresponding to the given model.
	 */
	protected function getTableName( string $class ): string {
		$class = str_replace( 'Silverscreen\\', '', $class );
		return str_replace( '\\', '_', $class );
	}

	protected function getModelValues( ModelInterface $model ): array {
		foreach ( $model::properties() as $id => $property ) {
			if ( Utils::isColumn( $property ) ) {
				$result[ $id ] = $this->getPropertyValue( $model[ $id ], $property );
			}
		}
		return $result ?? [];
	}

	protected function getPropertyValue( $value, array $property ) {
		$type  = $property[ PropertyItem::TYPE ] ?? PropertyType::MIXED;
		$child = $property[ PropertyItem::MODEL ] ?? null;

		// Short-circuit null values.
		if ( is_null( $value ) ) {
			return null;
		}

		// Transform boolean values.
		if ( is_bool( $value ) ) {
			return (int) $value;
		}

		// Transform objects.
		if ( PropertyType::OBJECT === $type ) {
			if ( Utils::isModel( $child ) ) {
				if ( $child::idProperty() ) {
					return $this->set( $value )->id();
				}
				return Utils::encode( $value->data( ModelData::COMPACT ) );
			}
			return Utils::encode( $value );
		}

		// Transform arrays.
		if ( PropertyType::ARRAY === $type ) {
			if ( Utils::isModel( $child ) ) {
				$callback = fn( $item ) => $item->data( ModelData::COMPACT );
				return Utils::encode( array_map( $callback, $value ) );
			}
			return Utils::encode( $value );
		}

		return $value;
	}

	protected function getModelRelations( ModelInterface $model ): array {
		foreach ( $model::properties() as $id => $property ) {
			if ( Utils::isRelation( $property ) ) {
				$child            = $property[ PropertyItem::MODEL ];
				$result[ $child ] = $model[ $id ] ?? [];
			}
		}

		return $result ?? [];
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

		foreach ( $models as $class ) {
			foreach ( $class::properties() as $property ) {
				$foreign = $property[ PropertyItem::FOREIGN ] ?? null;
				$model   = $property[ PropertyItem::MODEL ] ?? $foreign;

				if ( Utils::isModel( $model ) && $model::idProperty() ) {
					if ( empty( in_array( $model, $result, true ) ) ) {
						$result[] = $model;
						$this->getAllModels( [ $model ], $result );
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Extract all sub-models from the given model.
	 *
	 * @param ModelInterface|string $class A model class name.
	 * @param bool $include Whether to include the given model in the result or not.
	 *
	 * @return ModelInterface|string[] An array of sub-model classes of the given model class.
	 */
	protected function getSubModels( string $class, bool $include = true ): array {
		$result = $include ? [ $class ] : [];

		foreach ( $class::properties() as $property ) {
			$foreign = $property[ PropertyItem::FOREIGN ] ?? null;
			$model   = $property[ PropertyItem::MODEL ] ?? $foreign;

			if ( Utils::isModel( $model ) && $model::idProperty() ) {
				if ( empty( in_array( $model, $result, true ) ) ) {
					$result = array_merge( $result, $this->getSubModels( $model ) );
				}
			}
		}

		return $result;
	}

	protected static function getForeignProperties( array $properties ): array {
		return array_filter( $properties, function( array $property ): bool {
			$type  = $property[ PropertyItem::TYPE ] ?? PropertyType::MIXED;
			$model = $property[ PropertyItem::MODEL ] ?? null;

			if ( PropertyType::OBJECT === $type || PropertyType::ARRAY === $type ) {
				if ( Utils::isModel( $model ) && $model::idProperty() ) {
					return true;
				}
			}
			return false;
		} );
	}
}
