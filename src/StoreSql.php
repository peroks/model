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
			//	$this->createTables( $models );
			$this->updateTables( $models );
			return true;
		}

		return false;
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
		return sprintf( 'CREATE DATABASE IF NOT EXISTS %s', $this->name( $name ) );
	}

	/**
	 * Creates a new database with the given name.
	 *
	 * @param string $name The database name.
	 *
	 * @return bool True on success or if the database already exists, false otherwise.
	 */
	protected function createDatabase( string $name ): bool {
		$sql = $this->createDatabaseQuery( $name );
		return $this->exec( $sql );
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
		$sql = $this->dropDatabaseQuery( $name );
		return $this->exec( $sql );
	}

	/* -------------------------------------------------------------------------
	 * Show, create and drop tables
	 * ---------------------------------------------------------------------- */

	protected function showTablesQuery(): string {
		return 'SHOW TABLES;';
	}

	protected function showTables(): array {
		$sql = $this->showTablesQuery();
		return $this->query( $sql, [], PDO::FETCH_COLUMN );
	}

	/**
	 * Generates a query to create a database table for the given model.
	 *
	 * @param ModelInterface|string $model The model to create a database table for.
	 *
	 * @return string Sql query to create a database table.
	 */
	protected function createTableQuery( string $model ): string {
		$columns = $this->getModelColumns( $model );
		$indexes = $this->getModelIndexes( $model );
		$sql     = [];

		// Create columns.
		foreach ( $columns as $column ) {
			$sql[] = $this->defineColumnQuery( $column );
		}

		// Create indexes.
		foreach ( $indexes as $index ) {
			$sql[] = $this->defineIndexQuery( $index );
		}

		$sql   = "\n\t" . join( ",\n\t", $sql ) . "\n";
		$table = $this->name( $this->getTableName( $model ) );

		return sprintf( 'CREATE TABLE IF NOT EXISTS %s (%s)', $table, $sql );
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
	 * Generates a query to update a database table for the given model.
	 *
	 * @param ModelInterface|string $model The model to create a database table for.
	 *
	 * @return string Sql query to update a database table.
	 */
	protected function updateTableQuery( string $model ): string {
		$columns = $this->getDeltaColumns( $model );
		$indexes = $this->getDeltaIndexes( $model );
		$sql     = [];

		// Drop indexes.
		foreach ( array_keys( $indexes['drop'] ) as $name ) {
			$sql[] = sprintf( 'DROP INDEX %s', $name );
		}

		// Drop columns.
		foreach ( array_keys( $columns['drop'] ) as $name ) {
			$sql[] = sprintf( 'DROP COLUMN %s', $name );
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

		$sql   = "\n" . join( ",\n", $sql );
		$table = $this->name( $this->getTableName( $model ) );

		return sprintf( 'ALTER TABLE %s %s', $table, $sql );
	}

	/**
	 * Creates a table for the given model.
	 *
	 * @param ModelInterface|string $model The model to update a table for.
	 *
	 * @return bool True if the table was successfully updated, false otherwise.
	 */
	protected function updateTable( string $model ): bool {
		$sql = $this->updateTableQuery( $model );
		return $this->exec( $sql );
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
		$tables = $this->showTables();
		$count  = 0;

		foreach ( $models as $model ) {
			if ( in_array( $this->getTableName( $model ), $tables ) ) {
				$count += (int) $this->updateTable( $model );
			} else {
				$count += (int) $this->createTable( $model );
			}
		}

		return count( $models ) === $count;
	}

	/* -------------------------------------------------------------------------
	 * Show table columns.
	 * ---------------------------------------------------------------------- */

	protected function showColumnsQuery( string $table ): string {
		return sprintf( 'SHOW COLUMNS FROM %s', $this->name( $table ) );
	}

	protected function showColumns( string $table ): array {
		$sql = $this->showColumnsQuery( $table );
		return $this->query( $sql );
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
	 * Get model indexes.
	 *
	 * @param ModelInterface|string $model
	 *
	 * @return array
	 */
	protected function getModelColumns( string $model ): array {
		$properties = $model::properties();
		$result     = [];

		foreach ( $properties as $id => $property ) {
			$type    = $property[ PropertyItem::TYPE ] ?? PropertyType::MIXED;
			$default = $property[ PropertyItem::DEFAULT ] ?? null;

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
		}

		return $result;
	}

	/**
	 * @param ModelInterface|string $model
	 *
	 * @return array
	 */
	protected function getDeltaColumns( string $model ): array {
		$tableColumns = $this->getTableColumns( $this->getTableName( $model ) );
		$modelColumns = $this->getModelColumns( $model );

		$union  = array_intersect_key( $modelColumns, $tableColumns );
		$drop   = array_diff_key( $tableColumns, $modelColumns );
		$create = array_diff_key( $modelColumns, $tableColumns );
		$alter  = [];

		// Get altered columns.
		foreach ( $union as $name => $column ) {
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
	 * Show, create and update table indexes
	 * ---------------------------------------------------------------------- */

	protected function showIndexesQuery( string $table ): string {
		return vsprintf( 'SHOW INDEXES FROM %s', [
			$this->name( $table ),
		] );
	}

	protected function showIndexQuery( string $table, string $index ): string {
		return vsprintf( 'SHOW INDEX FROM %s WHERE Key_name = %s', [
			$this->name( $table ),
			$this->quote( $index ),
		] );
	}

	protected function createIndexQuery( string $table, string $name, array $columns, string $type = '' ): string {
		$sql[] = sprintf( 'ALTER TABLE %s', $table );
		$sql[] = $this->defineIndexQuery( $name, $columns, $type );

		return join( ' ', $sql );
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

	protected function dropIndexQuery( string $table, string $index ): string {
		return vsprintf( 'DROP INDEX %s ON %s', [
			$this->name( $index ),
			$this->name( $table ),
		] );
	}

	protected function showIndexes( string $table ): array {
		$sql = $this->showIndexesQuery( $table );
		return $this->query( $sql );
	}

	protected function showIndex( string $table, string $index ): array {
		$sql = $this->showIndexQuery( $table, $index );
		return $this->query( $sql );
	}

	protected function createIndex( string $table, string $index, array $fields, string $type = '' ): bool {
		$sql = $this->createIndexQuery( $table, $index, $fields, $type );
		return $this->exec( $sql );
	}

	protected function dropIndex( string $table, string $index ): bool {
		$sql = $this->dropIndexQuery( $table, $index );
		return $this->exec( $sql );
	}

	protected function dropIndexes( string $table ): bool {
		foreach ( $this->getIndexNames( $table ) as $index ) {
			$this->dropIndex( $table, $index );
		}
		return true;
	}

	protected function getIndexNames( string $table ): array {
		$indexes = $this->showIndexes( $table );
		$names   = array_column( $indexes, 'Key_name' );
		return array_values( array_unique( $names ) );
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
	 * @param ModelInterface|string $model
	 *
	 * @return array
	 */
	protected function getModelIndexes( string $model ): array {
		$properties = $model::properties();
		$primary    = $model::idProperty();
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
	 * @param ModelInterface|string $model
	 *
	 * @return array
	 */
	protected function getDeltaIndexes( string $model ): array {
		$tableIndexes = $this->getTableIndexes( $this->getTableName( $model ) );
		$modelIndexes = $this->getModelIndexes( $model );

		$result['drop']   = [];
		$result['create'] = [];

		foreach ( $tableIndexes as $name => $index ) {
			if ( empty( $modelIndexes[ $name ] ) || $modelIndexes[ $name ] !== $index ) {
				$result['drop'][ $name ] = $index;
			}
		}

		foreach ( $modelIndexes as $name => $index ) {
			if ( empty( $tableIndexes[ $name ] ) || $tableIndexes[ $name ] !== $index ) {
				$result['create'][ $name ] = $index;
			}
		}

		return $result;
	}

	/* -------------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------------- */

	/**
	 * Gets the table name corresponding to the given model.
	 */
	protected function getTableName( string $model ): string {
		return strtolower( str_replace( '\\', '_', $model ) );
	}

	/**
	 * Gets the deltas between a model and the corresponding table.
	 *
	 * @param ModelInterface|string $model A model class name.
	 *
	 * @return array An assoc array of added, updated and removed properties.
	 */
	protected function getColumnDelta( string $model ): array {

		// Generates table columns from model properties.
		$generated = $this->getModelColumns( $model );

		// Read the current table structure from the database.
		$columns = $this->showColumns( $this->getTableName( $model ) );
		$columns = array_column( $columns, null, 'Field' );

		// Get deltas between the new and the current table columns.
		$union   = array_intersect_key( $generated, $columns );
		$removed = array_diff_key( $columns, $generated );
		$added   = array_diff_key( $generated, $columns );
		$updated = [];

		// Get updated columns.
		foreach ( $union as $id => $structure ) {
			if ( array_diff_assoc( $structure, $columns[ $id ] ) ) {
				$updated[ $id ] = $structure;
			}
		}

		// Get renamed columns.
		// There is no safe way to know if a model property was replaced or renamed.
		// Here we assume that if the column type remains the same, then the property was renamed.
		foreach ( $removed as $a => $column ) {
			foreach ( $added as $b => $structure ) {
				if ( $column['Type'] === $structure['Type'] ) {
					$updated[ $a ] = $structure;
					unset( $removed[ $a ] );
					unset( $added[ $b ] );
					break;
				}
			}
		}

		return compact( 'added', 'updated', 'removed' );
	}

	protected function getIndexDelta( string $model ): array {
		$properties = $model::properties();
		$primary    = $model::idProperty();
		$unique     = $this->getModelIndexes( $properties, PropertyItem::UNIQUE );
		$indexes    = $this->getModelIndexes( $properties, PropertyItem::INDEX );

		// Generates table columns from model properties.
		$generated = $this->getModelColumns( $model );

		// Read the current table structure from the database.
		$columns = $this->showColumns( $this->getTableName( $model ) );
		$columns = array_column( $columns, null, 'Field' );

		// Get deltas between the new and the current table columns.
		$union   = array_intersect_key( $generated, $columns );
		$removed = array_diff_key( $columns, $generated );
		$added   = array_diff_key( $generated, $columns );
		$updated = [];

		// Get updated columns.
		foreach ( $union as $id => $structure ) {
			if ( array_diff_assoc( $structure, $columns[ $id ] ) ) {
				$updated[ $id ] = $structure;
			}
		}

		// Get renamed columns.
		// There is no safe way to know if a model property was replaced or renamed.
		// Here we assume that if the column type remains the same, then the property was renamed.
		foreach ( $removed as $a => $column ) {
			foreach ( $added as $b => $structure ) {
				if ( $column['Type'] === $structure['Type'] ) {
					$updated[ $a ] = $structure;
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
	 * @return array[] An array of column definitions.
	 */
	protected function x_getModelColumns( string $model ): array {
		$properties = $model::properties();
		$primary    = $model::idProperty();
		$unique     = $this->getModelIndexes( $properties, PropertyItem::UNIQUE );
		$indexes    = $this->getModelIndexes( $properties, PropertyItem::INDEX );
		$columns    = [];

		// Generate sql column definitions for all model properties.
		foreach ( $properties as $id => $property ) {
			$columns[ $id ] = $this->getColumnDefinition( $property );
		}

		// Set the primary key.
		if ( array_key_exists( $primary, $properties ) ) {
			$columns[ $primary ]['Key']     = 'PRI';
			$columns[ $primary ]['Default'] = null;
		}

		// Set unique and index keys.
		foreach ( array_merge( $indexes, $unique ) as $fields ) {
			foreach ( $fields as $field ) {
				$columns[ $field ]['Key'] = count( $fields ) === 1 ? 'UNI' : 'MUL';
			}
		}

		return array_filter( $columns );
	}

	/**
	 * Generates the column definition for a model property.
	 *
	 * @param array $property The model property.
	 *
	 * @return array An assoc array of column definition fields.
	 */
	protected function getColumnDefinition( array $property ): array {
		$type     = $property[ PropertyItem::TYPE ] ?? PropertyType::MIXED;
		$model    = $property[ PropertyItem::MODEL ] ?? null;
		$foreign  = $property[ PropertyItem::FOREIGN ] ?? null;
		$required = $property[ PropertyItem::REQUIRED ] ?? null;

		// Storing functions is not supported.
		if ( PropertyType::FUNCTION === $type ) {
			return [];
		}

		// Arrays of models require a separate relationship table.
		if ( PropertyType::ARRAY === $type && ( $model || $foreign ) ) {
			if ( Utils::isModel( $model ) || Utils::isModel( $foreign ) ) {
				return [];
			}
		}

		// Replace sub-models with foreign keys.
		if ( PropertyType::OBJECT === $type && Utils::isModel( $model ) ) {
			if ( $external = $model::getProperty( $model::idProperty() ) ) {
				return [
					'Field'   => $property[ PropertyItem::ID ],
					'Type'    => $this->getColumnType( $external->data( ModelData::COMPACT ) ),
					'Null'    => $required ? 'NO' : 'YES',
					'Key'     => '',
					'Default' => null,
					'Extra'   => '',
				];
			}
		}

		return [
			'Field'   => $property[ PropertyItem::ID ],
			'Type'    => $this->getColumnType( $property ),
			'Null'    => $required ? 'NO' : 'YES',
			'Key'     => '',
			'Default' => $this->getColumnDefault( $property ),
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
	 * Gets the column default value.
	 *
	 * @param array $property A model property.
	 *
	 * @return string The default value.
	 */
	protected function getColumnDefault( array $property ) {
		$type    = $property[ PropertyItem::TYPE ] ?? PropertyType::MIXED;
		$default = $property[ PropertyItem::DEFAULT ] ?? null;

		if ( PropertyType::UUID === $type && true === $default ) {
			return null;
		}

		if ( is_scalar( $default ) ) {
			return $default;
		}

		return null;
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
