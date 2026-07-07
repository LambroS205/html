<?php
namespace App\Core;

class Router
{
    private static $routes = [];

    public static function get($uri, $action)
    {
        self::$routes['GET'][$uri] = $action;
    }

    public static function post($uri, $action)
    {
        self::$routes['POST'][$uri] = $action;
    }

    public static function dispatch($uri, $method)
    {
        // Loại bỏ query string khỏi URI (VD: /product?id=1 => /product)
        $uri = explode('?', $uri)[0];
        
        // Loại bỏ slash cuối cùng (trừ khi URI là /)
        if ($uri !== '/' && substr($uri, -1) === '/') {
            $uri = rtrim($uri, '/');
        }

        // Tìm route (Bao gồm cả Regex params như {slug})
        foreach (self::$routes[$method] ?? [] as $route => $action) {
            // Chuyển đổi {param} thành Regex
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[a-zA-Z0-9_.-]+)', $route);
            $pattern = "@^" . $pattern . "$@D";

            if (preg_match($pattern, $uri, $matches)) {
                // Đưa các params vào $_GET để tương thích ngược với code cũ
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $_GET[$key] = $value;
                    }
                }

                // Tách 'Controller@method'
                $parts = explode('@', $action);
                $controllerName = "App\\Controllers\\" . $parts[0];
                $methodName = $parts[1] ?? 'index';

                if (class_exists($controllerName)) {
                    $controller = new $controllerName();
                    if (method_exists($controller, $methodName)) {
                        return call_user_func([$controller, $methodName]);
                    }
                }
            }
        }

        // Nếu không tìm thấy route hoặc lỗi
        http_response_code(404);
        echo "404 Not Found - Debug: URI=$uri, Method=$method";
        if (isset($action)) {
            echo " | Route matched: $action | Class exists: " . (class_exists($controllerName) ? 'Yes' : 'No');
            if (class_exists($controllerName)) {
                echo " | Method exists: " . (method_exists($controllerName, $methodName) ? 'Yes' : 'No');
            }
        }
    }
}
