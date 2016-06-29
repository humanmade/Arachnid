<?php

namespace Arachnid;

use WP_Error;

class Entry {
	const TABLE_NAME = '%sarachnid_entries';
	const META_TABLE_NAME = '%sarachnid_entries_meta';

	/**
	 * Entry ID.
	 *
	 * @var int
	 */
	protected $id;

	/**
	 * Time of the request.
	 *
	 * @var int Unix timestamp.
	 */
	protected $timestamp;

	/**
	 * Route used to handle the request.
	 *
	 * @var string
	 */
	protected $route;

	/**
	 * Request data.
	 *
	 * @var WP_REST_Request Request data.
	 */
	protected $request;

	/**
	 * Response data.
	 *
	 * @var WP_REST_Response|WP_Error|null Response data, error, or null if an error occurred.
	 */
	protected $response;

	/**
	 * Response status code.
	 *
	 * @var int HTTP response code.
	 */
	protected $response_status;

	/**
	 * Constructor.
	 *
	 * @param stdClass $data Request data from the DB.
	 */
	protected function __construct( $data ) {
		$this->id              = $data->id;
		$this->timestamp       = mysql2date( 'U', $data->timestamp );
		$this->route           = $data->route;
		$this->request         = unserialize( $data->request );
		$this->response        = unserialize( $data->response );
		$this->response_status = $data->response_status;
	}

	/**
	 * Get request ID.
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get timestamp for the request.
	 *
	 * @return int Unix timestamp (UTC).
	 */
	public function get_timestamp() {
		return $this->timestamp;
	}

	/**
	 * Get the route for the request.
	 *
	 * @return string Route string
	 */
	public function get_route() {
		return $this->route;
	}

	/**
	 * Get request object.
	 *
	 * @return WP_REST_Request
	 */
	public function get_request() {
		return $this->request;
	}

	/**
	 * Get response object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_response() {
		return $this->response;
	}

	/**
	 * Get a meta value for the request.
	 *
	 * @param string $key Meta key.
	 * @param mixed $default Default value to return if key isn't found.
	 * @return mixed Meta value, or `$default` if not set.
	 */
	public function get_meta( $key, $default = null ) {
		global $wpdb;

		$query = $wpdb->prepare(
			'SELECT value FROM ' . static::get_meta_table() . ' WHERE `key` = %s AND `entry` = %d;',
			$key,
			$this->id
		);
		$value = $wpdb->get_var( $query );
		if ( empty( $value ) ) {
			return $default;
		}

		return unserialize( $value );
	}

	/**
	 * Set a meta value for the request.
	 *
	 * @param string $key Meta key.
	 * @param mixed $value Meta value.
	 * @return bool|WP_Error True if set correctly, error otherwise.
	 */
	public function set_meta( $key, $value ) {
		global $wpdb;

		$table = static::get_meta_table();
		$query = $wpdb->prepare(
			"INSERT INTO $table
				SET `key` = %s, `entry` = %d, `value` = %s
				ON DUPLICATE KEY UPDATE
					`value` = VALUES(`value`);",
			$key,
			$this->id,
			serialize( $value )
		);
		$result = $wpdb->query( $query );
		if ( $result === false ) {
			return new WP_Error( 'arachnid.entry.set_meta.db_error', $wpdb->last_error, compact( $key, $value, $query ) );
		}

		return true;
	}

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public static function get_table() {
		return sprintf( static::TABLE_NAME, $GLOBALS['wpdb']->prefix );
	}

	/**
	 * Get the meta table name.
	 *
	 * @return string
	 */
	public static function get_meta_table() {
		return sprintf( static::META_TABLE_NAME, $GLOBALS['wpdb']->prefix );
	}

	/**
	 * Convert raw DB data to an Entry instance.
	 *
	 * Allows use as a callback, such as in `array_map`
	 *
	 * @param stdClass $row Raw entry data from the database.
	 * @return static
	 */
	protected static function to_instance( $row ) {
		return new static( $row );
	}

	/**
	 * Convert list of raw data to Entry instances.
	 *
	 * @param stdClass[] $rows List of raw entry data rows.
	 * @return static[]
	 */
	protected static function to_instances( $rows ) {
		return array_map( array( get_called_class(), 'to_instance' ), $rows );
	}

	/**
	 * Create an Entry object.
	 *
	 * @param array $data {
	 *     @var string $route Route used to handle the request.
	 *     @var WP_REST_Request|null $request Request data.
	 *     @var int|null $response_status Response status code.
	 *     @var WP_REST_Response|WP_Error|null $response Response data.
	 * }
	 */
	public static function create( $data ) {
		global $wpdb;

		$serialize_keys = [
			'request',
			'response',
		];

		$fields = [
			'route' => $data['route'],
		];
		foreach ( $serialize_keys as $key ) {
			if ( empty( $data[ $key ] ) ) {
				continue;
			}

			$fields[ $key ] = serialize( $data[ $key ] );
		}

		if ( isset( $data['response_status'] ) ) {
			$fields['response_status'] = absint( $data['response_status'] );
		}

		// Set the timestamp, or default to now.
		if ( isset( $data['timestamp'] ) ) {
			$fields['timestamp'] = date( 'Y-m-d H:i:s', $data['timestamp'] );
		} else {
			$fields['timestamp'] = current_time( 'mysql' );
		}

		$inserted = $wpdb->insert( static::get_table(), $fields );
		if ( empty( $inserted ) ) {
			$error = $wpdb->last_error;
			return new WP_Error( 'arachnid.request.create.query_failed', $error );
		}

		$id = $wpdb->insert_id;
		return static::get( $id );
	}

