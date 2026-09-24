<?php
/**
 * Minimal WordPress stubs so Routiller can be tested without WordPress.
 */

require __DIR__ . '/../vendor/autoload.php';

$GLOBALS['routiller_test_actions'] = [];
$GLOBALS['routiller_test_routes']  = [];

function add_action( $hook, $callback ) {
	$GLOBALS['routiller_test_actions'][ $hook ][] = $callback;
}

function do_action( $hook ) {
	foreach ( $GLOBALS['routiller_test_actions'][ $hook ] ?? [] as $callback ) {
		$callback();
	}
}

function register_rest_route( $namespace, $route, $args ) {
	$GLOBALS['routiller_test_routes'][ $namespace . $route ] = $args;
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

class WP_Error {
	public $errors = [];
	public $data   = [];

	public function __construct( $code = '', $message = '', $data = '' ) {
		if ( $code ) {
			$this->add( $code, $message, $data );
		}
	}

	public function add( $code, $message, $data = '' ) {
		$this->errors[ $code ][] = $message;
		if ( $data ) {
			$this->data[ $code ] = $data;
		}
	}

	public function add_data( $data, $code ) {
		$this->data[ $code ] = $data;
	}

	public function get_error_codes() {
		return array_keys( $this->errors );
	}

	public function get_error_messages( $code ) {
		return $this->errors[ $code ] ?? [];
	}

	public function get_error_data( $code ) {
		return $this->data[ $code ] ?? null;
	}
}

class WP_REST_Request {
	public $params = [];

	public function __construct( $params = [] ) {
		$this->params = is_array( $params ) ? $params : [];
	}

	public function get_param( $key ) {
		return $this->params[ $key ] ?? null;
	}
}

class WP_REST_Server {}
