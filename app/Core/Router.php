<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP router: route registration, params with constraints, groups,
 * middleware pipelines, named routes, subdomain routing, 404/405.
 */
final class Router
{
    private static ?self $instance = null;

    /** @var array<string, array<int, array>> method => routes */
    private array $routes = [];

    /** @var array<string, array> name => route */
    private array $named = [];

    private array $groupStack = [];

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // -- Registration --------------------------------------------------------

    public static function get(string $uri, callable|array|string $handler, array $middleware = []): RouteDefinition
    {
        return self::instance()->add(['GET', 'HEAD'], $uri, $handler, $middleware);
    }

    public static function post(string $uri, callable|array|string $handler, array $middleware = []): RouteDefinition
    {
        return self::instance()->add(['POST'], $uri, $handler, $middleware);
    }

    public static function put(string $uri, callable|array|string $handler, array $middleware = []): RouteDefinition
    {
        return self::instance()->add(['PUT'], $uri, $handler, $middleware);
    }

    public static function patch(string $uri, callable|array|string $handler, array $middleware = []): RouteDefinition
    {
        return self::instance()->add(['PATCH'], $uri, $handler, $middleware);
    }

    public static function delete(string $uri, callable|array|string $handler, array $middleware = []): RouteDefinition
    {
        return self::instance()->add(['DELETE'], $uri, $handler, $middleware);
    }

