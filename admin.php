<?php

namespace Arachnid\Admin;

use Arachnid\Entry;
use WP_REST_Request;
use WP_REST_Response;

function bootstrap() {
	if ( ! is_admin() ) {
		return;
	}

	add_action( 'admin_menu', __NAMESPACE__ . '\\register' );
}

function register() {
	$hook = add_submenu_page(
		// $parent_slug
		'tools.php',

		// $page_title
		'Arachnid',

		// $menu_title
		'Arachnid Logs',

		// $capability
		'manage_options',

		// $menu_slug
		'arachnid-log',

		// $function
		__NAMESPACE__ . '\\render',

		'dashicons-carrot'
	);

	add_action( 'load-' . $hook, __NAMESPACE__ . '\\on_load' );
}

function error_to_response( $error ) {
	$error_data = $error->get_error_data();

	if ( is_array( $error_data ) && isset( $error_data['status'] ) ) {
		$status = $error_data['status'];
	} else {
		$status = 500;
	}

	$errors = array();

	foreach ( (array) $error->errors as $code => $messages ) {
		foreach ( (array) $messages as $message ) {
			$errors[] = array( 'code' => $code, 'message' => $message, 'data' => $error->get_error_data( $code ) );
		}
	}

	$data = $errors[0];
	if ( count( $errors ) > 1 ) {
		// Remove the primary error.
		array_shift( $errors );
		$data['additional_errors'] = $errors;
	}

	$response = new WP_REST_Response( $data, $status );

	return $response;
}

/**
 * Prepare for loading the admin page.
 */
function on_load() {
	wp_enqueue_style( 'arachnid-log', plugins_url( 'assets/logs.css', __FILE__ ) );
	wp_enqueue_script( 'arachnid-log', plugins_url( 'assets/logs.js', __FILE__ ), [ 'jquery' ] );
}

/**
 * Render the admin page.
 */
function render() {
	$args = wp_unslash( $_GET );
	$query = [
		'order_dir' => 'desc',
	];
	if ( isset( $args['success'] ) ) {
		$query['success'] = bool_from_yn( $args['success'] );
	}
	if ( isset( $args['route'] ) ) {
		$query['route'] = $args['route'];
	}
	$logs = Entry::query( $query );
	?>

	<div class="wrap">

		<h2>Arachnid Logs</h2>

		<ul class="subsubsub">
			<?php

			$links = get_filter_links( $args );
			echo '<li>' . implode( '|</li>', $links ) . '</li>';

			?>
		</ul>

		<form method="GET" action="">
			<p class="search-box">
				<label for="endpoint-search" class="screen-reader-text">Show Endpoint:</label>
				<input type="text" id="endpoint-search" name="route" />
				<button class="button">Filter by Endpoint</button>
			</p>
		</form>

		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<p><?php echo esc_html( sprintf(
					_n( '%d item', '%d items', $logs->total ),
					number_format_i18n( $logs->total )
				)) ?></p>
			</div>
		</div>

		<ul class="logs">

			<?php foreach ( $logs as $log ): ?>

				<?php render_log_row( $log ) ?>

			<?php endforeach ?>

		</ul>

	</div>

	<?php
}

/**
 * Get filter links for the log.
 *
 * @param array $args Arguments passed to the page (typically from $_GET).
 * @return string[] List of links.
 */
function get_filter_links( $args ) {
	$options = [
		'All'        => [ 'success' => false ],
		'Successful' => [ 'success' => 'y' ],
		'Errors'     => [ 'success' => 'n' ],
	];

	/**
	 * Filter options for the log.
	 *
	 * Contains a map of label to query args. If the query args match
	 * the current page's query, the link will be marked "current". The
	 * value can be specified as `false` to ensure the argument is
	 * missing rather than a value.
	 *
	 * @param array $options Filter options, see description for format.
	 */
	$options = apply_filters( 'arachnid.admin.log.filters', $options );

	$links = [];
	foreach ( $options as $name => $query_args ) {
		$url = add_query_arg( $query_args );
		$selected = true;

		foreach ( $query_args as $qk => $qv ) {
			if ( $qv === false && empty( $args[ $qk ] ) ) {
				continue;
			}

			$selected = $selected && isset( $args[ $qk ] ) && ( $qv === $args[ $qk ] );
		}

		$class = $selected ? 'current' : '';

		$links[] = sprintf(
			'<a href="%s" class="%s">%s</a>',
			esc_url( $url ),
			esc_attr( $class ),
			esc_html( $name )
		);
	}

	/**
	 * Filter links for the log.
	 *
	 * Contains links to filtered log pages. The
	 * `arachnid.admin.log.filters` can be used for most links, but this
	 * filter can be used for more complex links.
	 *
	 * @param
	 */
	return apply_filters( 'arachnid.admin.log.filter_links', $links, $args );
}

