<?php
namespace app\controller;

use app\model\OAuthProvider;
use app\model\OAuthUserBinding;
use app\model\User;
use support\Request;
use support\Response;

class ThirdPartyOAuthController
{
    /**
     * 发起第三方OAuth登录
     */
    public function login(Request $request, string $provider): Response
    {
        $oauthProvider = OAuthProvider::findBySlug($provider);
        
        if (!$oauthProvider) {
            return json(['error' => '不支持的OAuth提供商'], 404);
        }

        // 生成state用于防止CSRF攻击
        $state = bin2hex(random_bytes(16));
        session('oauth_state', $state);
        session('oauth_provider_id', $oauthProvider->id);

        // 构建回调URL
        $redirectUri = $request->url() . '://' . $request->host() . '/oauth/callback/' . $provider;
        
        // 构建授权URL并重定向
        $authorizeUrl = $oauthProvider->buildAuthorizeUrl($redirectUri, $state);
        
        return redirect($authorizeUrl);
    }

    /**
     * 处理OAuth回调
     */
    public function callback(Request $request, string $provider): Response
    {
        $code = $request->get('code');
        $state = $request->get('state');
        $error = $request->get('error');

        // 检查是否有错误
        if ($error) {
            return view('oauth/error', ['error' => 'OAuth授权失败：' . $error]);
        }

        // 验证state
        if (!$state || $state !== session('oauth_state')) {
            return view('oauth/error', ['error' => '无效的OAuth状态']);
        }

        // 获取提供商信息
        $providerId = session('oauth_provider_id');
        $oauthProvider = OAuthProvider::find($providerId);

        if (!$oauthProvider || $oauthProvider->slug !== $provider) {
            return view('oauth/error', ['error' => 'OAuth提供商不匹配']);
        }

        // 清除session
        session()->forget(['oauth_state', 'oauth_provider_id']);

        try {
            // 交换授权码获取访问令牌
            $redirectUri = $request->url() . '://' . $request->host() . '/oauth/callback/' . $provider;
            $tokenData = $this->exchangeCodeForToken($oauthProvider, $code, $redirectUri);

            if (!$tokenData) {
                return view('oauth/error', ['error' => '获取访问令牌失败']);
            }

            // 获取用户信息
            $userInfo = $this->getUserInfo($oauthProvider, $tokenData['access_token']);

            if (!$userInfo) {
                return view('oauth/error', ['error' => '获取用户信息失败']);
            }

            // 查找或创建用户绑定
            $binding = OAuthUserBinding::findByProviderUser($oauthProvider->id, $userInfo['id']);

            if ($binding) {
                // 更新现有绑定
                $binding->update([
                    'provider_username' => $userInfo['username'] ?? null,
                    'provider_email' => $userInfo['email'] ?? null,
                    'provider_avatar' => $userInfo['avatar'] ?? null,
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'] ?? null,
                    'token_expires_at' => isset($tokenData['expires_in']) 
                        ? date('Y-m-d H:i:s', time() + $tokenData['expires_in']) 
                        : null,
                ]);

                // 登录用户
                $user = $binding->user;
            } else {
                // 创建新用户
                $user = $this->createOrFindUser($userInfo);

                // 创建绑定
                OAuthUserBinding::create([
                    'user_id' => $user->id,
                    'provider_id' => $oauthProvider->id,
                    'provider_user_id' => $userInfo['id'],
                    'provider_username' => $userInfo['username'] ?? null,
                    'provider_email' => $userInfo['email'] ?? null,
                    'provider_avatar' => $userInfo['avatar'] ?? null,
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'] ?? null,
                    'token_expires_at' => isset($tokenData['expires_in']) 
                        ? date('Y-m-d H:i:s', time() + $tokenData['expires_in']) 
                        : null,
                ]);
            }

            // 设置登录session
            session('user_id', $user->id);
            session('username', $user->username);

            // 检查是否有待处理的OAuth授权请求
            $oauthRequest = $request->cookie('oauth_request');
            if ($oauthRequest) {
                $oauthData = json_decode($oauthRequest, true);
                if ($oauthData) {
                    // 重定向到OAuth授权页面
                    $response = redirect('/oauth/authorize?' . http_build_query($oauthData));
                    $response->cookie('oauth_request', '', time() - 3600, '/');
                    return $response;
                }
            }

            // 默认重定向到首页或用户中心
            return redirect('/');

        } catch (\Exception $e) {
            file_put_contents(runtime_path() . '/oauth_error.log', date('Y-m-d H:i:s') . ' ' . $e->getMessage() . "\n", FILE_APPEND);
            return view('oauth/error', ['error' => 'OAuth登录失败，请重试']);
        }
    }

