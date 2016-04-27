<?php

namespace Arachnid;

use WP_Error;

class Request {
	const TABLE_NAME = '%swebhook_requests';
	const META_TABLE_NAME = '%swebhook_requests_meta';

	/**
	 * Request ID.
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
			'SELECT value FROM ' . static::get_meta_table() . ' WHERE `key` = %s AND `request` = %d;',
			$key,
			$this->id
		);
		$value = $wpdb->get_col( $query );
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
				SET `key` = %s, `request` = %d, `value` = %s
				ON DUPLICATE KEY UPDATE
					`value` = VALUES(`value`);",
			$key,
			$this->id,
			serialize( $value )
		);
		$result = $wpdb->query( $query );
		if ( $result === false ) {
			return new WP_Error( 'arachnid.request.set_meta.db_error', $wpdb->last_error, compact( $key, $value, $query ) );
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
	 * Convert raw DB data to a Request instance.
	 *
	 * Allows use as a callback, such as in `array_map`
	 *
	 * @param stdClass $row Raw request data from the database.
	 * @return static
	 */
	protected static function to_instance( $row ) {
		return new static( $row );
	}

	/**
	 * Convert list of raw data to Request instances.
	 *
	 * @param stdClass[] $rows List of raw request data rows.
	 * @return static[]
	 */
	protected static function to_instances( $rows ) {
		return array_map( array( get_called_class(), 'to_instance' ), $rows );
	}

	/**
	 * Create a request object.
	 *
	 * @param array $data {
	 *     @var array|object|null $request_headers Headers passed with the request.
	 *     @var WP_REST_Request|null $request Request data.
	 *     @var int|null $response_status Response status code.
	 *     @var array|object|null $response_headers Headers passed back with the response.
	 *     @var WP_REST_Response|WP_Error|null $response Response data.
	 * }
	 */
	public static function create( $data ) {
		global $wpdb;

		$serialize_keys = [
			'request_headers',
			'request',
			'response_status',
			'response_headers',
			'response',
		];

		$fields = [];
		foreach ( $serialize_keys as $key ) {
			if ( empty( $data[ $key ] ) ) {
				continue;
			}

			$fields[ $key ] = serialize( $data[ $key ] );
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
	 * Get request by request ID.
	 *
	 * @param int $id Request ID.
	 * @return static|WP_Error|null Request instance on success, error object if error occurred, null if no request found.
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
}