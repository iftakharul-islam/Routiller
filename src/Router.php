<?php

namespace Routiller;

/**
 * Fluent route definition builder.
 *
 * Each Router instance is scoped to a single plugin — no shared static state.
 *
 * Usage:
 *   $router->get('projects/{id}', [ProjectController::class, 'show'])
 *       ->permission([AccessProject::class])
 *       ->validator(CreateProjectValidator::class);
 */
class Router {
    /** @var Route[] */
    protected $routes = [];

    /** @var UriParser */
    protected $uriParser;

    /** @var string URI prefix applied to all routes in this group */
    protected $groupPrefix = '';

    /** @var array Middleware applied to all routes in this group */
    protected $groupMiddleware = [];

    /** @var array Permissions applied to all routes in this group */
    protected $groupPermissions = [];

    public function __construct( ?UriParser $uriParser = null ) {
        $this->uriParser = $uriParser ?: new UriParser();
    }

    /**
     * Define a route group with shared attributes.
     *
     * @param array    $attributes ['prefix' => '...', 'middleware' => [...], 'permission' => [...]]
     * @param callable $callback
     *
     * @return $this
     */
    public function group( array $attributes, callable $callback ) {
        $previousPrefix      = $this->groupPrefix;
        $previousMiddleware   = $this->groupMiddleware;
        $previousPermissions  = $this->groupPermissions;

        if ( isset( $attributes['prefix'] ) ) {
            $this->groupPrefix = trim( $previousPrefix . '/' . trim( $attributes['prefix'], '/' ), '/' );
        }

        if ( isset( $attributes['middleware'] ) ) {
            $this->groupMiddleware = array_merge( $previousMiddleware, (array) $attributes['middleware'] );
        }

        if ( isset( $attributes['permission'] ) ) {
            $this->groupPermissions = array_merge( $previousPermissions, (array) $attributes['permission'] );
        }

        $callback( $this );

        $this->groupPrefix      = $previousPrefix;
        $this->groupMiddleware   = $previousMiddleware;
        $this->groupPermissions  = $previousPermissions;

        return $this;
    }

    /**
     * Register a GET route.
     *
     * @param string $uri
     * @param array  $handler [Controller::class, 'method']
     *
     * @return $this
     */
    public function get( $uri, $handler ) {
        return $this->addRoute( $uri, $handler, 'GET' );
    }

    /**
     * Register a POST route.
     *
     * @param string $uri
     * @param array  $handler [Controller::class, 'method']
     *
     * @return $this
     */
    public function post( $uri, $handler ) {
        return $this->addRoute( $uri, $handler, 'POST' );
    }

    /**
     * Register a PUT route.
     *
     * @param string $uri
     * @param array  $handler [Controller::class, 'method']
     *
     * @return $this
     */
    public function put( $uri, $handler ) {
        return $this->addRoute( $uri, $handler, 'PUT' );
    }

    /**
     * Register a DELETE route.
     *
     * @param string $uri
     * @param array  $handler [Controller::class, 'method']
     *
     * @return $this
     */
    public function delete( $uri, $handler ) {
        return $this->addRoute( $uri, $handler, 'DELETE' );
    }

    /**
     * Register a PATCH route.
     *
     * @param string $uri
     * @param array  $handler [Controller::class, 'method']
     *
     * @return $this
     */
    public function patch( $uri, $handler ) {
        return $this->addRoute( $uri, $handler, 'PATCH' );
    }

    /**
     * Apply permission classes to the last registered route.
     *
     * @param array $permissions Array of class names implementing PermissionInterface.
     *
     * @return $this
     */
    public function permission( array $permissions ) {
        $route = $this->lastRoute();

        if ( $route ) {
            $route->permissions = array_merge( $route->permissions, $permissions );
        }

        return $this;
    }

    /**
     * Apply a validator class to the last registered route.
     *
     * @param string $validator Class name implementing ValidatorInterface.
     *
     * @return $this
     */
    public function validator( $validator ) {
        $route = $this->lastRoute();

        if ( $route ) {
            $route->validator = $validator;
        }

        return $this;
    }

    /**
     * Apply a sanitizer class to the last registered route.
     *
     * @param string $sanitizer Class name implementing SanitizerInterface.
     *
     * @return $this
     */
    public function sanitizer( $sanitizer ) {
        $route = $this->lastRoute();

        if ( $route ) {
            $route->sanitizer = $sanitizer;
        }

        return $this;
    }

    /**
     * Apply a schema class to the last registered route.
     *
     * The schema makes the endpoint self-documenting via OPTIONS requests
     * and the WP REST API index at /wp-json.
     *
     * @param string $schema Class name implementing SchemaInterface.
     *
     * @return $this
     */
    public function schema( $schema ) {
        $route = $this->lastRoute();

        if ( $route ) {
            $route->schema = $schema;
        }

        return $this;
    }

    /**
     * Apply middleware classes to the last registered route.
     *
     * @param array $middleware
     *
     * @return $this
     */
    public function middleware( array $middleware ) {
        $route = $this->lastRoute();

        if ( $route ) {
            $route->middleware = array_merge( $route->middleware, $middleware );
        }

        return $this;
    }

    /**
     * Get all registered routes.
     *
     * @return Route[]
     */
    public function getRoutes() {
        return $this->routes;
    }

    /**
     * Parse and store a route.
     *
     * @param string $uri
     * @param array  $handler
     * @param string $httpVerb
     *
     * @return $this
     */
    protected function addRoute( $uri, $handler, $httpVerb ) {
        $handler = $this->uriParser->parseHandler( $handler );

        $controller = $this->uriParser->resolveController( $handler[0] );
        $method     = $this->uriParser->resolveMethod( $controller, $handler[1] );

        $fullUri = $this->groupPrefix
            ? trim( $this->groupPrefix . '/' . trim( $uri, '/' ), '/' )
            : $uri;

        $route = new Route( [
            'http_verb'    => $httpVerb,
            'original_uri' => $fullUri,
            'uri'          => $this->uriParser->convertToWpUri( $fullUri ),
            'controller'   => $controller,
            'method'       => $method,
        ] );

        // Apply group-level attributes
        if ( ! empty( $this->groupMiddleware ) ) {
            $route->middleware = $this->groupMiddleware;
        }

        if ( ! empty( $this->groupPermissions ) ) {
            $route->permissions = $this->groupPermissions;
        }

        $this->routes[] = $route;

        return $this;
    }

    /**
     * Get the last registered route.
     *
     * @return Route|null
     */
    protected function lastRoute() {
        if ( empty( $this->routes ) ) {
            return null;
        }

        return $this->routes[ count( $this->routes ) - 1 ];
    }
}
