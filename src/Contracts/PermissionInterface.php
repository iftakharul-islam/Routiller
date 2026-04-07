<?php

namespace Routiller\Contracts;

use WP_REST_Request;

interface PermissionInterface {
    /**
     * Check if the current request has permission.
     *
     * @param WP_REST_Request $request
     *
     * @return bool|\WP_Error True if permitted, false or WP_Error otherwise.
     */
    public function check( WP_REST_Request $request );
}
