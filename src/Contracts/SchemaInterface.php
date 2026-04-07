<?php

namespace Routiller\Contracts;

/**
 * Defines the JSON Schema for a REST API endpoint's response.
 *
 * WordPress REST API uses JSON Schema to describe what an endpoint returns.
 * This makes your API self-documenting — clients can discover the shape of
 * responses by hitting the OPTIONS endpoint or the /wp-json index.
 *
 * Implementation example:
 *
 *   class ProjectSchema implements SchemaInterface {
 *       public function schema() {
 *           return [
 *               '$schema'    => 'http://json-schema.org/draft-04/schema#',
 *               'title'      => 'project',
 *               'type'       => 'object',
 *               'properties' => [
 *                   'id' => [
 *                       'description' => 'Unique identifier for the project.',
 *                       'type'        => 'integer',
 *                       'context'     => ['view', 'edit'],
 *                       'readonly'    => true,
 *                   ],
 *                   'title' => [
 *                       'description' => 'The title of the project.',
 *                       'type'        => 'string',
 *                       'context'     => ['view', 'edit'],
 *                       'required'    => true,
 *                   ],
 *               ],
 *           ];
 *       }
 *   }
 */
interface SchemaInterface {
    /**
     * Return the JSON Schema array describing this endpoint's response.
     *
     * @return array JSON Schema compatible array.
     *
     * @see https://developer.wordpress.org/rest-api/extending-the-rest-api/schema/
     */
    public function schema();
}
