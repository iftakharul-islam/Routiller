<?php

namespace Routiller;

use WP_REST_Request;
use WP_REST_Server;
use WP_Error;
use Routiller\Contracts\ValidatorInterface;
use Routiller\Contracts\SanitizerInterface;
use Routiller\Contracts\PermissionInterface;
use Routiller\Contracts\SchemaInterface;
use Routiller\Contracts\MiddlewareInterface;

/**
 * Registers Routiller routes with the WordPress REST API.
 *
 * Each Registrar instance is scoped to a single REST namespace,
 * so multiple plugins can use Routiller without any conflict.
 */
class Registrar {
    /** @var string The WP REST API namespace (e.g. 'my-plugin/v1') */
    protected $namespace;

    /** @var Route[] */
    protected $routes = [];

    /** @var callable|null Builds controller, permission, middleware, validator and sanitizer instances. */
    protected $resolver;

    public function __construct( string $namespace ) {
        $this->namespace = $namespace;
    }

    /**
     * Set the class resolver (e.g. a DI container's get/make method).
     *
     * @param callable|null $resolver fn( string $class ): object
     */
    public function setResolver( ?callable $resolver ) {
        $this->resolver = $resolver;
    }

    /**
     * Build an instance of a class through the resolver, or `new`.
     *
     * @param string $class
     *
     * @return object
     */
    protected function make( $class ) {
        if ( $this->resolver ) {
            return call_user_func( $this->resolver, $class );
        }

        return new $class();
    }

    /**
     * Register routes with WordPress REST API.
     *
     * @param Route[] $routes
     */
    public function register( array $routes ) {
        $this->routes = $routes;

        add_action( 'rest_api_init', [ $this, 'registerRestRoutes' ] );
    }

    /**
     * Callback for rest_api_init — converts Route objects to WP REST routes.
     */
    public function registerRestRoutes() {
        foreach ( $this->routes as $route ) {
            $uri = '/' . $route->uri;

            $routeArgs = [
                'methods'             => $route->http_verb,
                'callback'            => $this->buildCallback( $route ),
                'args'                => $this->prepareArgs( $route ),
                'permission_callback' => $this->buildPermissionCallback( $route ),
            ];

            if ( $route->schema ) {
                $routeArgs['schema'] = $this->buildSchemaCallback( $route );
            }

            register_rest_route( $this->namespace, $uri, $routeArgs );
        }
    }

    /**
     * Build the route callback: the controller is created only when the
     * route runs, then the request passes through the route's middleware.
     *
     * @param Route $route
     *
     * @return callable
     */
    protected function buildCallback( Route $route ) {
        return function ( WP_REST_Request $request ) use ( $route ) {
            $controller = $this->make( $route->controller );
            $method     = $route->method;

            $pipeline = function ( WP_REST_Request $request ) use ( $controller, $method ) {
                return $controller->{$method}( $request );
            };

            foreach ( array_reverse( $route->middleware ) as $middlewareClass ) {
                $middleware = $this->make( $middlewareClass );

                if ( ! ( $middleware instanceof MiddlewareInterface ) ) {
                    continue;
                }

                $next     = $pipeline;
                $pipeline = function ( WP_REST_Request $request ) use ( $middleware, $next ) {
                    return $middleware->handle( $request, $next );
                };
            }

            return $pipeline( $request );
        };
    }

    /**
     * Build the permission callback for a route.
     *
     * @param Route $route
     *
     * @return callable
     */
    protected function buildPermissionCallback( Route $route ) {
        $permissions = $route->permissions;

        return function ( WP_REST_Request $request ) use ( $permissions ) {
            return $this->checkPermissions( $request, $permissions );
        };
    }

    /**
     * Build the schema callback for a route.
     *
     * WordPress expects `schema` to be a callable that returns a JSON Schema array.
     * This enables self-documentation via OPTIONS requests and the /wp-json index.
     *
     * @param Route $route
     *
     * @return callable
     */
    protected function buildSchemaCallback( Route $route ) {
        $schemaClass = $route->schema;

        return function () use ( $schemaClass ) {
            $schema = $this->make( $schemaClass );

            if ( $schema instanceof SchemaInterface ) {
                return $schema->schema();
            }

            return [];
        };
    }

