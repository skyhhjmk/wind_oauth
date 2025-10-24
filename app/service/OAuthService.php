<?php
namespace app\service;

use app\model\OAuthClient;
use app\model\OAuthAuthorizationCode;
use app\model\OAuthToken;
use app\model\User;

class OAuthService
{
    /**
     * 生成授权码
     */
    public function generateAuthorizationCode(
        string $clientId,
        int $userId,
        string $redirectUri,
        array $scope = []
    ): string {
        $code = bin2hex(random_bytes(32));
        
        OAuthAuthorizationCode::create([
            'authorization_code' => $code,
            'client_id' => $clientId,
            'user_id' => $userId,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+10 minutes')), // 10分钟有效期
        ]);

        return $code;
    }

    /**
     * 验证授权码并生成访问令牌
     */
    public function exchangeCodeForToken(
        string $code,
        string $clientId,
        string $clientSecret,
        string $redirectUri
    ): ?array {
        // 验证客户端
        $client = OAuthClient::where('client_id', $clientId)
            ->where('status', 1)
            ->first();

        if (!$client || !$client->validateSecret($clientSecret)) {
            return null;
        }

        // 查找授权码
        $authCode = OAuthAuthorizationCode::where('authorization_code', $code)
            ->where('client_id', $clientId)
            ->first();

        if (!$authCode) {
            return null;
        }

        // 检查授权码是否过期
        if ($authCode->isExpired()) {
            $authCode->delete();
            return null;
        }

        // 验证重定向URI
        if ($authCode->redirect_uri !== $redirectUri) {
            return null;
        }

        // 生成访问令牌
        $accessToken = bin2hex(random_bytes(32));
        $refreshToken = bin2hex(random_bytes(32));

        $token = OAuthToken::create([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'user_id' => $authCode->user_id,
            'scope' => $authCode->scope,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+2 hours')), // 2小时有效期
            'refresh_expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')), // 30天有效期
        ]);

        // 删除已使用的授权码
        $authCode->delete();

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 7200, // 2小时
            'scope' => implode(' ', $authCode->scope ?? []),
        ];
    }

    /**
     * 刷新访问令牌
     */
    public function refreshToken(
        string $refreshToken,
        string $clientId,
        string $clientSecret
    ): ?array {
        // 验证客户端
        $client = OAuthClient::where('client_id', $clientId)
            ->where('status', 1)
            ->first();

        if (!$client || !$client->validateSecret($clientSecret)) {
            return null;
        }

        // 查找令牌
        $token = OAuthToken::where('refresh_token', $refreshToken)
            ->where('client_id', $clientId)
            ->first();

        if (!$token || $token->isRefreshExpired()) {
            return null;
        }

        // 生成新的访问令牌
        $newAccessToken = bin2hex(random_bytes(32));
        $newRefreshToken = bin2hex(random_bytes(32));

        $token->update([
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+2 hours')),
            'refresh_expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        return [
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 7200,
            'scope' => implode(' ', $token->scope ?? []),
        ];
    }

    /**
     * 验证访问令牌
     */
    public function validateAccessToken(string $accessToken): ?array
    {
        $token = OAuthToken::where('access_token', $accessToken)->first();

        if (!$token || $token->isExpired()) {
            return null;
        }

        $user = $token->user;
        if (!$user || $user->status != 1) {
            return null;
        }

        return [
            'user_id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'name' => $user->name,
            'scope' => $token->scope,
        ];
    }

    /**
     * 撤销令牌
     */
    public function revokeToken(string $token): bool
    {
        $tokenModel = OAuthToken::where('access_token', $token)
            ->orWhere('refresh_token', $token)
            ->first();

        if ($tokenModel) {
            $tokenModel->delete();
            return true;
        }

        return false;
    }

    /**
     * 生成客户端ID和密钥
     */
    public function generateClientCredentials(): array
    {
        return [
            'client_id' => 'client_' . bin2hex(random_bytes(16)),
            'client_secret' => bin2hex(random_bytes(32)),
        ];
    }
}
