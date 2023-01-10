<?php namespace Peroks\Model;

class StoreSql implements StoreInterface {

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

	public function init( array $options ) {
		$default = [
			'models' => [],
		];

		$options = array_merge( $default, $options );
		$models  = $options['models'];

		foreach ( $models as $model ) {
			$this->createTable( $model );
		}
	}

	public function createTable( string $model ) {
		$sql = $this->createTableQuery( $model );
	}

	public function createTableQuery( string $model ): string {

		/** @var ModelInterface $model */
		$properties = $model::properties();
		$primary    = $model::idProperty();
		$columns    = [];

		foreach ( $properties as $property ) {
			$columns[] = $this->getColumnDefinition( $property );
		}

		// Set primary key.
		if ( $primary && array_key_exists( $primary, $properties ) ) {
			$columns[] = sprintf( 'PRIMARY KEY (%s)', $primary );
		}

		$columns = "\n\t" . join( ",\n\t", $columns ) . "\n";
		$name    = str_replace( '\\', '_', $model );

		return sprintf( 'CREATE TABLE IF NOT EXISTS %s (%s)', $name, $columns );
	}

	public function getColumnDefinition( array $data ): string {
		$property = Property::create( $data );

		$query[] = $property->id;
		$query[] = $this->getColumnType( $property );
		$query[] = $property->required ? 'NOT NULL' : '';

		return join( ' ', array_filter( $query ) );
	}

	public function getColumnType( Property $property ): string {
		switch ( $property->type ) {
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
				if ( $property->max ) {
					return sprintf( 'varchar(%d)', $property->max );
				}
				return 'text';
			case PropertyType::UUID:
				return 'char(36)';
			case PropertyType::URL:
				return 'text';
			case PropertyType::EMAIL:
				return 'varchar(100)';
			case PropertyType::DATETIME:
				return 'varchar(25)';
			case PropertyType::DATE:
			case PropertyType::TIME:
				return 'varchar(10)';
			case PropertyType::OBJECT:
				if ( $property->foreign ) {
					return 'bigint';
				}
				return 'text';
			case PropertyType::ARRAY:
				if ( empty( $property->foreign ) ) {
					return 'text';
				}
		}
		return '';
	}
}
