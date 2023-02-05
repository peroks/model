<?php namespace Peroks\Model;

use PDO, PDOException, PDOStatement;

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

	/**
	 * @inheritDoc
	 */
	public function get( string $id, string $class, bool $create = true ): ?ModelInterface {
		$table    = $this->name( $this->getTableName( $class ) );
		$primary  = $this->name( $class::idProperty() );
		$children = static::getChildModels( $class );

		$sql  = "SELECT * FROM {$table} WHERE {$primary} = ?";
		$data = $this->query( $sql, [ $id ] );

		if ( $data ) {
			foreach ( $children as $id => $property ) {
				$child       = $property[ PropertyItem::MODEL ];
				$value       = $data[ $id ];
				$data[ $id ] = $this->get( $value, $child );

			}
			return $class::create( $data );
		}
		return null;
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
		$model->validate( true );

		$values    = $this->getModelValues( $model );
		$relations = $this->getModelRelations( $model );

		try {
			$query = $this->insertRowStatement( get_class( $model ) );
			$rows  = $this->update( $query, $values );
		} catch ( PDOException $e ) {
			$query = $this->updateRowStatement( get_class( $model ) );
			$rows  = $this->update( $query, $values );
		}

		foreach ( $relations as $child => $list ) {
			$this->updateRelation( $model, $child, $list );
		}

		return $model;
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

	public function build( array $models ): int {
		$models = $this->getAllModels( $models );
		return $this->buildDatabase( $models );
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
			PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_PERSISTENT => true,
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
	 * @return bool
	 */
	protected function exec( string $query ): bool {
		$this->db->exec( $query );
		return true;
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
	 * @return bool True on success or if the database already exists, false otherwise.
	 */
	protected function createDatabase( string $name ): bool {
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
	 * @return bool True on success or if the database doesn't exist, false otherwise.
	 */
	protected function dropDatabase( string $name ): bool {
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
		$tables = $this->showTableNames();
		$count  = 0;

		// Create missing tables + columns for models.
		foreach ( $models as $class ) {
			$count += (int) $this->createTable( $class );
		}

		// Create missing tables + columns for relations.
		foreach ( array_keys( $this->relations ) as $relation ) {
			$count += (int) $this->createTable( $relation );
		}

		// Merge all models and relations.
		$all = array_merge( $models, array_keys( $this->relations ) );

		// Apply table indexes after all tables + columns are in place.
		foreach ( $all as $class ) {
			$count += (int) $this->alterTable( $class );
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
		$columns = Utils::isModel( $class )
			? $this->getModelColumns( $class )
			: $this->getRelationColumns( $class );

		foreach ( $columns as $column ) {
			$sql[] = $this->defineColumnQuery( $column );
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
	 * @return bool True if the table was created or already exists, false otherwise.
	 */
	protected function createTable( string $class ): bool {
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
	 * @return bool True if the table was successfully updated, false otherwise.
	 */
	protected function alterTable( string $class ): bool {
		if ( $query = $this->alterTableQuery( $class ) ) {
			return $this->exec( $query );
		}
		return false;
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
		$required = $column['required'] ?? null;
		$default  = $column['default'] ?? null;

		// Cast default value to string or integer.
		if ( isset( $default ) ) {
			$default = is_string( $default ) ? $this->quote( $default ) : (int) $default;
		}

		return join( ' ', array_filter( [
			$this->name( $column['name'] ),
			$column['type'],
			$required ? 'NOT NULL' : null,
			isset( $default ) ? "DEFAULT {$default}" : null,
		] ) );
	}

	protected function getTableColumns( string $table ): array {
		$columns = $this->showColumns( $table );
		$result  = [];

		foreach ( $columns as $column ) {
			$name = $column['Field'];

			$result[ $name ] = [
				'name'     => $name,
				'type'     => $column['Type'],
				'required' => $column['Null'] === 'NO',
				'default'  => $column['Default'],
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
			$type    = $property[ PropertyItem::TYPE ] ?? PropertyType::MIXED;
			$child   = $property[ PropertyItem::MODEL ] ?? null;
			$default = $property[ PropertyItem::DEFAULT ] ?? null;

			// Storing functions is not supported.
			if ( PropertyType::FUNCTION === $type ) {
				continue;
			}

			// Arrays of models require a separate relation table.
			if ( PropertyType::ARRAY === $type ) {
				if ( Utils::isModel( $child ) && Utils::getModelPrimary( $child ) ) {
					$this->addRelation( $class, $child );
					continue;
				}
			}

			if ( empty( is_scalar( $default ) ) ) {
				$default = null;
			}

			if ( PropertyType::UUID === $type && true === $default ) {
				$default = null;
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
				return 'bool';
			case PropertyType::INTEGER:
				return 'bigint';
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
			case 'FOREIGN':
				return $this->defineForeignQuery( $index );
			case 'UNIQUE':
				return sprintf( 'UNIQUE %s (%s)', $name, $columns );
			default:
				return sprintf( 'INDEX %s (%s)', $name, $columns );
		}
	}

	protected function defineForeignQuery( array $index ): string {

		// Index name and columns.
		$name    = $this->name( $index['name'] );
		$columns = join( ', ', array_map( [ $this, 'name' ], $index['columns'] ) );

		// Reference table and columns.
		$table = $this->name( $index['table'] );
		$match = join( ', ', array_map( [ $this, 'name' ], $index['match'] ) );

		// ON UPDATE / DELETE actions.
		$update = $index['update'] ?? null;
		$delete = $index['delete'] ?? null;

		$sql[] = sprintf( 'FOREIGN KEY %s (%s)', $name, $columns );
		$sql[] = sprintf( 'REFERENCES %s (%s)', $table, $match );

		if ( $update ) {
			$sql[] = sprintf( 'ON UPDATE %s', $update );
		}

		if ( $delete ) {
			$sql[] = sprintf( 'ON DELETE %s', $delete );
		}

		return join( ' ', $sql );
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

		if ( $primary && array_key_exists( $primary, $properties ) ) {
			$result['PRIMARY'] = [ 'name' => 'PRIMARY', 'type' => 'PRIMARY', 'columns' => [ $primary ] ];
		}

		foreach ( $properties as $id => $property ) {
			$index = $property[ PropertyItem::INDEX ] ?? null;
			$name  = $index ?? $property[ PropertyItem::UNIQUE ] ?? null;

			if ( $name ) {
				$result[ $name ]['name']      = $name;
				$result[ $name ]['type']      = $index ? 'INDEX' : 'UNIQUE';
				$result[ $name ]['columns'][] = $id;
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

			if ( $modelJson !== $tableJson && $modelIndex['type'] !== 'FOREIGN' ) {
				$drop[ $name ]   = $tableIndex;
				$create[ $name ] = $modelIndex;
			}
		}

		return compact( 'drop', 'create' );
	}

	/* -------------------------------------------------------------------------
	 * Model relations
	 * ---------------------------------------------------------------------- */

	protected function getRelationColumns( string $relation ): array {
		return $this->relations[ $relation ]['columns'] ?? [];
	}

	protected function getRelationIndexes( string $relation ): array {
		return $this->relations[ $relation ]['indexes'] ?? [];
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

		// No need to continue when the relation already exists.
		if ( array_key_exists( $relation, $this->relations ) ) {
			return true;
		}

		$modelPrimary = Utils::getModelPrimary( $class );
		$childPrimary = Utils::getModelPrimary( $child );

		// A valid primary key is required for both sides of the relation table.
		if ( empty( $modelPrimary ) || empty( $childPrimary ) ) {
			return false;
		}

		$columns[ $left ] = [
			'name'     => $left,
			'type'     => $this->getColumnType( $modelPrimary ),
			'required' => true,
			'default'  => null,
		];

		$columns[ $right ] = [
			'name'     => $right,
			'type'     => $this->getColumnType( $childPrimary ),
			'required' => true,
			'default'  => null,
		];

		$indexes[ $left ] = [
			'name'    => $left,
			'type'    => 'FOREIGN',
			'columns' => [ $left ],
			'table'   => $this->getTableName( $class ),
			'match'   => [ $modelPrimary->id() ],
			'delete'  => 'CASCADE',
		];

		$indexes[ $right ] = [
			'name'    => $right,
			'type'    => 'FOREIGN',
			'columns' => [ $right ],
			'table'   => $this->getTableName( $child ),
			'match'   => [ $childPrimary->id() ],
			'delete'  => 'CASCADE',
		];

		$this->relations[ $relation ] = compact( 'columns', 'indexes' );
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
		$list  = array_column( $list, null, $child::idProperty() );

		foreach ( $list as $item ) {
			$this->set( $item );
		}

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
	 * Gets a prepared insert statement for the given relation table.
	 *
	 * @param string $table A relation table name.
	 *
	 * @return PDOStatement
	 */
	protected function insertRelationStatement( string $table ): object {
		if ( empty( $this->queries[ $table ]['insert'] ) ) {
			$name  = $this->name( $table );
			$query = "INSERT INTO {$name} VALUES (?, ?)";;
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
			$name  = $this->name( $table );
			$query = "DELETE FROM {$name} WHERE {$left} = ?";;
			$this->queries[ $table ]['delete'] = $this->prepare( $query );
		}
		return $this->queries[ $table ]['delete'];
	}

	/**
	 * Gets a prepared select statement for the given relation table.
	 *
	 * @param string $table A relation table name.
	 *
	 * @return PDOStatement
	 */
	protected function selectRelationStatement( string $table, string $left ): object {
		if ( empty( $this->queries[ $table ]['select'] ) ) {
			$name  = $this->name( $table );
			$query = "SELECT * FROM {$name} WHERE {$left} = ?";;
			$this->queries[ $table ]['select'] = $this->prepare( $query );
		}
		return $this->queries[ $table ]['select'];
	}

	/* -------------------------------------------------------------------------
	 * Select, insert, update and delete rows.
	 * ---------------------------------------------------------------------- */

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
			$type  = $property[ PropertyItem::TYPE ] ?? PropertyType::MIXED;
			$child = $property[ PropertyItem::MODEL ] ?? null;

			if ( PropertyType::ARRAY === $type ) {
				if ( Utils::isModel( $child ) && Utils::getModelPrimary( $child ) ) {
					continue;
				}
			}

			$columns[] = $this->name( $id );
			$values[]  = ':' . $id;
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
			$type  = $property[ PropertyItem::TYPE ] ?? PropertyType::MIXED;
			$child = $property[ PropertyItem::MODEL ] ?? null;

			// Don't update the primary key.
			if ( $id === $primary ) {
				continue;
			}

			if ( PropertyType::ARRAY === $type ) {
				if ( Utils::isModel( $child ) && Utils::getModelPrimary( $child ) ) {
					continue;
				}
			}

			$name  = $this->name( $id );
			$sql[] = "{$name} = :{$id}";
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

	/* -------------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------------- */

	/**
	 * Gets the table name corresponding to the given model.
	 */
	protected function getTableName( string $class ): string {
		return str_replace( '\\', '_', $class );
	}

	protected function getModelValues( ModelInterface $model ): array {
		foreach ( $model::properties() as $id => $property ) {
			$type  = $property[ PropertyItem::TYPE ] ?? PropertyType::MIXED;
			$child = $property[ PropertyItem::MODEL ] ?? null;
			$value = $model[ $id ];

			// Storing functions is not supported.
			if ( PropertyType::FUNCTION === $type ) {
				continue;
			}

			// Storing objects.
			if ( PropertyType::OBJECT === $type && isset( $value ) ) {
				if ( Utils::isModel( $child ) ) {
					if ( Utils::getModelPrimary( $child ) ) {
						$result[ $id ] = $this->set( $value )->id();
						continue;
					}
					$result[ $id ] = Utils::encode( $value->data( ModelData::COMPACT ) );
					continue;
				}
				$result[ $id ] = Utils::encode( $value );
				continue;
			}

			if ( PropertyType::ARRAY === $type ) {
				if ( Utils::isModel( $child ) ) {
					if ( Utils::getModelPrimary( $child ) ) {
						// Arrays of models are stored in a separate relation table.
						continue;
					}
					if ( isset( $value ) ) {
						$compact       = array_map( fn( $item ) => $item->data( ModelData::COMPACT ), $value );
						$result[ $id ] = Utils::encode( $compact );
						continue;
					}
				}
				if ( isset( $value ) ) {
					$result[ $id ] = Utils::encode( $value );
					continue;
				}
			}

			if ( is_bool( $value ) ) {
				$value = (int) $value;
			}

			$result[ $id ] = $value;
		}
		return $result ?? [];
	}

	protected function getModelRelations( ModelInterface $model ): array {
		foreach ( $model::properties() as $id => $property ) {
			$type  = $property[ PropertyItem::TYPE ] ?? PropertyType::MIXED;
			$child = $property[ PropertyItem::MODEL ] ?? null;

			if ( PropertyType::ARRAY === $type ) {
				if ( Utils::isModel( $child ) && Utils::getModelPrimary( $child ) ) {
					$result[ $child ] = $model[ $id ] ?? [];
				}
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
				$foreign = $property[ PropertyItem::MODEL ] ?? $property[ PropertyItem::FOREIGN ] ?? null;

				if ( Utils::isModel( $foreign ) && Utils::getModelPrimary( $foreign ) ) {
					if ( empty( in_array( $foreign, $result, true ) ) ) {
						$result[] = $foreign;
						$this->getAllModels( [ $foreign ], $result );
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
			$foreign = $property[ PropertyItem::MODEL ] ?? $property[ PropertyItem::FOREIGN ] ?? null;

			if ( Utils::isModel( $foreign ) && Utils::getModelPrimary( $foreign ) ) {
				if ( empty( in_array( $foreign, $result, true ) ) ) {
					$result = array_merge( $result, $this->getSubModels( $foreign ) );
				}
			}
		}

		return $result;
	}

	/**
	 * @param ModelInterface|string $class
	 *
	 * @return array An array of child model properties keyed by the child class name.
	 */
	protected function getChildModels( string $class ): array {
		return array_filter( $class::properties(), function( array $property ) {
			return Utils::isModel( $property[ PropertyItem::MODEL ] ?? null );
		} );
	}
}
