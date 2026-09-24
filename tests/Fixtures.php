<?php

namespace Routiller\Tests;

use Routiller\Contracts\MiddlewareInterface;
use Routiller\Contracts\PermissionInterface;
use WP_REST_Request;

class Log {
	public static $entries = [];
}

class Greeter {
	public $greeting;

	public function __construct( $greeting ) {
		$this->greeting = $greeting;
	}
}

class HelloController {
	public static $built = 0;

	/** @var Greeter|null */
	private $greeter;

	public function __construct( ?Greeter $greeter = null ) {
		self::$built++;
		$this->greeter = $greeter;
	}

	public function index( WP_REST_Request $request ) {
		Log::$entries[] = 'controller';
		return ( $this->greeter ? $this->greeter->greeting : 'hello' ) . ' ' . $request->get_param( 'name' );
	}
}

class First implements MiddlewareInterface {
	public function handle( WP_REST_Request $request, callable $next ) {
		Log::$entries[] = 'first:before';
		$response       = $next( $request );
		Log::$entries[] = 'first:after';
		return $response;
	}
}

class Second implements MiddlewareInterface {
	public function handle( WP_REST_Request $request, callable $next ) {
		Log::$entries[] = 'second';
		return $next( $request );
	}
}

class Block implements MiddlewareInterface {
	public function handle( WP_REST_Request $request, callable $next ) {
		return new \WP_Error( 'blocked', 'Blocked.', [ 'status' => 403 ] );
	}
}

class Allow implements PermissionInterface {
	public function check( WP_REST_Request $request ) {
		return true;
	}
}

class DenyWithError implements PermissionInterface {
	public function check( WP_REST_Request $request ) {
		return new \WP_Error( 'forbidden', 'No.', [ 'status' => 403 ] );
	}
}

class DenyFalse implements PermissionInterface {
	public function check( WP_REST_Request $request ) {
		return false;
	}
}