    /**
     * Check permissions for a request.
     *
     * @param WP_REST_Request $request
     * @param array           $permissions Array of class names.
     *
     * @return bool|WP_Error
     */
    protected function checkPermissions( WP_REST_Request $request, array $permissions ) {
        if ( empty( $permissions ) ) {
            return true;
        }

        $results = [];

        foreach ( $permissions as $permissionClass ) {
            $permission = $this->make( $permissionClass );

            if ( ! ( $permission instanceof PermissionInterface ) ) {
                continue;
            }

            $result = $permission->check( $request );

            // OR logic: the first permission that grants access wins.
            if ( true === $result ) {
                return true;
            }

            $results[] = $result;
        }

        foreach ( $results as $result ) {
            if ( is_wp_error( $result ) ) {
                return $this->mergeErrors( $results );
            }
        }

        return false;
    }

    /**
     * Merge multiple WP_Error objects into one.
     *
     * @param array $results
     *
     * @return WP_Error
     */
    protected function mergeErrors( array $results ) {
        $merged = new WP_Error();

        foreach ( $results as $result ) {
            if ( ! is_wp_error( $result ) ) {
                continue;
            }

            foreach ( $result->get_error_codes() as $code ) {
                foreach ( $result->get_error_messages( $code ) as $message ) {
                    $merged->add( $code, $message );
                }

                $data = $result->get_error_data( $code );

                if ( $data ) {
                    $merged->add_data( $data, $code );
                }
            }
        }

        return $merged;
    }

    /**
     * Prepare WP REST route args (validation + sanitization).
     *
     * @param Route $route
     *
     * @return array
     */
    protected function prepareArgs( Route $route ) {
        $args = [];

        if ( $route->validator ) {
            $validator = $this->makeWithRequestFallback( $route->validator, $route );

            if ( $validator instanceof ValidatorInterface ) {
                $args = $this->applyValidation( $args, $validator );
            }
        }

        if ( $route->sanitizer ) {
            $sanitizer = $this->makeWithRequestFallback( $route->sanitizer, $route );

            if ( $sanitizer instanceof SanitizerInterface ) {
                $args = $this->applySanitization( $args, $sanitizer );
            }
        }

        return $args;
    }

    /**
     * Build a validator or sanitizer.
     *
     * Classes get the real request in validate()/sanitize(). For backward
     * compatibility with 1.0 classes whose constructor requires a request,
     * fall back to a request built from the current HTTP globals.
     *
     * @param string $class
     * @param Route  $route
     *
     * @return object
     */
    protected function makeWithRequestFallback( $class, Route $route ) {
        try {
            return $this->make( $class );
        } catch ( \ArgumentCountError $e ) {
            return new $class( $this->buildRequestObject( $route ) );
        }
    }

