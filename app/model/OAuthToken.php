<?php
namespace app\model;

use Illuminate\Database\Eloquent\Model;

class OAuthToken extends Model
{
    protected $table = 'oauth_tokens';
    
    protected $fillable = [
        'access_token',
        'refresh_token',
        'client_id',
        'user_id',
        'scope',
        'expires_at',
        'refresh_expires_at',
    ];

    protected $casts = [
        'scope' => 'array',
        'expires_at' => 'datetime',
        'refresh_expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 令牌所属用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 令牌所属客户端
     */
    public function client()
    {
        return $this->belongsTo(OAuthClient::class, 'client_id', 'client_id');
    }

    /**
     * 检查访问令牌是否过期
     */
    public function isExpired(): bool
    {
        return strtotime($this->expires_at) < time();
    }

    /**
     * 检查刷新令牌是否过期
     */
    public function isRefreshExpired(): bool
    {
        return strtotime($this->refresh_expires_at) < time();
    }
}
