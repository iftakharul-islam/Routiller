<?php

namespace Routiller\Contracts;

use WP_REST_Request;

/**
 * Middleware wraps a route's controller call.
 *
 * Call `$next( $request )` to continue down the pipeline, or return a
 * WP_Error / WP_REST_Response to stop the request early.
 *
 *   class LogRequests implements MiddlewareInterface {
 *       public function handle( WP_REST_Request $request, callable $next ) {
 *           $response = $next( $request );
 *           error_log( $request->get_route() );
 *           return $response;
 *       }
 *   }
 */
interface MiddlewareInterface {
    /**
     * Handle the request.
     *
     * @param WP_REST_Request $request
     * @param callable        $next    Receives the request, returns the response.
     *
     * @return mixed Controller result, WP_REST_Response or WP_Error.
     */
    public function handle( WP_REST_Request $request, callable $next );
}
