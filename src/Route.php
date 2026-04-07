<?php

namespace Routiller;

/**
 * Represents a single registered route with its metadata.
 */
class Route {
    /** @var string */
    public $http_verb;

    /** @var string Original URI as defined (e.g. 'projects/{id}') */
    public $original_uri;

    /** @var string WP REST regex URI (e.g. '(?P<id>\d+)') */
    public $uri;

    /** @var string Fully qualified controller class */
    public $controller;

    /** @var string Controller method name */
    public $method;

    /** @var array Array of permission class names (must implement PermissionInterface) */
    public $permissions = [];

    /** @var string|null Validator class name (must implement ValidatorInterface) */
    public $validator;

    /** @var string|null Sanitizer class name (must implement SanitizerInterface) */
    public $sanitizer;

    /** @var string|null Schema class name (must implement SchemaInterface) */
    public $schema;

    /** @var array Middleware class names */
    public $middleware = [];

    public function __construct( array $data ) {
        $this->http_verb    = $data['http_verb'];
        $this->original_uri = $data['original_uri'];
        $this->uri          = $data['uri'];
        $this->controller   = $data['controller'];
        $this->method       = $data['method'];
    }
}
