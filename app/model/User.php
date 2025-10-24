<?php
namespace app\model;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';
    
    protected $fillable = [
        'username',
        'email',
        'password',
        'name',
        'is_admin',
        'status',
    ];

    protected $hidden = [
        // 注意: password不应该在hidden中，否则无法验证
        // 'password',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 获取属性（包括hidden的）
     */
    public function getAuthPassword()
    {
        return $this->attributes['password'];
    }

    /**
     * 用户拥有的OAuth客户端
     */
    public function oauthClients()
    {
        return $this->hasMany(OAuthClient::class, 'user_id');
    }

    /**
     * 用户的访问令牌
     */
    public function tokens()
    {
        return $this->hasMany(OAuthToken::class, 'user_id');
    }
}
