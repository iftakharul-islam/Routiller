<?php

namespace Routiller\Exceptions;

use InvalidArgumentException;

class InvalidRouteHandlerException extends InvalidArgumentException {
    public function __construct( $handler ) {
        $message = 'Route handler must be an array of [ControllerClass::class, \'method\'].';

        if ( is_array( $handler ) ) {
            $message .= ' Got array with ' . count( $handler ) . ' element(s).';
        }

        parent::__construct( $message );
    }
}
