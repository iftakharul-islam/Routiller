# Routiller

Laravel-like REST API routing for WordPress plugins. Zero conflicts when multiple plugins use the same package.

## Install

```bash
composer require routiller/routiller
```

## Quick Start

```php
use Routiller\Routiller;
use App\Controllers\ProjectController;
use App\Permissions\Authentic;

// Create an isolated router instance for your plugin
$routiller = Routiller::create( 'my-plugin/v1' );
$router    = $routiller->router();

// Define routes
$router->get( 'projects', [ProjectController::class, 'index'] )
    ->permission( [Authentic::class] );

$router->post( 'projects', [ProjectController::class, 'store'] )
    ->permission( [Authentic::class] )
    ->validator( CreateProjectValidator::class )
    ->sanitizer( ProjectSanitizer::class );

$router->get( 'projects/{id}', [ProjectController::class, 'show'] );
$router->put( 'projects/{id}', [ProjectController::class, 'update'] );
$router->delete( 'projects/{id}', [ProjectController::class, 'destroy'] );

// Register with WordPress
$routiller->register();
```

Your routes are now available at `/wp-json/my-plugin/v1/projects`.

## Route Groups

```php
$router->group( ['prefix' => 'projects/{project_id}'], function ( $router ) {
    $router->get( 'tasks', [TaskController::class, 'index'] );
    $router->post( 'tasks', [TaskController::class, 'store'] );
    $router->get( 'tasks/{id}', [TaskController::class, 'show'] );
});
// Produces: projects/(?P<project_id>\d+)/tasks, etc.
```

Groups support `prefix`, `middleware`, and `permission` attributes. They nest properly.

## Loading Route Files

Instead of defining all routes inline, load them from a directory:

```php
$routiller = Routiller::create( 'my-plugin/v1' );
$routiller->loadRoutes( __DIR__ . '/routes' );
$routiller->register();
```

Each file in `routes/` receives `$router`:

```php
// routes/projects.php
$router->get( 'projects', [ProjectController::class, 'index'] );
$router->post( 'projects', [ProjectController::class, 'store'] );
```

## URI Parameters

| Syntax | Regex | Matches |
|---|---|---|
| `{id}` | `(?P<id>\d+)` | Numeric (default) |
| `{slug:string}` | `(?P<slug>[a-zA-Z0-9_-]+)` | Alphanumeric string |
| `{path:any}` | `(?P<path>.+)` | Anything |

## Permissions

Implement `Routiller\Contracts\PermissionInterface`:

```php
use Routiller\Contracts\PermissionInterface;
use WP_REST_Request;

class Authentic implements PermissionInterface {
    public function check( WP_REST_Request $request ) {
        return is_user_logged_in();
    }
}

class AdminOnly implements PermissionInterface {
    public function check( WP_REST_Request $request ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error( 'forbidden', 'Admin access required.', ['status' => 403] );
        }
        return true;
    }
}
```

Multiple permissions use OR logic — if any returns `true`, access is granted.

## Validators

Implement `Routiller\Contracts\ValidatorInterface`:

```php
use Routiller\Contracts\ValidatorInterface;
use WP_REST_Request;

class CreateProjectValidator implements ValidatorInterface {
    public function rules() {
        return [
            'title' => 'required|string',
            'description' => 'string',
        ];
    }

    public function validate( WP_REST_Request $request, $key ) {
        // Your validation logic
    }

    public function get_errors( $key = null ) {
        // Return error messages
    }
}
```

## Schema

Make your endpoints self-documenting by attaching a JSON Schema. WordPress serves this via `OPTIONS` requests and the `/wp-json` discovery index — clients can introspect your API without any external docs.

Implement `Routiller\Contracts\SchemaInterface`:

```php
use Routiller\Contracts\SchemaInterface;

class ProjectSchema implements SchemaInterface {
    public function schema() {
        return [
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => 'project',
            'type'       => 'object',
            'properties' => [
                'id' => [
                    'description' => 'Unique identifier for the project.',
                    'type'        => 'integer',
                    'context'     => ['view', 'edit'],
                    'readonly'    => true,
                ],
                'title' => [
                    'description' => 'The title of the project.',
                    'type'        => 'string',
                    'context'     => ['view', 'edit'],
                    'required'    => true,
                ],
                'status' => [
                    'description' => 'Project status.',
                    'type'        => 'string',
                    'enum'        => ['active', 'archived', 'completed'],
                    'context'     => ['view', 'edit'],
                ],
                'created_at' => [
                    'description' => 'The date the project was created.',
                    'type'        => 'string',
                    'format'      => 'date-time',
                    'context'     => ['view'],
                    'readonly'    => true,
                ],
            ],
        ];
    }
}
```

Chain it on any route:

```php
$router->get( 'projects', [ProjectController::class, 'index'] )
    ->permission( [Authentic::class] )
    ->schema( ProjectSchema::class );

$router->get( 'projects/{id}', [ProjectController::class, 'show'] )
    ->schema( ProjectSchema::class );
```

Now `OPTIONS /wp-json/my-plugin/v1/projects` returns the full schema, and clients like JavaScript frontends or mobile apps can auto-generate types from it.

### Schema `context`

The `context` array controls which fields appear in different views:
- `'view'` — fields returned on normal GET requests
- `'edit'` — fields returned when editing (typically includes more detail)
- `'embed'` — minimal fields for `_embed` responses

### Schema with groups

```php
$router->group( ['prefix' => 'projects'], function ( $router ) {
    $router->get( '', [ProjectController::class, 'index'] )
        ->schema( ProjectCollectionSchema::class );

    $router->get( '{id}', [ProjectController::class, 'show'] )
        ->schema( ProjectSchema::class );

    $router->post( '', [ProjectController::class, 'store'] )
        ->schema( ProjectSchema::class )
        ->validator( CreateProjectValidator::class );
});
```

## Sanitizers

Implement `Routiller\Contracts\SanitizerInterface`:

```php
use Routiller\Contracts\SanitizerInterface;
use WP_REST_Request;

class ProjectSanitizer implements SanitizerInterface {
    public function filters() {
        return [
            'title'       => 'sanitize_text_field',
            'description' => 'wp_kses_post',
        ];
    }

    public function sanitize( WP_REST_Request $request, $key ) {
        // Your sanitization logic
    }
}
```

## Multi-Plugin Safety

The core design principle: **each plugin gets its own isolated instance**.

```php
// Plugin A
$a = Routiller::create( 'plugin-a/v1' );
$a->router()->get( 'items', [AController::class, 'index'] );
$a->register();

// Plugin B (same package, zero conflicts)
$b = Routiller::create( 'plugin-b/v2' );
$b->router()->get( 'items', [BController::class, 'index'] );
$b->register();
```

- Routes are stored per-instance, not in global statics
- Different namespaces = different WP REST endpoints
- `Routiller::create()` returns the same instance for the same namespace (safe to call multiple times)

## Requirements

- PHP >= 7.4
- WordPress (REST API)

## License

MIT
