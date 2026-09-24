<?php

namespace Routiller\Tests;

use PHPUnit\Framework\TestCase;
use Routiller\Routiller;
use WP_REST_Request;

require_once __DIR__ . '/Fixtures.php';

class RegistrarTest extends TestCase {
	protected function setUp(): void {
		Routiller::reset();
		$GLOBALS['routiller_test_actions'] = [];
		$GLOBALS['routiller_test_routes']  = [];
		Log::$entries                      = [];
		HelloController::$built            = 0;
	}

	private function register( callable $define, ?callable $resolver = null ) {
		$routiller = Routiller::create( 'test/v1' );
		if ( $resolver ) {
			$routiller->resolver( $resolver );
		}
		$define( $routiller->router() );
		$routiller->register();
		do_action( 'rest_api_init' );
		return $GLOBALS['routiller_test_routes'];
	}

	public function test_controller_is_built_lazily() {
		$routes = $this->register(
			function ( $router ) {
				$router->get( 'hello', [ HelloController::class, 'index' ] );
			}
		);

		$this->assertSame( 0, HelloController::$built );

		$result = $routes['test/v1/hello']['callback']( new WP_REST_Request( [ 'name' => 'Sam' ] ) );

		$this->assertSame( 'hello Sam', $result );
		$this->assertSame( 1, HelloController::$built );
	}

	public function test_resolver_builds_controller() {
		$routes = $this->register(
			function ( $router ) {
				$router->get( 'hello', [ HelloController::class, 'index' ] );
			},
			function ( $class ) {
				return HelloController::class === $class ? new HelloController( new Greeter( 'hi' ) ) : new $class();
			}
		);

		$this->assertSame( 'hi Sam', $routes['test/v1/hello']['callback']( new WP_REST_Request( [ 'name' => 'Sam' ] ) ) );
	}

	public function test_middleware_runs_in_order_group_first() {
		$routes = $this->register(
			function ( $router ) {
				$router->group(
					[ 'middleware' => [ First::class ] ],
					function ( $router ) {
						$router->get( 'hello', [ HelloController::class, 'index' ] )->middleware( [ Second::class ] );
					}
				);
			}
		);

		$routes['test/v1/hello']['callback']( new WP_REST_Request() );

		$this->assertSame( [ 'first:before', 'second', 'controller', 'first:after' ], Log::$entries );
	}

	public function test_middleware_can_stop_request() {
		$routes = $this->register(
			function ( $router ) {
				$router->get( 'hello', [ HelloController::class, 'index' ] )->middleware( [ Block::class ] );
			}
		);

		$result = $routes['test/v1/hello']['callback']( new WP_REST_Request() );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertNotContains( 'controller', Log::$entries );
	}

	public function test_any_true_permission_grants_access() {
		$routes = $this->register(
			function ( $router ) {
				$router->get( 'hello', [ HelloController::class, 'index' ] )->permission( [ DenyWithError::class, Allow::class ] );
			}
		);

		$this->assertTrue( $routes['test/v1/hello']['permission_callback']( new WP_REST_Request() ) );
	}

	public function test_denied_permissions_return_error_or_false() {
		$routes = $this->register(
			function ( $router ) {
				$router->get( 'a', [ HelloController::class, 'index' ] )->permission( [ DenyFalse::class, DenyWithError::class ] );
				$router->get( 'b', [ HelloController::class, 'index' ] )->permission( [ DenyFalse::class ] );
			}
		);

		$this->assertInstanceOf( \WP_Error::class, $routes['test/v1/a']['permission_callback']( new WP_REST_Request() ) );
		$this->assertFalse( $routes['test/v1/b']['permission_callback']( new WP_REST_Request() ) );
	}
}
