<?php
namespace app\model;

use Illuminate\Database\Eloquent\Model;

class OAuthUserBinding extends Model
{
    protected $table = 'oauth_user_bindings';
    
    protected $fillable = [
        'user_id',
        'provider_id',
        'provider_user_id',
        'provider_username',
        'provider_email',
        'provider_avatar',
        'access_token',
        'refresh_token',
        'token_expires_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'provider_id' => 'integer',
        'token_expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 关联本地用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联OAuth提供商
     */
    public function provider()
    {
        return $this->belongsTo(OAuthProvider::class);
    }

    /**
     * 检查令牌是否过期
     */
    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return false;
        }
        return $this->token_expires_at->isPast();
    }

    /**
     * 根据提供商和第三方用户ID查找绑定
     */
    public static function findByProviderUser(int $providerId, string $providerUserId)
    {
        return self::where('provider_id', $providerId)
            ->where('provider_user_id', $providerUserId)
            ->first();
    }
}
