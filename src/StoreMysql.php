<?php namespace Peroks\Model;

use Generator;
use mysqli, mysqli_sql_exception;

/**
 * Class for storing and retrieving models from a SQL database.
 *
 * @author Per Egil Roksvaag
 * @copyright Per Egil Roksvaag
 * @license MIT
 */
class StoreMysql extends StoreSql implements StoreInterface {

	/**
	 * @var mysqli|object $db The database object.
	 */
	protected object $db;

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

		mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );

		// Delete database.
		if ( false ) {
			$db = new mysqli( $connect->host, $connect->user, $connect->pass );
			$db->real_query( $this->dropDatabaseQuery( $connect->name ) );
		}

		try {
			$db = new mysqli( $connect->host, $connect->user, $connect->pass, $connect->name );
			$db->set_charset( 'utf8mb4' );
		} catch ( mysqli_sql_exception $e ) {
			$db = new mysqli( $connect->host, $connect->user, $connect->pass );
			$db->set_charset( 'utf8mb4' );
			$db->real_query( $this->createDatabaseQuery( $connect->name ) );
			$db->select_db( $connect->name );
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
		if ( $this->db->real_query( $query ) ) {
			return $this->db->affected_rows;
		}
		return 0;
	}

	/**
	 * Executes a single query against the database.
	 *
	 * @param string $query A sql query.
	 * @param array $values
	 *
	 * @return array[] The query result.
	 */
	protected function query( string $query, array $values = [] ): array {
		$prepared = $this->prepare( $query );
		return $this->select( $prepared, $values );
	}

	/**
	 * Prepares a statement for execution and returns a statement object.
	 *
	 * @param string $query A valid sql statement template.
	 *
	 * @return object
	 */
	protected function prepare( string $query ): object {
		$params = static::stripQueryParams( $query );

		return (object) [
			'query'  => $this->db->prepare( $query ),
			'params' => $params,
		];
	}

	protected function select( object $prepared, array $values = [] ): array {
		static::bindParams( $prepared, $values );
		$prepared->query->execute();
		return $prepared->query->get_result()->fetch_all( MYSQLI_ASSOC );
	}

	/**
	 * Inserts, updates or deletes a row.
	 *
	 * @param object $prepared A prepared update query.
	 * @param array $values An array of values for the prepared sql statement being executed.
	 *
	 * @return int The number of updated rows.
	 */
	protected function update( object $prepared, array $values = [] ): int {
		static::bindParams( $prepared, $values );
		$prepared->query->execute();
		return $prepared->query->affected_rows;
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
	 * If necessary, escapes and quotes a variable before use in a sql statement.
	 *
	 * @param mixed $value The variable to be used in a sql statement.
	 *
	 * @return mixed A safe variable to be used in a sql statement.
	 */
	protected function escape( $value ) {
		return is_string( $value ) ? "'" . $this->db->real_escape_string( $value ) . "'" : $value;
	}

	/**
	 * @param string $query
	 *
	 * @return Generator
	 */
	protected function multi( string $query ): Generator {
		$this->db->multi_query( $query );

		do {
			if ( $result = $this->db->use_result() ) {
				yield $result;
				$result->free();
			}
		} while ( $this->db->next_result() );
	}

	protected function fetch( object $result ): ?array {
		return $result->fetch_assoc() ?: null;
	}

	/* -------------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------------- */

	protected static function stripQueryParams( string &$query ): array {
		$pattern = '/:(\\w+)/';
		$params  = [];

		if ( preg_match_all( $pattern, $query, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$params[] = $match[1];
			}
		}

		$query = preg_replace( $pattern, '?', $query );
		return $params;
	}

	protected static function bindParams( object $prepared, array $params ): void {
		if ( $params ) {
			$params = array_merge( array_flip( $prepared->params ), $params );
			$params = array_values( $params );
			$types  = '';

			foreach ( $params as $value ) {
				if ( is_string( $value ) ) {
					$types .= 's';
				} elseif ( is_int( $value ) ) {
					$types .= 'i';
				} elseif ( is_float( $value ) ) {
					$types .= 'd';
				} else {
					$types .= 'b';
				}
			}

			$prepared->query->bind_param( $types, ...$params );
		}
	}

	/**
	 * Completely restores an array of models including all sub-models.
	 *
	 * @param ModelInterface|string $class The model class name.
	 * @param ModelInterface[] $collection An array of models of the given class.
	 */
	protected function restoreCollection( string $class, array $collection ): void {
		if ( empty( $collection ) ) {
			return;
		}

		if ( empty( $properties = static::getForeignProperties( $class::properties() ) ) ) {
			return;
		}

		// Temp variables.
		$targets  = [];
		$queries  = [];
		$children = [];

		// Loop over all models and their sub-model properties.
		foreach ( $collection as $model ) {
			foreach ( $properties as $id => $property ) {
				$type  = $property[ PropertyItem::TYPE ];
				$child = $property[ PropertyItem::MODEL ];
				$value = $model[ $id ];

				// Create queries to fetch sub-models.
				if ( PropertyType::ARRAY === $type ) {
					$targets[] = (object) compact( 'model', 'child', 'id', 'type' );
					$queries[] = $this->selectChildrenQuery( $class, $child, $id, $model->id() );
				} elseif ( PropertyType::OBJECT === $type && isset( $value ) ) {
					$table     = $this->getTableName( $child );
					$targets[] = (object) compact( 'model', 'child', 'id', 'type' );
					$queries[] = $this->selectRowQuery( $table, $child::idProperty(), $value );
				}
			}
		}

		// Execute the queries and fetch the sub-model data from the db.
		if ( $queries ) {
			foreach ( $this->multi( join( ";\n", $queries ) ) as $result ) {
				$target = array_shift( $targets );

				while ( $row = $this->fetch( $result ) ) {
					$children[ $target->child ][] = $child = new $target->child( $row );

					// Assign sub-models to the parent model.
					if ( PropertyType::OBJECT === $target->type ) {
						$target->model[ $target->id ] = $child;
					} elseif ( PropertyType::ARRAY === $target->type ) {
						$target->model[ $target->id ][] = $child;
					}
				}
			}
		}

		// Recursively restore sub-models.
		foreach ( $children as $class => $collection ) {
			static::restoreCollection( $class, $collection );
		}
	}
}
