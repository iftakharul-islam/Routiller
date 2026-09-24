# Changelog

## 1.1.0

- Added: middleware now runs. `->middleware()` and group `middleware` wrap the controller call through `Contracts\MiddlewareInterface`.
- Added: `Routiller::resolver()` to build classes through a DI container.
- Changed: controllers are created lazily, only when their route is called.
- Changed: validators and sanitizers no longer receive a request built from `$_SERVER` at registration. They get the real request in `validate()` / `sanitize()`. 1.0 classes whose constructor requires a request still work.
- Fixed: permissions now follow the documented OR logic. A permission that returns `true` grants access even if another returns a `WP_Error`.
- Fixed: PHP 8.4 deprecation for the implicitly nullable `Router::__construct()` parameter.

## 1.0.0

- First release.
