<?php
namespace app\controller;

use app\model\OAuthClient;
use app\model\OAuthScope;
use app\model\User;
use app\model\OAuthToken;
use app\service\OAuthService;
use support\Request;
use support\Response;

class OauthController
{
    protected OAuthService $oauthService;

    public function __construct()
    {
        $this->oauthService = new OAuthService();
    }

    private function authenticateClient(Request $request): ?OAuthClient
    {
        $auth = $request->header('authorization');
        $clientId = null; $clientSecret = null;
        if ($auth && str_starts_with($auth, 'Basic ')) {
            $decoded = base64_decode(substr($auth, 6));
            if ($decoded !== false) {
                $parts = explode(':', $decoded, 2);
                $clientId = $parts[0] ?? null;
                $clientSecret = $parts[1] ?? null;
            }
        } else {
            $clientId = $request->post('client_id');
            $clientSecret = $request->post('client_secret');
        }
        if (!$clientId || !$clientSecret) {
            return null;
        }
        $client = OAuthClient::where('client_id', $clientId)->where('status', 1)->first();
        if (!$client || !$client->validateSecret($clientSecret)) {
            return null;
        }
        return $client;
    }

    /**
     * 授权页面
     */
    public function authorize(Request $request): Response
    {
        $clientId = $request->get('client_id');
        $redirectUri = $request->get('redirect_uri');
        $responseType = $request->get('response_type', 'code');
        $scope = $request->get('scope', '');
        $state = $request->get('state', '');

        // 验证参数
        if (!$clientId || !$redirectUri || $responseType !== 'code') {
            return view('oauth/error', ['error' => '无效的请求参数']);
        }

        // 验证客户端
        $client = OAuthClient::where('client_id', $clientId)
            ->where('status', 1)
            ->first();

        if (!$client || !$client->validateRedirectUri($redirectUri)) {
            return view('oauth/error', ['error' => '无效的客户端']);
        }

        // 检查用户是否已登录
        $userId = session('user_id');
        file_put_contents(runtime_path() . '/debug.log', date('Y-m-d H:i:s') . ' [DEBUG] authorize() - user_id: ' . ($userId ?: 'null') . "\n", FILE_APPEND);
        
        if (!$userId) {
            // 跳转到登录页面，使用cookie保存OAuth请求参数
            $oauthData = json_encode($request->get());
            file_put_contents(runtime_path() . '/debug.log', date('Y-m-d H:i:s') . ' [DEBUG] authorize() - Setting cookie with data: ' . $oauthData . "\n", FILE_APPEND);
            
            $response = redirect('/login');
            // 设置cookie：600秒有效期，路径为/
            $response->cookie('oauth_request', $oauthData, time() + 600, '/', '', false, true);
            return $response;
        }

        $user = User::find($userId);
        if (!$user || $user->status != 1) {
            return view('oauth/error', ['error' => '用户状态异常']);
        }

        // 解析scope
        $requestedScopes = array_filter(explode(' ', $scope));
        $availableScopes = OAuthScope::whereIn('scope', $requestedScopes)->get();

        return view('oauth/authorize', [
            'client' => $client,
            'user' => $user,
            'scopes' => $availableScopes,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);
    }

    /**
     * 处理授权
     */
    public function authorizeSubmit(Request $request): Response
    {
        $userId = session('user_id');
        if (!$userId) {
            return json(['error' => 'unauthorized'], 401);
        }

        $clientId = $request->post('client_id');
        $redirectUri = $request->post('redirect_uri');
        $scope = $request->post('scope', []);
        $state = $request->post('state', '');
        $approved = $request->post('approved');

        // 用户拒绝授权
        if ($approved !== 'yes') {
            $errorUrl = $redirectUri . '?error=access_denied';
            if ($state) {
                $errorUrl .= '&state=' . urlencode($state);
            }
            return redirect($errorUrl);
        }

        // 生成授权码
        $code = $this->oauthService->generateAuthorizationCode(
            $clientId,
            $userId,
            $redirectUri,
            $scope
        );

        // 重定向回客户端
        $callbackUrl = $redirectUri . '?code=' . $code;
        if ($state) {
            $callbackUrl .= '&state=' . urlencode($state);
        }

        return redirect($callbackUrl);
    }

    /**
     * 获取访问令牌
     */
    public function token(Request $request): Response
    {
        $grantType = $request->post('grant_type');
        $clientId = $request->post('client_id');
        $clientSecret = $request->post('client_secret');

        if ($grantType === 'authorization_code') {
            // 授权码换取令牌
            $code = $request->post('code');
            $redirectUri = $request->post('redirect_uri');

            $token = $this->oauthService->exchangeCodeForToken(
                $code,
                $clientId,
                $clientSecret,
                $redirectUri
            );

            if (!$token) {
                return json(['error' => 'invalid_grant'], 400);
            }

            return json($token);

        } elseif ($grantType === 'refresh_token') {
            // 刷新令牌
            $refreshToken = $request->post('refresh_token');

            $token = $this->oauthService->refreshToken(
                $refreshToken,
                $clientId,
                $clientSecret
            );

            if (!$token) {
                return json(['error' => 'invalid_grant'], 400);
            }

            return json($token);
        }

        return json(['error' => 'unsupported_grant_type'], 400);
    }

    /**
     * 验证令牌（资源服务器使用）
     */
    public function introspect(Request $request): Response
    {
        $client = $this->authenticateClient($request);
        if (!$client) {
            return json(['error' => 'invalid_client'], 401);
        }
        $tokenValue = $request->post('token');
        if (!$tokenValue) {
            return json(['active' => false]);
        }
        $token = OAuthToken::where('access_token', $tokenValue)->first();
        if (!$token || $token->client_id !== $client->client_id || $token->isExpired()) {
            return json(['active' => false]);
        }
        $scopes = implode(' ', $token->scope ?? []);
        return json([
            'active' => true,
            'scope' => $scopes,
            'client_id' => $token->client_id,
            'exp' => strtotime($token->expires_at),
            'sub' => (string)$token->user_id,
        ]);
    }

    /**
     * 撤销令牌
     */
    public function revoke(Request $request): Response
    {
        $client = $this->authenticateClient($request);
        if (!$client) {
            return json(['error' => 'invalid_client'], 401);
        }
        $tokenValue = $request->post('token');
        if (!$tokenValue) {
            return json(['error' => 'invalid_request'], 400);
        }
        $token = OAuthToken::where('access_token', $tokenValue)
            ->orWhere('refresh_token', $tokenValue)
            ->first();
        if ($token && $token->client_id === $client->client_id) {
            $token->delete();
        }
        return json(['success' => true]);
    }

    /**
     * 用户信息接口（需要访问令牌）
     */
    public function userinfo(Request $request): Response
    {
        $authorization = $request->header('authorization');
        
        if (!$authorization || !str_starts_with($authorization, 'Bearer ')) {
            return json(['error' => 'unauthorized'], 401);
        }

        $token = substr($authorization, 7);
        $userInfo = $this->oauthService->validateAccessToken($token);

        if (!$userInfo) {
            return json(['error' => 'invalid_token'], 401);
        }

        return json($userInfo);
    }
}
