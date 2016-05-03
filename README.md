# Arachnid

Catch API requests in your web. Arachnid logs selected endpoints to your database for later usage, and allows storing custom data on logs for custom interfaces and other usage.

## How to Use

Arachnid is currently opt-in by endpoints. In your `register_rest_route` call, simply add `'arachnid_log' => true`.

You can also add custom data to the log entry by setting the `arachnid_log_callback` parameter in your registration, which specifies a callback to call after the entry has been created. This callback takes three parameters: the `Entry` item, the `WP_REST_Request` that generated the request, and your response data.

The `Entry` object has a bunch of methods in the public API, including `get_meta( $key, $default = null )` and `set_meta( $key, $value )` for storing your custom data. (Unlike WP's meta, this is a key-value store and cannot hold multiple items.)

For example:

```php
register_rest_route( 'example/v1', '/foo', [
	'methods' => 'GET, POST',
	'callback' => '__return_false',

	'arachnid_log' => true,
	'arachnid_log_callback' => function ( \Arachnid\Entry $entry, $request, $response ) {
		$entry->set_meta( 'foo', 'bar' );
	}
]);
```
