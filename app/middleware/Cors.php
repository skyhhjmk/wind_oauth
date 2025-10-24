<?php
namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;

class Cors implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        // Handle preflight OPTIONS request
        if ($request->method() === 'OPTIONS') {
            return response('', 200, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
                'Access-Control-Max-Age' => '86400',
            ]);
        }

        /** @var Response $response */
        $response = $next($request);
        
        // Add CORS headers to actual response
        $response->withHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
        ]);

        return $response;
    }
}