    public static function any(string $uri, callable|array|string $handler, array $middleware = []): RouteDefinition
    {
        return self::instance()->add(['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], $uri, $handler, $middleware);
    }

    /**
     * Route group with shared prefix / middleware / name prefix.
     * Router::group(['prefix' => '/admin', 'middleware' => ['auth','admin'], 'name' => 'admin.'], fn () => ...)
     */
    public static function group(array $attributes, callable $callback): void
    {
        $self = self::instance();
        $self->groupStack[] = $attributes;
        $callback();
        array_pop($self->groupStack);
    }

    private function add(array $methods, string $uri, callable|array|string $handler, array $middleware): RouteDefinition
    {
        $prefix = '';
        $groupMiddleware = [];
        $namePrefix = '';
        $domain = null;

        foreach ($this->groupStack as $group) {
            if (!empty($group['prefix'])) {
                $prefix .= '/' . trim((string) $group['prefix'], '/');
            }
            if (!empty($group['middleware'])) {
                $groupMiddleware = array_merge($groupMiddleware, (array) $group['middleware']);
            }
            if (!empty($group['name'])) {
                $namePrefix .= (string) $group['name'];
            }
            if (!empty($group['domain'])) {
                $domain = (string) $group['domain'];
            }
        }

        $uri = '/' . trim($prefix . '/' . trim($uri, '/'), '/');
        if ($uri === '//') {
            $uri = '/';
        }

        $route = [
            'uri' => $uri,
            'handler' => $handler,
            'middleware' => array_merge($groupMiddleware, $middleware),
            'name' => null,
            'name_prefix' => $namePrefix,
            'where' => [],
            'domain' => $domain,
            'regex' => null,
            'params' => [],
        ];

        $definition = new RouteDefinition($this, $route);
        foreach ($methods as $method) {
            $this->routes[$method] ??= [];
            $this->routes[$method][] = &$definition->route;
        }

        return $definition;
    }

    public function registerName(string $name, array &$route): void
    {
        $this->named[$name] = &$route;
    }

    // -- URL generation ------------------------------------------------------

    public function url(string $name, array $params = []): string
    {
        if (!isset($this->named[$name])) {
            return '/';
        }
        $uri = $this->named[$name]['uri'];
        foreach ($params as $key => $value) {
            $uri = preg_replace('/\{' . preg_quote((string) $key, '/') . '\??\}/', rawurlencode((string) $value), $uri) ?? $uri;
        }
        // Remove unfilled optional params
        $uri = preg_replace('#/\{[^}]+\?\}#', '', $uri) ?? $uri;
        return $uri;
    }

    // -- Dispatch ------------------------------------------------------------

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();
        $host = $request->host();

        $lookupMethod = $method === 'HEAD' ? 'GET' : $method;
        $candidates = $this->routes[$lookupMethod] ?? [];

        foreach ($candidates as $route) {
            $params = $this->match($route, $path, $host);
            if ($params === null) {
                continue;
            }
            $request->setRouteParams($params);
            $this->runRoute($route, $request);
            return;
        }

        // 405 detection: does the path match under another verb?
        foreach ($this->routes as $otherMethod => $routes) {
            if ($otherMethod === $lookupMethod) {
                continue;
            }
            foreach ($routes as $route) {
                if ($this->match($route, $path, $host) !== null) {
                    Response::abort(405);
                }
            }
        }

        Response::abort(404);
    }

    private function match(array $route, string $path, string $host): ?array
    {
        $params = [];

        if ($route['domain'] !== null) {
            $domainRegex = '#^' . preg_replace('/\\\{(\w+)\\\}/', '(?P<$1>[^.]+)', preg_quote($route['domain'], '#')) . '$#i';
            if (!preg_match($domainRegex, $host, $dm)) {
                return null;
            }
            foreach ($dm as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
        }

        $regex = $this->compile($route);
        if (!preg_match($regex, $path, $m)) {
            return null;
        }
        foreach ($m as $key => $value) {
            if (is_string($key) && $value !== '') {
                $params[$key] = rawurldecode($value);
            }
        }
        return $params;
    }

    private function compile(array $route): string
    {
        if ($route['regex'] !== null) {
            return $route['regex'];
        }

        $uri = $route['uri'];
        $where = $route['where'];

        if ($uri === '/') {
            return '#^/$#';
        }

        $parts = [];
        foreach (explode('/', trim($uri, '/')) as $segment) {
            if (preg_match('/^\{(\w+)(\?)?\}$/', $segment, $m)) {
                // Whole segment is a parameter
                $pattern = $where[$m[1]] ?? '[^/]+';
                if (($m[2] ?? '') === '?') {
                    $parts[] = '(?:/(?P<' . $m[1] . '>' . $pattern . '))?';
                } else {
                    $parts[] = '/(?P<' . $m[1] . '>' . $pattern . ')';
                }
            } else {
                // Literal segment, possibly with embedded params: file-{name}.zip
                $escaped = preg_quote($segment, '#');
                $escaped = preg_replace_callback('/\\\\\{(\w+)\\\\\}/', function ($mm) use ($where) {
                    $pattern = $where[$mm[1]] ?? '[^/]+';
                    return '(?P<' . $mm[1] . '>' . $pattern . ')';
                }, $escaped) ?? $escaped;
                $parts[] = '/' . $escaped;
            }
        }

        return '#^' . implode('', $parts) . '$#';
    }

    private function runRoute(array $route, Request $request): void
    {
        $pipeline = array_values(array_unique($route['middleware']));

        $core = function () use ($route, $request) {
            $handler = $route['handler'];

            if (is_callable($handler) && !is_array($handler) && !is_string($handler)) {
                $result = $handler($request);
            } else {
                if (is_string($handler) && str_contains($handler, '@')) {
                    [$class, $method] = explode('@', $handler, 2);
                } elseif (is_array($handler) && count($handler) === 2) {
                    [$class, $method] = $handler;
                } else {
                    throw new \RuntimeException('Invalid route handler for ' . $route['uri']);
                }

                if (!class_exists($class)) {
                    throw new \RuntimeException('Controller not found: ' . $class);
                }
                $controller = new $class();
                if (!method_exists($controller, $method)) {
                    throw new \RuntimeException('Method not found: ' . $class . '::' . $method);
                }
                $result = $controller->{$method}($request);
            }

            if (is_string($result)) {
                Response::html($result);
            } elseif (is_array($result)) {
                Response::json($result);
            }
        };

        $next = $core;
        foreach (array_reverse($pipeline) as $name) {
            $middlewareClass = $this->resolveMiddleware($name);
            $next = function () use ($middlewareClass, $request, $next, $name) {
                $parts = explode(':', $name, 2);
                $args = isset($parts[1]) ? explode(',', $parts[1]) : [];
                $instance = new $middlewareClass();
                $instance->handle($request, $next, ...$args);
            };
        }

        $next();
    }

    private function resolveMiddleware(string $name): string
    {
        $alias = explode(':', $name, 2)[0];
        $map = [
            'auth' => \App\Middleware\AuthMiddleware::class,
            'guest' => \App\Middleware\GuestMiddleware::class,
            'admin' => \App\Middleware\AdminMiddleware::class,
            'tenant' => \App\Middleware\TenantMiddleware::class,
            'permission' => \App\Middleware\PermissionMiddleware::class,
            'apikey' => \App\Middleware\ApiKeyMiddleware::class,
            'throttle' => \App\Middleware\ThrottleMiddleware::class,
            'csrf' => \App\Middleware\CsrfMiddleware::class,
            'subscription' => \App\Middleware\SubscriptionMiddleware::class,
        ];
        if (isset($map[$alias])) {
            return $map[$alias];
        }
        if (class_exists($alias)) {
            return $alias;
        }
        throw new \RuntimeException('Unknown middleware: ' . $name);
    }
}

/**
 * Fluent route definition returned by registration methods:
 *   Router::get('/x/{id}', ...)->name('x.show')->where('id', '\d+');
 */
final class RouteDefinition
{
    public array $route;
    private Router $router;

    public function __construct(Router $router, array $route)
    {
        $this->router = $router;
        $this->route = $route;
    }

    public function name(string $name): self
    {
        $full = $this->route['name_prefix'] . $name;
        $this->route['name'] = $full;
        $this->router->registerName($full, $this->route);
        return $this;
    }

    public function where(string $param, string $pattern): self
    {
        $this->route['where'][$param] = $pattern;
        $this->route['regex'] = null;
        return $this;
    }

    public function middleware(string ...$names): self
    {
        $this->route['middleware'] = array_merge($this->route['middleware'], $names);
        return $this;
    }
}
