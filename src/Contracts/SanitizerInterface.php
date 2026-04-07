<?php

namespace Routiller\Contracts;

use WP_REST_Request;

interface SanitizerInterface {
    /**
     * Return sanitization filters as an associative array.
     *
     * Keys are parameter names, values are pipe-separated filter strings.
     * Example: ['title' => 'sanitize_text_field', 'content' => 'wp_kses_post']
     *
     * @return array
     */
    public function filters();

    /**
     * Sanitize a specific field from the request.
     *
     * @param WP_REST_Request $request
     * @param string          $key
     *
     * @return mixed The sanitized value.
     */
    public function sanitize( WP_REST_Request $request, $key );
}
