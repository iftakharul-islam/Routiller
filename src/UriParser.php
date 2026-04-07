<?php

namespace Routiller;

use Routiller\Exceptions\InvalidRouteHandlerException;
use Routiller\Exceptions\ClassNotFoundException;
use Routiller\Exceptions\UndefinedMethodException;

class UriParser {
    /**
     * Validate and return the [class, method] handler array.
     *
     * @param array $handler
     *
     * @return array
     *
     * @throws InvalidRouteHandlerException
     */
    public function parseHandler( array $handler ) {
        if ( count( $handler ) === 2 && is_string( $handler[0] ) && is_string( $handler[1] ) ) {
            return $handler;
        }

        throw new InvalidRouteHandlerException( $handler );
    }

    /**
     * Validate controller class exists.
     *
     * @param string $class
     *
     * @return string Fully qualified class name.
     *
     * @throws ClassNotFoundException
     */
    public function resolveController( string $class ) {
        $class = str_replace( '/', '\\', $class );

        if ( class_exists( $class ) ) {
            return $class;
        }

        throw new ClassNotFoundException( $class );
    }

    /**
     * Validate method exists on the controller.
     *
     * @param string $controller
     * @param string $method
     *
     * @return string
     *
     * @throws UndefinedMethodException
     */
    public function resolveMethod( string $controller, string $method ) {
        if ( method_exists( $controller, $method ) ) {
            return $method;
        }

        throw new UndefinedMethodException( $controller, $method );
    }

    /**
     * Convert Laravel-style URI placeholders {param} to WP REST API regex.
     *
     * Supports:
     *   {id}    -> (?P<id>\d+)        (numeric by default)
     *   {slug:string} -> (?P<slug>[a-zA-Z0-9_-]+)
     *   {any:any}     -> (?P<any>.+)
     *
     * @param string $uri
     *
     * @return string
     */
    public function convertToWpUri( string $uri ) {
        $patterns = [
            // {param:string}
            '/\{([a-zA-Z_][a-zA-Z0-9_]*):string\}/' => function ( $m ) {
                return '(?P<' . $m[1] . '>[a-zA-Z0-9_-]+)';
            },
            // {param:any}
            '/\{([a-zA-Z_][a-zA-Z0-9_]*):any\}/' => function ( $m ) {
                return '(?P<' . $m[1] . '>.+)';
            },
            // {param} — numeric default
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/' => function ( $m ) {
                return '(?P<' . $m[1] . '>\d+)';
            },
        ];

        foreach ( $patterns as $pattern => $callback ) {
            $uri = preg_replace_callback( $pattern, $callback, $uri );
        }

        return $uri;
    }
}
