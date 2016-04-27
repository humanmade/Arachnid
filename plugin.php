<?php
/**
 * Plugin Name: Arachnid
 * Description: Catch webhooks in your web.
 * Version: 0.1
 */

namespace Arachnid;

const VERSION = '0.1';

spl_autoload_register( __NAMESPACE__ . '\\autoload' );
add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap' );

/**
 * Autoloader for Arachnid classes.
 *
 * @param string $class Class name to attempt autoloading.
 */
function autoload( $class ) {
	if ( strpos( $class, __NAMESPACE__ . '\\' ) !== 0 ) {
		return;
	}

	$file = substr( $class, strlen( __NAMESPACE__ . '\\' ) );
	$file = strtolower( $file );
	$parts = explode( '\\', $file );
	$last = array_pop( $parts );

	$path = __DIR__ . '/inc/' . implode( '/', $parts );
	$path = untrailingslashit( $path ) . '/class-' . $last . '.php';

	require $path;
}

function bootstrap() {
	// Check version requirement, just to be sure.
	$version_php = ABSPATH . WPINC . '/wp-includes/version.php';
	if ( version_compare( $GLOBALS['wp_version'], '4.5', '<' ) ) {
		add_action( 'admin_notices', function () {
			$message = 'Arachnid requires WordPress 4.5 or later.';
			printf( '<div class="error notice"><p>%s</p></div>', $message );
		});
		return;
	}

	// Check plugin version.
	$version = get_option( 'arachnid_version', null );
	if ( version_compare( $version, VERSION, '<' ) ) {
		update_tables();
		// update_option( 'arachnid_version', VERSION );
	}

	add_action( 'rest_dispatch_request', __NAMESPACE__ . '\\on_dispatch_request', 10, 4 );
}

/**
 * Install or update Arachnid tables.
 *
 * @return WP_Error|bool True if the tables were successfully created, error otherwise.
 */
function update_tables() {
	global $wpdb;

	$table_name = Request::get_table();
	$meta_table_name = Request::get_meta_table();

	$tables = [];
	$tables[ $table_name ] = "CREATE TABLE $table_name (
		`id` int(20) unsigned NOT NULL AUTO_INCREMENT,
		`timestamp` datetime NOT NULL,
		`response_status` smallint(2) unsigned NOT NULL,
		`request` longblob NOT NULL,
		`response` longblob,

		PRIMARY KEY (`id`),
		KEY `timestamp` (`timestamp`),
		KEY `response_status` (`response_status`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

	$tables[ $meta_table_name ] = "CREATE TABLE $meta_table_name (
		`key` varchar(255) NOT NULL DEFAULT '',
		`request` int(20) unsigned NOT NULL,
		`value` longtext,

		PRIMARY KEY (`key`),
		KEY `request` (`request`),
		FOREIGN KEY (`request`)
			REFERENCES $table_name (`id`)
			ON DELETE CASCADE
			ON UPDATE CASCADE
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

	foreach ( $tables as $table => $create_syntax ) {
		$suppress = $wpdb->suppress_errors();
		$tablefields = $wpdb->get_results( "DESCRIBE {$table};" );
		$wpdb->suppress_errors( $suppress );

		if ( ! empty( $tablefields ) ) {
			// Table exists, skip!
			continue;
		}

		// Create the table.
		$wpdb->query( $create_syntax );
	}

	return true;
}

/**
 * Hook into dispatching of a request.
 *
 * @param WP_REST_Response|WP_Error|null $result Result value, or null if not yet generated.
 * @param WP_REST_Request $request Request we're dispatching.
 * @param string $route The route matched in the server.
 * @param array $handler Options for the handler that matched.
 * @return WP_REST_Response|WP_Error|null Dispatch request.
 */
function on_dispatch_request( $result, $request, $route, $handler ) {
	// If we're running on an older version of WP, skip everything.
	if ( empty( $handler ) ) {
		return $result;
	}

	// Is this a webhook request?
	if ( empty( $handler['is_webhook'] ) ) {
		return $result;
	}

	// If we don't have a result, get it now.
	if ( empty( $result ) ) {
		$result = call_user_func( $handler['callback'], $request );
	}

	// We've found a webhook, so log it. THIS IS NOT A DRILL!
	$data = [
		'timestamp'       => $GLOBALS['timestart'],
		'request'         => $request,
		'response'        => $result,
	];

	// Grab the status, depending on what result we have.
	if ( is_wp_error( $result ) ) {
		$err_data = $result->get_error_data();
		$data['response_status'] = isset( $err_data['status'] ) ? absint( $err_data ) : 500;
	} else {
		$response = rest_ensure_response( $result );
		$data['response_status'] = $response->get_status();
	}

	$request = Request::create( $data );
	var_dump( $request );

	return $result;
}