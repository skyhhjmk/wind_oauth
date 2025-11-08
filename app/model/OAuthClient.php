<?php
namespace app\model;

use Illuminate\Database\Eloquent\Model;

class OAuthClient extends Model
{
    protected $table = 'oauth_clients';
    
    protected $fillable = [
        'user_id',
        'name',
        'client_id',
        'client_secret',
        'redirect_uri',
        'redirect_dynamic_enabled',
        'redirect_whitelist',
        'grant_types',
        'scope',
        'status',
    ];

    protected $hidden = [
        'client_secret',
    ];

    protected $casts = [
        'grant_types' => 'array',
        'scope' => 'array',
        'redirect_whitelist' => 'array',
        'redirect_dynamic_enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 客户端所属用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 客户端的授权码
     */
    public function authorizationCodes()
    {
        return $this->hasMany(OAuthAuthorizationCode::class, 'client_id', 'client_id');
    }

    /**
     * 客户端的访问令牌
     */
    public function tokens()
    {
        return $this->hasMany(OAuthToken::class, 'client_id', 'client_id');
    }

    /**
     * 验证客户端密钥
     */
    public function validateSecret(string $secret): bool
    {
        $stored = $this->client_secret;
        // If stored secret is hashed (bcrypt/argon), verify accordingly
        if (is_string($stored) && preg_match('/^\$[A-Za-z0-9]+\$/', $stored)) {
            return password_verify($secret, $stored);
        }
        // Fallback: plaintext compare (legacy), then upgrade to hash
        $ok = hash_equals((string)$stored, (string)$secret);
        if ($ok) {
            $this->client_secret = password_hash($secret, PASSWORD_DEFAULT);
            // Best-effort upgrade; ignore errors
            try { $this->save(); } catch (\Throwable $e) {}
        }
        return $ok;
    }

    /**
     * 解析URL，返回 host:port 形式（若端口缺失按协议补全80/443）。
     */
    protected function normalizeHostPortFromUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null; // 仅允许 http/https
        }
        $host = $parts['host'] ?? '';
        if ($host === '') {
            return null;
        }
        // 去除可能的IPv6方括号
        if ($host[0] === '[' && substr($host, -1) === ']') {
            $host = trim($host, '[]');
        }
        $port = $parts['port'] ?? null;
        if ($port === null) {
            $port = ($scheme === 'https') ? 443 : 80;
        }
        return strtolower($host) . ':' . (int)$port;
    }

    /**
     * 从白名单条目解析 host:port。支持：
     * - 形如 example.com:8080 或 [::1]:8080
     * - 完整URL如 https://example.com:8443/anything
     */
    protected function normalizeHostPortFromEntry(string $entry): ?string
    {
        $entry = trim($entry);
        if ($entry === '') {
            return null;
        }
        if (str_contains($entry, '://')) {
            return $this->normalizeHostPortFromUrl($entry);
        }
        // 处理 [ipv6]:port 或 host:port
        if ($entry[0] === '[') {
            $pos = strpos($entry, ']');
            if ($pos === false) {
                return null;
            }
            $host = substr($entry, 1, $pos - 1);
            $rest = substr($entry, $pos + 1);
            if (!str_starts_with($rest, ':')) {
                return null; // 必须带端口
            }
            $port = (int)substr($rest, 1);
            if ($port <= 0) {
                return null;
            }
            return strtolower($host) . ':' . $port;
        }
        // 普通 host:port
        $pos = strrpos($entry, ':');
        if ($pos === false) {
            return null; // 必须带端口（端口区分）
        }
        $host = substr($entry, 0, $pos);
        $port = (int)substr($entry, $pos + 1);
        if ($host === '' || $port <= 0) {
            return null;
        }
        return strtolower($host) . ':' . $port;
    }

    /**
     * 获取标准化白名单 host:port 列表。
     */
    public function getNormalizedWhitelist(): array
    {
        $list = $this->redirect_whitelist ?? [];
        if (!is_array($list)) {
            // 兼容字符串存储（如手工写入）
            $list = [$list];
        }
        $result = [];
        foreach ($list as $item) {
            $hp = $this->normalizeHostPortFromEntry((string)$item);
            if ($hp !== null) {
                $result[$hp] = true;
            }
        }
        return array_keys($result);
    }

    /**
     * 验证重定向URI：
     * - 精确匹配已注册 redirect_uri；或
     * - 若启用动态回调，则校验 host:port 在白名单中（路径任意）。
     */
    public function validateRedirectUri(string $uri): bool
    {
        if ($uri === $this->redirect_uri) {
            return true;
        }
        if (!$this->redirect_dynamic_enabled) {
            return false;
        }
        $hp = $this->normalizeHostPortFromUrl($uri);
        if ($hp === null) {
            return false;
        }
        $whitelist = $this->getNormalizedWhitelist();
        return in_array($hp, $whitelist, true);
    }
}