	/**
	 * Deletes an entry
	 *
	 * @param int $id ID to be deleted
	 * @return bool|str true if successful, string if there was an error
	 */
	public static function delete( $id ) {
		global $wpdb;
		
		$table = $wpdb->delete( static::get_table(), array( 'id' => $id ), '%d' );
		if ( false === $table ) {
			return $wpdb->last_error;
		}
		$meta = $wpdb->delete( static::get_meta_table(), array( 'key' => $id ), '%d' );
		if ( false === $meta ) {
			return $wpdb->last_error;
		}

		return true;

	}

	/**
	 * Get entry by ID.
	 *
	 * @param int $id Entry ID.
	 * @return static|WP_Error|null Entry instance on success, error object if error occurred, null if no entry found.
	 */
	public static function get( $id ) {
		global $wpdb;
		$query = $wpdb->prepare(
			'SELECT * FROM ' . static::get_table() . ' WHERE `id` = %d',
			$id
		);
		$data = $wpdb->get_row( $query );
		if ( empty( $data ) ) {
			return null;
		}

		return static::to_instance( $data );
	}

	/**
	 * Query for entries.
	 *
	 * @param array $args Query arguments. {
	 *     @type string $route Route to search for.
	 *     @type int $response_status Response status code.
	 *     @type int $timestamp Exact timestamp to search for.
	 *     @type int $before Upper bound for the timestamp.
	 *     @type int $after Lower bound for the timestamp.
	 *     @type int $offset Row offset to start from.
	 *     @type int|bool $limit Limit of rows to return, or false for no limit.
	 * }
	 *
	 * @return Entry[]|WP_Error List of entries if successful, error otherwise.
	 */
	public static function query( $args = [] ) {
		global $wpdb;

		$offset = 0;
		$limit = 10;
		$order_dir = 'ASC';

		$conditions = [];
		$params = [];
		foreach ( $args as $key => $value ) {
			switch ( $key ) {
				case 'route':
					$conditions[] = '`route` = %s';
					$params[] = $value;
					break;

				case 'response_status':
					$conditions[] = '`response_status` = %d';
					$params[] = $value;
					break;

				case 'success':
					if ( $value ) {
						$conditions[] = '(`response_status` >= 200 AND `response_status` < 300)';
					} else {
						$conditions[] = '(`response_status` < 200 OR `response_status` >= 300)';
					}
					break;

				case 'timestamp':
					$conditions[] = '`timestamp` = %s';
					$params[] = date( 'Y-m-d H:i:s', $value );
					break;

				case 'before':
					$conditions[] = '`timestamp` < %s';
					$params[] = date( 'Y-m-d H:i:s', $value );
					break;

				case 'after':
					$conditions[] = '`timestamp` > %s';
					$params[] = date( 'Y-m-d H:i:s', $value );
					break;

				case 'offset':
					$offset = absint( $value );
					break;

				case 'limit':
					$limit = ( $value === false ) ? false : absint( $value );
					break;

				case 'order_dir':
					switch ( strtolower( $value ) ) {
						case 'asc':
							$order_dir = 'ASC';
							break;

						case 'desc':
							$order_dir = 'DESC';
							break;

						default:
							return new WP_Error( 'arachnid.query.invalid_order' );
					}
					break;
			}
		}

		$table = static::get_table();
		$where = implode( ' AND ', $conditions );
		$query = "SELECT SQL_CALC_FOUND_ROWS * FROM $table";
		if ( ! empty( $conditions ) ) {
			$query .= ' WHERE ' . $where;
		}
		$query .= " ORDER BY `timestamp` $order_dir";
		if ( $limit !== false ) {
			$query = sprintf( '%s LIMIT %d OFFSET %d', $query, absint( $limit ), absint( $offset ) );
		}

		// Prepare if we have fields.
		if ( ! empty( $params ) ) {
			$query = $wpdb->prepare( $query, $params );
		}

		$results = $wpdb->get_results( $query );
		if ( $results === null ) {
			return new WP_Error( 'arachnid.query.db_error', $wpdb->last_error, compact( 'query' ) );
		}

		$total = $wpdb->get_var( "SELECT FOUND_ROWS();" );

		// Convert results to result object.
		$result = new QueryResult( static::to_instances( $results ) );
		$result->total = absint( $total );
		$result->query = $query;
		return $result;
	}
}
