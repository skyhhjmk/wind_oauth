<?php
namespace app\model;

use Illuminate\Database\Eloquent\Model;

class OAuthScope extends Model
{
    protected $table = 'oauth_scopes';
    
    protected $fillable = [
        'scope',
        'description',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
