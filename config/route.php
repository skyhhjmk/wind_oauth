<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use Webman\Route;
use app\controller\AuthController;
use app\controller\OauthController;
use app\controller\AdminController;
use app\controller\InstallController;
use app\controller\ThirdPartyOAuthController;

// 安装路由
Route::get('/install', [InstallController::class, 'index']);
Route::post('/install', [InstallController::class, 'install']);

// 认证路由
Route::get('/login', [AuthController::class, 'login']);
Route::post('/login', [AuthController::class, 'loginSubmit']);
Route::get('/register', [AuthController::class, 'register']);
Route::post('/register', [AuthController::class, 'registerSubmit']);
Route::get('/logout', [AuthController::class, 'logout']);

// 个人中心
Route::get('/profile', [AuthController::class, 'profile']);
Route::post('/profile/update', [AuthController::class, 'profileUpdate']);
Route::post('/profile/password', [AuthController::class, 'profilePassword']);

// OAuth 2.0 端点
Route::get('/oauth/authorize', [OauthController::class, 'authorize']);
Route::post('/oauth/authorize', [OauthController::class, 'authorizeSubmit']);
Route::post('/oauth/token', [OauthController::class, 'token']);
Route::post('/oauth/introspect', [OauthController::class, 'introspect']);
Route::post('/oauth/revoke', [OauthController::class, 'revoke']);
Route::get('/oauth/userinfo', [OauthController::class, 'userinfo']);

// 第三方OAuth登录
Route::get('/oauth/login/{provider}', [ThirdPartyOAuthController::class, 'login']);
Route::get('/oauth/callback/{provider}', [ThirdPartyOAuthController::class, 'callback']);

// 管理后台
Route::get('/admin', [AdminController::class, 'index']);

// 客户端管理
Route::get('/admin/clients', [AdminController::class, 'clients']);
Route::get('/admin/clients/create', [AdminController::class, 'createClient']);
Route::post('/admin/clients', [AdminController::class, 'storeClient']);
Route::get('/admin/clients/edit', [AdminController::class, 'editClient']);
Route::post('/admin/clients/update', [AdminController::class, 'updateClient']);
Route::post('/admin/clients/delete', [AdminController::class, 'deleteClient']);

// 令牌管理
Route::get('/admin/tokens', [AdminController::class, 'tokens']);
Route::post('/admin/tokens/revoke', [AdminController::class, 'revokeTokenAdmin']);

// 权限范围管理
Route::get('/admin/scopes', [AdminController::class, 'scopes']);
Route::post('/admin/scopes', [AdminController::class, 'storeScope']);
Route::post('/admin/scopes/update', [AdminController::class, 'updateScope']);
Route::post('/admin/scopes/delete', [AdminController::class, 'deleteScope']);

// 用户管理
Route::get('/admin/users', [AdminController::class, 'users']);
Route::get('/admin/users/{id}', [AdminController::class, 'getUser']);
Route::post('/admin/users', [AdminController::class, 'storeUser']);
Route::post('/admin/users/update', [AdminController::class, 'updateUser']);
Route::post('/admin/users/delete', [AdminController::class, 'deleteUser']);

// OAuth提供商管理
Route::get('/admin/providers', [AdminController::class, 'providers']);
Route::get('/admin/providers/create', [AdminController::class, 'createProvider']);
Route::post('/admin/providers', [AdminController::class, 'storeProvider']);
Route::get('/admin/providers/edit', [AdminController::class, 'editProvider']);
Route::post('/admin/providers/update', [AdminController::class, 'updateProvider']);
Route::post('/admin/providers/delete', [AdminController::class, 'deleteProvider']);
