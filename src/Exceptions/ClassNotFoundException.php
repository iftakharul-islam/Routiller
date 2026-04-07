<?php

namespace Routiller\Exceptions;

use RuntimeException;

class ClassNotFoundException extends RuntimeException {
    public function __construct( string $class ) {
        parent::__construct( "Controller class '{$class}' not found." );
    }
}
