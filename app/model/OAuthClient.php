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
        return hash_equals($this->client_secret, $secret);
    }

    /**
     * 验证重定向URI
     */
    public function validateRedirectUri(string $uri): bool
    {
        return $uri === $this->redirect_uri;
    }
}
