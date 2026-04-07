<?php

namespace Routiller\Exceptions;

use RuntimeException;

class UndefinedMethodException extends RuntimeException {
    public function __construct( string $controller, string $method ) {
        parent::__construct( "Method '{$method}' not found in controller '{$controller}'." );
    }
}
