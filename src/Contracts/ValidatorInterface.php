<?php

namespace Routiller\Contracts;

use WP_REST_Request;

interface ValidatorInterface {
    /**
     * Return validation rules as an associative array.
     *
     * Keys are parameter names, values are pipe-separated rule strings.
     * Example: ['title' => 'required|string', 'email' => 'required|email']
     *
     * @return array
     */
    public function rules();

    /**
     * Validate a specific field from the request.
     *
     * @param WP_REST_Request $request
     * @param string          $key
     *
     * @return bool
     */
    public function validate( WP_REST_Request $request, $key );

    /**
     * Get validation error messages for a specific field (or all fields).
     *
     * @param string|null $key
     *
     * @return mixed
     */
    public function get_errors( $key = null );
}