    /**
     * 交换授权码获取访问令牌
     */
    private function exchangeCodeForToken(OAuthProvider $provider, string $code, string $redirectUri): ?array
    {
        $params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $provider->client_id,
            'client_secret' => $provider->client_secret,
            'redirect_uri' => $redirectUri,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $provider->token_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'User-Agent: WindOAuth/1.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        $data = json_decode($response, true);
        
        // 处理GitHub特殊情况（可能返回form-urlencoded格式）
        if (!is_array($data)) {
            parse_str($response, $data);
        }

        if (!isset($data['access_token'])) {
            return null;
        }

        return $data;
    }

    /**
     * 获取用户信息
     */
    private function getUserInfo(OAuthProvider $provider, string $accessToken): ?array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $provider->userinfo_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
            'User-Agent: WindOAuth/1.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            return null;
        }

        // 标准化用户信息（不同提供商字段可能不同）
        return $this->normalizeUserInfo($provider, $data);
    }

    /**
     * 标准化用户信息
     */
    private function normalizeUserInfo(OAuthProvider $provider, array $data): array
    {
        // 根据不同提供商标准化字段
        $normalized = [
            'id' => null,
            'username' => null,
            'email' => null,
            'avatar' => null,
        ];

        // GitHub
        if ($provider->slug === 'github') {
            $normalized['id'] = (string)($data['id'] ?? '');
            $normalized['username'] = $data['login'] ?? '';
            $normalized['email'] = $data['email'] ?? '';
            $normalized['avatar'] = $data['avatar_url'] ?? '';
        }
        // Google
        elseif ($provider->slug === 'google') {
            $normalized['id'] = $data['id'] ?? $data['sub'] ?? '';
            $normalized['username'] = $data['name'] ?? $data['email'] ?? '';
            $normalized['email'] = $data['email'] ?? '';
            $normalized['avatar'] = $data['picture'] ?? '';
        }
        // 通用处理
        else {
            $normalized['id'] = (string)($data['id'] ?? $data['sub'] ?? $data['user_id'] ?? '');
            $normalized['username'] = $data['username'] ?? $data['login'] ?? $data['name'] ?? $data['nickname'] ?? '';
            $normalized['email'] = $data['email'] ?? '';
            $normalized['avatar'] = $data['avatar'] ?? $data['avatar_url'] ?? $data['picture'] ?? '';
        }

        return $normalized;
    }

    /**
     * 创建或查找用户
     */
    private function createOrFindUser(array $userInfo): User
    {
        // 如果有邮箱，尝试查找现有用户
        if (!empty($userInfo['email'])) {
            $user = User::where('email', $userInfo['email'])->first();
            if ($user) {
                return $user;
            }
        }

        // 生成唯一用户名
        $username = $userInfo['username'] ?: 'user_' . bin2hex(random_bytes(4));
        $originalUsername = $username;
        $counter = 1;
        
        while (User::where('username', $username)->exists()) {
            $username = $originalUsername . '_' . $counter;
            $counter++;
        }

        // 创建新用户（不设置密码，只能通过OAuth登录）
        $user = User::create([
            'username' => $username,
            'email' => $userInfo['email'] ?: $username . '@oauth.local',
            'password' => password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT),
            'name' => $userInfo['username'] ?: $username,
            'is_admin' => 0,
            'status' => 1,
        ]);

        return $user;
    }
}