    /**
     * Build a WP_REST_Request object from current HTTP request data.
     *
     * @param Route $route
     *
     * @return WP_REST_Request
     */
    protected function buildRequestObject( Route $route ) {
        $request_uri = isset( $_SERVER['REQUEST_URI'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
            : '';

        $url_prefix  = '/' . rest_get_url_prefix();
        $request_uri = substr( $request_uri, strlen( $url_prefix ) );

        $wp_route = '/' . $this->namespace . '/' . $route->uri;
        $request  = new WP_REST_Request( $route->http_verb, $request_uri );

        $this->appendHeaders( $request );
        $this->appendUriParams( $request, $wp_route );
        $this->appendBodyParams( $request );

        return $request;
    }

    /**
     * Append HTTP headers to the request.
     *
     * @param WP_REST_Request $request
     */
    protected function appendHeaders( WP_REST_Request $request ) {
        $copy_server = [
            'CONTENT_TYPE'    => 'Content-Type',
            'CONTENT_LENGTH'  => 'Content-Length',
            'CONTENT_MD5'     => 'Content-Md5',
            'HTTP_X_WP_NONCE' => 'HTTP_X_WP_NONCE',
        ];

        foreach ( $_SERVER as $key => $value ) {
            if ( substr( $key, 0, 5 ) === 'HTTP_' ) {
                $header_key = substr( $key, 5 );

                if ( ! isset( $copy_server[ $header_key ] ) || ! isset( $_SERVER[ $header_key ] ) ) {
                    $header_key = str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', $header_key ) ) ) );
                    $request->add_header( $header_key, $value );
                }
            } elseif ( isset( $copy_server[ $key ] ) ) {
                $request->add_header( $copy_server[ $key ], $value );
            }
        }

        if ( ! $request->get_header( 'Authorization' ) ) {
            if ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
                $request->add_header( 'Authorization', sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) );
            } elseif ( isset( $_SERVER['PHP_AUTH_USER'] ) ) {
                $user = sanitize_text_field( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) );
                $pass = isset( $_SERVER['PHP_AUTH_PW'] ) ? sanitize_text_field( wp_unslash( $_SERVER['PHP_AUTH_PW'] ) ) : '';
                $request->add_header( 'Authorization', 'Basic ' . base64_encode( $user . ':' . $pass ) );
            } elseif ( isset( $_SERVER['PHP_AUTH_DIGEST'] ) ) {
                $request->add_header( 'Authorization', sanitize_text_field( wp_unslash( $_SERVER['PHP_AUTH_DIGEST'] ) ) );
            }
        }
    }

    /**
     * Extract URI parameters and set them on the request.
     *
     * @param WP_REST_Request $request
     * @param string          $routePattern
     */
    protected function appendUriParams( WP_REST_Request $request, $routePattern ) {
        $request_uri = $request->get_route();
        $uri_parts   = explode( '/', $request_uri );
        $route_parts = explode( '/', $routePattern );
        $params      = [];

        if ( count( $uri_parts ) === count( $route_parts ) ) {
            foreach ( $uri_parts as $key => $value ) {
                if ( $value === $route_parts[ $key ] ) {
                    continue;
                }

                if ( preg_match( '/^\(\?P<([^>]+)>/', $route_parts[ $key ], $matches ) ) {
                    $params[ $matches[1] ] = $value;
                }
            }
        }

        $request->set_url_params( $params );
    }

    /**
     * Append query, body and file params to the request.
     *
     * @param WP_REST_Request $request
     */
    protected function appendBodyParams( WP_REST_Request $request ) {
        $nonce = $request->get_header( 'x_wp_nonce' );

        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return;
        }

        $request->set_query_params( wp_unslash( $_GET ) );
        $request->set_body_params( wp_unslash( $_POST ) );
        $request->set_file_params( $_FILES );
    }

    /**
     * Apply validation rules to REST route args.
     *
     * @param array              $args
     * @param ValidatorInterface $validator
     *
     * @return array
     */
    protected function applyValidation( array $args, ValidatorInterface $validator ) {
        $rules = $validator->rules();

        foreach ( $rules as $key => $rule ) {
            $args[ $key ] = [
                'required'          => in_array( 'required', explode( '|', $rule ) ),
                'validate_callback' => function ( $param, $request, $key ) use ( $validator ) {
                    if ( $validator->validate( $request, $key ) ) {
                        return true;
                    }

                    return new WP_Error(
                        'rest_invalid_param',
                        $validator->get_errors( $key ),
                        [ 'status' => 400 ]
                    );
                },
            ];
        }

        return $args;
    }

    /**
     * Apply sanitization callbacks to REST route args.
     *
     * @param array              $args
     * @param SanitizerInterface $sanitizer
     *
     * @return array
     */
    protected function applySanitization( array $args, SanitizerInterface $sanitizer ) {
        $filters = $sanitizer->filters();

        foreach ( $filters as $key => $filter ) {
            $args[ $key ]['sanitize_callback'] = function ( $param, $request, $key ) use ( $sanitizer ) {
                return $sanitizer->sanitize( $request, $key );
            };
        }

        return $args;
    }
}
