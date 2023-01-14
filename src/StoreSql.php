<?php namespace Peroks\Model;

use mysqli;
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
			'connect' => [
				'host'   => 'localhost',
				'user'   => 'root',
				'pass'   => 'root',
				'name'   => 'octopus',
				'port'   => null,
				'socket' => null,
			],
			'models'  => [],
		];

		$options = (object) array_merge( $default, $args );
		$connect = (object) $options->connect;

		if ( $this->connect( $connect ) ) {
			return $this->createTables( $options->models );
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

	protected function x_connect( object $connect ): bool {
		mysqli_report( MYSQLI_REPORT_OFF );

		$db = new mysqli( $connect->host, $connect->user, $connect->pass );

		if ( empty( $db->host_info ) ) {
			return false;
		}

		$db->set_charset( 'utf8mb4' );

		if ( $db->select_db( $connect->name ) ) {
			$this->db = $db;
			return true;
		}

		if ( $db->real_query( $this->createDatabaseQuery( $connect->name ) ) ) {
			if ( $db->select_db( $connect->name ) ) {
				$this->db = $db;
				return true;
			}
		}

		return false;
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
	protected function query( string $sql ): array {
		$statement = $this->db->query( $sql, PDO::FETCH_ASSOC );
		return $statement->fetchAll();
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
		$sql = sprintf( 'DROP DATABASE IF EXISTS %s', $name );
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
		$models = $this->getAllModels( $models );
		$count  = 0;

		foreach ( $models as $model ) {
			$count += (int) $this->createTable( $model );
		}

		return count( $models ) === $count;
	}

	/**
	 * Creates a table for the given model.
	 *
	 * @param string $model The model to create a table for.
	 *
	 * @return bool True if the table was created or already exists, false otherwise.
	 */
	protected function createTable( string $model ): bool {
		$sql = $this->createTableQuery( $model );
		return $this->exec( $sql );
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

		// Get column definitions from model properties.
		$columns = array_map( [ $this, 'getColumn' ], $properties );
		$columns = array_values( array_filter( $columns ) );

		// Set primary key.
		if ( $primary && array_key_exists( $primary, $properties ) ) {
			$columns[] = sprintf( 'PRIMARY KEY (%s)', $this->quote( $primary ) );
		}

		$sql   = "\n\t" . join( ",\n\t", $columns ) . "\n";
		$table = $this->getTableName( $model );

		return sprintf( 'CREATE TABLE IF NOT EXISTS %s (%s);', $table, $sql );
	}

	/**
	 * Get column definitions from model properties.
	 *
	 * @param array $property A model property.
	 *
	 * @return string Column definition string.
	 */
	protected function getColumn( array $property ): string {
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
				$query[] = $this->getColumnName( $property[ PropertyItem::ID ] );
				$query[] = $this->getColumnType( $primary->data() );
				return join( ' ', $query );
			}
		}

		$query[] = $this->getColumnName( $property[ PropertyItem::ID ] );
		$query[] = $this->getColumnType( $property );
		$query[] = $required ? 'NOT NULL' : '';

		return join( ' ', array_filter( $query ) );
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

	/* -------------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------------- */

	/**
	 * Quotes db, table and column names.
	 *
	 * @param string $name The name to quote.
	 *
	 * @return string The quoted name.
	 */
	protected function quote( string $name ): string {
		return '`' . trim( trim( $name ), '`' ) . '`';
	}

	/**
	 * Gets the table name corresponding to the given model.
	 */
	protected function getTableName( string $model ): string {
		return $this->quote( str_replace( '\\', '_', $model ) );
	}

	/**
	 * Gets the column name corresponding to the given property id.
	 */
	protected function getColumnName( string $property ): string {
		return $this->quote( $property );
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
