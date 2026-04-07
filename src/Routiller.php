<?php

namespace Routiller;

/**
 * Main entry point for Routiller.
 *
 * Each plugin creates its own Routiller instance with its own namespace.
 * Multiple plugins can use the same Routiller package without any conflict
 * because routes are scoped per instance — no shared global state.
 *
 * Usage:
 *
 *   // In your plugin bootstrap:
 *   $routiller = Routiller::create( 'my-plugin/v1' );
 *
 *   // Define routes (returns a Router instance):
 *   $router = $routiller->router();
 *
 *   $router->get( 'projects', [ProjectController::class, 'index'] )
 *       ->permission( [Authentic::class] );
 *
 *   $router->group( ['prefix' => 'projects/{id}'], function ( $router ) {
 *       $router->get( '', [ProjectController::class, 'show'] );
 *       $router->put( '', [ProjectController::class, 'update'] );
 *       $router->delete( '', [ProjectController::class, 'destroy'] );
 *   });
 *
 *   // Register all routes with WordPress:
 *   $routiller->register();
 *
 *   // Or load route files and then register:
 *   $routiller->loadRoutes( __DIR__ . '/routes' );
 *   $routiller->register();
 */
class Routiller {
    /**
     * Isolated instances keyed by namespace.
     *
     * This ensures that even if two plugins accidentally use the same namespace,
     * calling create() again returns the existing instance rather than overwriting.
     *
     * @var array<string, Routiller>
     */
    protected static $instances = [];

    /** @var string WP REST API namespace */
    protected $namespace;

    /** @var Router */
    protected $router;

    /** @var Registrar */
    protected $registrar;

    /** @var bool Whether routes have been registered */
    protected $registered = false;

    /**
     * Create or retrieve an isolated Routiller instance for a namespace.
     *
     * @param string $namespace WP REST namespace (e.g. 'my-plugin/v1').
     *
     * @return static
     */
    public static function create( string $namespace ) {
        if ( ! isset( static::$instances[ $namespace ] ) ) {
            static::$instances[ $namespace ] = new static( $namespace );
        }

        return static::$instances[ $namespace ];
    }

    protected function __construct( string $namespace ) {
        $this->namespace = $namespace;
        $this->router    = new Router();
        $this->registrar = new Registrar( $namespace );
    }

    /**
     * Get the Router instance to define routes.
     *
     * @return Router
     */
    public function router() {
        return $this->router;
    }

    /**
     * Get the REST namespace.
     *
     * @return string
     */
    public function getNamespace() {
        return $this->namespace;
    }

    /**
     * Load all route files from a directory.
     *
     * Each route file receives `$router` as the Router instance.
     *
     * @param string $directory Absolute path to the routes directory.
     *
     * @return $this
     */
    public function loadRoutes( string $directory ) {
        $files = glob( rtrim( $directory, '/' ) . '/*.php' );

        if ( ! $files ) {
            return $this;
        }

        $router = $this->router;

        foreach ( $files as $file ) {
            ( function () use ( $file, $router ) {
                require $file;
            } )();
        }

        return $this;
    }

    /**
     * Register all defined routes with WordPress REST API.
     *
     * Safe to call multiple times — only registers once.
     *
     * @return $this
     */
    public function register() {
        if ( $this->registered ) {
            return $this;
        }

        $this->registrar->register( $this->router->getRoutes() );
        $this->registered = true;

        return $this;
    }

    /**
     * Get all registered Route objects.
     *
     * @return Route[]
     */
    public function getRoutes() {
        return $this->router->getRoutes();
    }

    /**
     * Check if an instance exists for the given namespace.
     *
     * @param string $namespace
     *
     * @return bool
     */
    public static function has( string $namespace ) {
        return isset( static::$instances[ $namespace ] );
    }

    /**
     * Reset all instances (useful for testing).
     */
    public static function reset() {
        static::$instances = [];
    }
}