/**
 * Render a single log entry.
 *
 * @param Entry $entry Entry to render.
 */
function render_log_row( $entry ) {
	$time = $entry->get_timestamp();
	$time_iso = date( 'c', $time );
	$time_formatted = date( 'Y-m-d H:i:s', $time );

	$request = $entry->get_request();
	$response = $entry->get_response();
	$response = is_wp_error( $response ) ? error_to_response( $response ) : rest_ensure_response( $response );

	$status = $response->get_status();
	$success = $status >= 200 && $status < 300;
	switch ( true ) {
		case $status >= 200 && $status < 300:
			$status_class = 'status-success';
			$icon = 'dashicons-yes';
			break;

		case $status >= 300 && $status < 400:
			$status_class = 'status-redirect';
			$icon = 'dashicons-migrate';
			break;

		default:
			$status_class = 'status-error';
			$icon = 'dashicons-no';
			break;
	}

	?>
	<li class="arachnid-entry">
		<header>
			<p class="status-icon <?php echo esc_attr( $status_class ) ?>">
				<i class="dashicons <?php echo esc_attr( $icon ) ?>"></i>
			</p>

			<p class="title"><code><?php echo esc_html( $entry->get_route() ) ?></code></p>

			<?php

			/**
			 * Output extra items into the entry header.
			 *
			 * The parent `<header>` element uses flexbox for layout, so
			 * elements can be visually reordered by using the `order` CSS
			 * property if needed.
			 *
			 * @param Entry $entry
			 */
			do_action( 'arachnid.admin.log.entry_header', $entry );

			?>

			<p class="time"><time datetime="<?php echo esc_attr( $time_iso ) ?>">
				<?php echo esc_html( $time_formatted ) ?></time></p>

			<span class="the-mighty-expando hide-if-no-js"><i class="dashicons dashicons-arrow-down-alt2"></i></span>
		</header>
		<div class="content hide-if-js">
			<h1 class="nav-tab-wrapper">
				<button type="button" data-target="request" class="nav-tab nav-tab-active">Request</button>
				<button type="button" data-target="response" class="nav-tab">Response</button>

				<?php

				/**
				 * Output extra detail tabs.
				 *
				 * Use this action to output extra tabs for switching detail
				 * sections. The tabs should be `<button>` elements with the
				 * .nav-tab class, and a data-target attribute to specify the
				 * corresponding section (matched like `.section-%s`)
				 *
				 * @param Entry $entry
				 */
				do_action( 'arachnid.admin.log.section_tabs', $entry );

				?>
			</h1>

			<div class="section section-request active">

				<?php render_request( $request ) ?>

			</div>

			<div class="section section-response">

				<?php render_response( $response ) ?>

			</div>

			<?php

			/**
			 * Output extra detail sections.
			 *
			 * Use this action to output extra detail sections. The sections
			 * should be `<div>` elements with the .section class, and a
			 * `.section-%s` class to match the corresponding tab's
			 * data-target attribute.
			 *
			 * @param Entry $entry
			 */
			do_action( 'arachnid.admin.log.sections', $entry );

			?>

		</div>
	</li>
	<?php
}

/**
 * Render a header map as HTML.
 *
 * Normalizes header name casing (as they're case-insensitive), and escapes.
 *
 * @param array $headers Map of key => value.
 */
function render_headers( $headers ) {
	foreach ( $headers as $key => $value ) {
		$real_key = str_replace( '_', '-', $key );
		$real_key = ucwords( $real_key, ' -' );
		printf( "<strong>%s</strong>: %s\n", esc_html( $real_key ), esc_html( implode( ', ', $value ) ) );
	}
}

function render_request( WP_REST_Request $request ) {
	$body = $request->get_body();

	// Pretty-print the JSON if we can.
	$decoded = json_decode( $body );
	if ( json_last_error() === JSON_ERROR_NONE && $decoded !== null ) {
		$body = wp_json_encode( $decoded, JSON_PRETTY_PRINT );
	}

	?>

	<h3>Headers</h3>
	<pre><?php render_headers( $request->get_headers() ) ?></pre>

	<h3>Body</h3>
	<pre><?php echo esc_html( $body ) ?></pre>

	<?php
}

function render_response( WP_REST_Response $response ) {
	?>
	<h3>Headers</h3>
	<pre><?php
		printf( 'HTTP/1.0 %d %s', $response->get_status(), get_status_header_desc( $response->get_status() ) );
		render_headers( $response->get_headers() );
	?></pre>
	<h3>Body</h3>
	<pre><?php echo esc_html( wp_json_encode( $response->get_data(), JSON_PRETTY_PRINT ) ) ?></pre>
	<?php
}
