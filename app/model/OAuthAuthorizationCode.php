<?php
namespace app\model;

use Illuminate\Database\Eloquent\Model;

class OAuthAuthorizationCode extends Model
{
    protected $table = 'oauth_authorization_codes';
    
    protected $fillable = [
        'authorization_code',
        'client_id',
        'user_id',
        'redirect_uri',
        'scope',
        'expires_at',
    ];

    protected $casts = [
        'scope' => 'array',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 授权码所属用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 授权码所属客户端
     */
    public function client()
    {
        return $this->belongsTo(OAuthClient::class, 'client_id', 'client_id');
    }

    /**
     * 检查授权码是否过期
     */
    public function isExpired(): bool
    {
        return strtotime($this->expires_at) < time();
    }
}
