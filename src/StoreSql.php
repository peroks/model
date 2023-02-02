<?php namespace Peroks\Model;

use PDO;
use PDOException;

class StoreSql implements StoreInterface {

	/**
	 * @var PDO $db The database object.
	 */
	protected PDO $db;

	/**
	 * @var array A temp array of model relations.
	 */
	protected array $relations;

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
			$this->buildDatabase( $models );
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
	protected function query( string $sql, array $param = [] ): array {
		$statement = $this->db->prepare( $sql );
		$statement->execute( $param );
		return $statement->fetchAll( PDO::FETCH_ASSOC );
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

		// Reset relations.
		$this->relations = [];

		// Create or alter database tables for models.
		foreach ( $models as $model ) {
			if ( in_array( $this->getTableName( $model ), $tables, true ) ) {
				$count += (int) $this->alterTable( $model );
			} else {
				$count += (int) $this->createTable( $model );
			}
		}

		// Create or alter database tables for model relations.
		foreach ( $this->relations as $relation => $definition ) {
			if ( in_array( $this->getTableName( $relation ), $tables, true ) ) {
				$count += (int) $this->alterTable( $relation );
			} else {
				$count += (int) $this->createTable( $relation );
			}
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
		$sql = $this->showTablesQuery();
		return $this->query( $sql );
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
	 * @param ModelInterface|string $model The model to create a database table for.
	 *
	 * @return string Sql query to create a database table.
	 */
	protected function createTableQuery( string $model ): string {
		if ( Utils::isModel( $model ) ) {
			$columns = $this->getModelColumns( $model );
			$indexes = $this->getModelIndexes( $model );
		} else {
			$columns = $this->getRelationColumns( $model );
			$indexes = $this->getRelationIndexes( $model );
		}

		$sql = [];

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
	 * Generates a query to create a table for the given model.
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
	 * Generates a query to alter a database table to match the given model.
	 *
	 * @param ModelInterface|string $model The model to create a database table for.
	 *
	 * @return string Sql query to update a database table.
	 */
	protected function alterTableQuery( string $model ): string {
		$columns = $this->getDeltaColumns( $model );
		$indexes = $this->getDeltaIndexes( $model );
		$sql     = [];

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
	protected function alterTable( string $model ): bool {
		$sql = $this->alterTableQuery( $model );
		return $this->exec( $sql );
	}

	/* -------------------------------------------------------------------------
	 * Show and define table columns.
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
	 * Get model columns.
	 *
	 * @param ModelInterface|string $model
	 *
	 * @return array
	 */
	protected function getModelColumns( string $model ): array {
		$properties = $model::properties();
		$result     = [];

		foreach ( $properties as $id => $property ) {
			$type     = $property[ PropertyItem::TYPE ] ?? PropertyType::MIXED;
			$submodel = $property[ PropertyItem::MODEL ] ?? null;
			$foreign  = $property[ PropertyItem::FOREIGN ] ?? null;
			$default  = $property[ PropertyItem::DEFAULT ] ?? null;

			// Storing functions is not supported.
			if ( PropertyType::FUNCTION === $type ) {
				continue;
			}

			// Arrays of models require a separate relation table.
			if ( PropertyType::ARRAY === $type && ( $submodel || $foreign ) ) {
				if ( Utils::isModel( $submodel ) || Utils::isModel( $foreign ) ) {
					$this->addRelation( $model, $submodel );
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
			if ( PropertyType::OBJECT === $type && Utils::isModel( $submodel ) ) {
				if ( $primary = $submodel::getProperty( $submodel::idProperty() ) ) {
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
	 * @param ModelInterface|string $model
	 *
	 * @return array
	 */
	protected function getDeltaColumns( string $model ): array {
		$tableColumns = $this->getTableColumns( $this->getTableName( $model ) );
		$modelColumns = Utils::isModel( $model )
			? $this->getModelColumns( $model )
			: $this->getRelationColumns( $model );

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
	 * Show and define table indexes
	 * ---------------------------------------------------------------------- */

	protected function showIndexesQuery( string $table ): string {
		return vsprintf( 'SHOW INDEXES FROM %s', [
			$this->name( $table ),
		] );
	}

	protected function showIndexes( string $table ): array {
		$sql = $this->showIndexesQuery( $table );
		return $this->query( $sql );
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
		$modelIndexes = Utils::isModel( $model )
			? $this->getModelIndexes( $model )
			: $this->getRelationIndexes( $model );

		$union  = array_intersect_key( $modelIndexes, $tableIndexes );
		$drop   = array_diff_key( $tableIndexes, $modelIndexes );
		$create = array_diff_key( $modelIndexes, $tableIndexes );

		// Check for differences.
		foreach ( $union as $name => $modelIndex ) {
			$tableIndex = $tableIndexes[ $name ];

			// Convert columns to string for comparing.
			$tableIndex['columns'] = join( ', ', $tableIndex['columns'] );
			$modelIndex['columns'] = join( ', ', $modelIndex['columns'] );

			if ( array_diff( $modelIndex, $tableIndex ) ) {
				$drop[ $name ]   = $tableIndexes[ $name ];
				$create[ $name ] = $modelIndexes[ $name ];
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
	 * Adds a model relation table and adds to the processing stack.
	 *
	 * @param ModelInterface|string $model
	 * @param ModelInterface|string $foreign
	 */
	protected function addRelation( string $model, string $foreign ): bool {
		$modelPrimary   = $model::getProperty( $model::idProperty() );
		$foreignPrimary = $foreign::getProperty( $foreign::idProperty() );

		if ( isset( $modelPrimary, $foreignPrimary ) ) {
			$left     = current( array_reverse( explode( '\\', $model ) ) );
			$right    = current( array_reverse( explode( '\\', $foreign ) ) );
			$relation = $model . '\\' . $right;

			$columns[ $left ] = [
				'name'     => $left,
				'type'     => $this->getColumnType( $modelPrimary ),
				'required' => true,
				'default'  => null,
			];

			$columns[ $right ] = [
				'name'     => $right,
				'type'     => $this->getColumnType( $foreignPrimary ),
				'required' => true,
				'default'  => null,
			];

			$indexes[ $left ] = [
				'name'    => $left,
				'type'    => 'INDEX',
				'columns' => [ $left ],
			];

			$this->relations[ $relation ] = compact( 'columns', 'indexes' );
			return true;
		}
		return false;
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

				if ( Utils::isModel( $foreign ) ) {
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
	 * @param ModelInterface|string $model A model class name.
	 * @param bool $include Whether to include the given model in the result or not.
	 *
	 * @return ModelInterface|string[] An array of sub-model classes of the given model class.
	 */
	protected function getSubModels( string $model, bool $include = true ): array {
		$result = $include ? [ $model ] : [];

		foreach ( $model::properties() as $property ) {
			$foreign = $property[ PropertyItem::MODEL ] ?? $property[ PropertyItem::FOREIGN ] ?? null;

			if ( Utils::isModel( $foreign ) ) {
				if ( empty( in_array( $foreign, $result, true ) ) ) {
					$result = array_merge( $result, $this->getSubModels( $foreign ) );
				}
			}
		}

		return $result;
	}
}
