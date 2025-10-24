<?php
namespace app\model;

use Illuminate\Database\Eloquent\Model;

class OAuthProvider extends Model
{
    protected $table = 'oauth_providers';
    
    protected $fillable = [
        'name',
        'slug',
        'client_id',
        'client_secret',
        'authorize_url',
        'token_url',
        'userinfo_url',
        'scope',
        'icon_class',
        'button_color',
        'status',
        'sort_order',
    ];

    protected $hidden = [
        'client_secret',
    ];

    protected $casts = [
        'status' => 'integer',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 获取用户绑定
     */
    public function userBindings()
    {
        return $this->hasMany(OAuthUserBinding::class, 'provider_id');
    }

    /**
     * 获取启用的提供商
     */
    public static function getActive()
    {
        return self::where('status', 1)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * 根据slug查找提供商
     */
    public static function findBySlug(string $slug)
    {
        return self::where('slug', $slug)->where('status', 1)->first();
    }

    /**
     * 构建授权URL
     */
    public function buildAuthorizeUrl(string $redirectUri, string $state): string
    {
        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'state' => $state,
        ];

        if ($this->scope) {
            $params['scope'] = $this->scope;
        }

        return $this->authorize_url . '?' . http_build_query($params);
    }
}
