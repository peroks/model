<?php namespace Peroks\Model;

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
	 * Retrieving models.
	 * ---------------------------------------------------------------------- */

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

		$properties = static::getForeignProperties( $class::properties() );
		$queries    = [];
		$values     = [];

		foreach ( $this->select( $query, $filter ) as &$row ) {
			$model = $row = new $class( $row );

			foreach ( $properties as $id => $property ) {
				$child = $property[ PropertyItem::MODEL ];
				$value = &$model[ $id ];

				if ( PropertyType::ARRAY === $property[ PropertyItem::TYPE ] ) {
					//	$select = $this->selectChildrenStatement( get_class( $model ), $child );
					//	$rows   = $this->select( $select, (array) $model->id() );
					//	$value  = array_map( fn( $row ) => $this->restore( new $child( $row ) ), $rows );
				} elseif ( $value ) {
					$queries[] = vsprintf( 'SELECT * FROM %s WHERE %s = %s', [
						$this->name( $child ),
						$this->name( $child::idProperty() ),
						$this->quote( $value ),
					] );
					$values[]  = $value;
				}
			}
		}

		if ( $queries ) {
			$query = join( ";\n", $queries );
			//	$x     = $this->query( $query );
		}

		return $rows;
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

		// Delete database.
		mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
		$db = new mysqli( $connect->host, $connect->user, $connect->pass );
		$db->set_charset( 'utf8mb4' );

		if ( false ) {
			$db->real_query( $this->dropDatabaseQuery( $connect->name ) );
		}

		try {
			$db->select_db( $connect->name );
		} catch ( mysqli_sql_exception $e ) {
			$db = new mysqli( $connect->host, $connect->user, $connect->pass );
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
	 *
	 * @return array[] The query result.
	 */
	protected function query( string $query, array $params = [] ): array {
		$prepared = $this->prepare( $query );
		return $this->select( $prepared, $params );
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

	protected function select( object $prepared, array $params = [] ): array {
		static::bindParams( $prepared, $params );
		$prepared->query->execute();
		return $prepared->query->get_result()->fetch_all( MYSQLI_ASSOC );
	}

	/**
	 * Inserts, updates or deletes a row.
	 *
	 * @param object $prepared A prepared update query.
	 * @param array $params An array of values for the prepared sql statement being executed.
	 *
	 * @return int The number of updated rows.
	 */
	protected function update( object $prepared, array $params = [] ): int {
		static::bindParams( $prepared, $params );
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
	 * Quotes a string for use in a query.
	 *
	 * @param string $value The string to be quoted.
	 *
	 * @return string The quoted string.
	 */
	protected function quote( string $value ): string {
		return "'" . $this->db->real_escape_string( $value ) . "'";
	}

	/* -------------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------------- */

	protected static function stripQueryParams( string &$query ): array {
		$pattern = '/:(\\w+)/';
		$search  = [];
		$params  = [];

		if ( preg_match_all( $pattern, $query, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$search[] = $match[0];
				$params[] = $match[1];
			}
		}

		$query = str_replace( $search, '?', $query );
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
}
