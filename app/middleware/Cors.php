<?php
namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

class Cors implements MiddlewareInterface
{
    private function resolveAllowedOrigin(Request $request): ?string
    {
        $origin = $request->header('origin');
        if (!$origin) {
            return null;
        }
        $allowed = array_filter(array_map('trim', explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: '')));
        if (!$allowed) {
            return null;
        }
        if (in_array('*', $allowed, true)) {
            return '*';
        }
        return in_array($origin, $allowed, true) ? $origin : null;
    }

    public function process(Request $request, callable $next): Response
    {
        // Preflight
        if ($request->method() === 'OPTIONS') {
            $allowedOrigin = $this->resolveAllowedOrigin($request);
            $headers = [
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Max-Age' => '86400',
            ];
            $reqHeaders = $request->header('Access-Control-Request-Headers');
            if ($reqHeaders) {
                $headers['Access-Control-Allow-Headers'] = $reqHeaders;
            } else {
                $headers['Access-Control-Allow-Headers'] = 'Content-Type, Authorization, X-Requested-With';
            }
            if ($allowedOrigin) {
                $headers['Access-Control-Allow-Origin'] = $allowedOrigin;
                $headers['Vary'] = 'Origin';
            }
            return response('', 200, $headers);
        }

        /** @var Response $response */
        $response = $next($request);

        $allowedOrigin = $this->resolveAllowedOrigin($request);
        if ($allowedOrigin) {
            $response->withHeaders([
                'Access-Control-Allow-Origin' => $allowedOrigin,
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
                'Vary' => 'Origin',
            ]);
        }

        return $response;
    }
}
