<?php

class Router
{
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): void
    {
        $path = $request->path();

        foreach ($this->routes as $route) {
            $params = $this->match($route['pattern'], $path);
            if ($params !== null) {
                call_user_func($route['handler'], $request, $params);
                return;
            }
        }

        // No route matched
        abort();
    }

    private function match(string $pattern, string $path): ?array
    {
        // Convert pattern to regex
        // {country} becomes a named capture group
        $regex = preg_replace('/\{(\w+)}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches)) {
            // Filter out numeric keys
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }

        return null;
    }
}
