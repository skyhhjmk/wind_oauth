# Wind OAuth 系统

基于 Webman 框架构建的完整 OAuth 2.0 授权服务器系统，支持多种数据库（MySQL, SQLite, PostgreSQL），使用 Twig 模板引擎和
TailwindCSS 构建前端界面。

## 功能特性

- ✅ 完整的 OAuth 2.0 授权码流程
- ✅ 访问令牌和刷新令牌管理
- ✅ 后台管理系统
    - 客户端管理
    - 用户管理
    - 令牌管理
    - 权限范围管理
- ✅ 多数据库支持 (MySQL / SQLite / PostgreSQL)
- ✅ 美观的 TailwindCSS 界面
- ✅ Twig 模板引擎

## 安装步骤

### 1. 安装依赖

```bash
composer install
```

### 2. 配置数据库

创建 `.env` 文件或修改 `config/database.php`:

```env
DB_CONNECTION=mysql          # 可选: mysql, sqlite, pgsql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wind_oauth
DB_USERNAME=root
DB_PASSWORD=
```

### 3. 初始化数据库

根据您选择的数据库类型，运行相应的初始化脚本：

**MySQL:**

```bash
mysql -u root -p wind_oauth < support/database/mysql_schema.sql
```

**SQLite:**

```bash
sqlite3 runtime/database.sqlite < support/database/sqlite_schema.sql
```

**PostgreSQL:**

```bash
psql -U postgres -d wind_oauth -f support/database/pgsql_schema.sql
```

### 4. 配置引导程序

编辑 `config/bootstrap.php`, 添加数据库引导:

```php
return [
    // ... 其他引导
    support\bootstrap\Database::class,
];
```

### 5. 配置 Twig 视图

编辑 `config/view.php`:

```php
return [
    'handler' => support\view\Twig::class,
    'options' => [
        'view_path' => app_path() . '/view',
        'cache_path' => runtime_path() . '/views',
        'options' => [
            'debug' => true,
            'auto_reload' => true,
        ]
    ]
];
```

### 6. 配置路由

编辑 `config/route.php`, 添加OAuth路由:

```php
<?php
use Webman\Route;
use app\controller\AuthController;
use app\controller\OauthController;
use app\controller\AdminController;

// 认证路由
Route::get('/login', [AuthController::class, 'login']);
Route::post('/login', [AuthController::class, 'loginSubmit']);
Route::get('/logout', [AuthController::class, 'logout']);

// OAuth 2.0 端点
Route::get('/oauth/authorize', [OauthController::class, 'authorize']);
Route::post('/oauth/authorize', [OauthController::class, 'authorizeSubmit']);
Route::post('/oauth/token', [OauthController::class, 'token']);
Route::post('/oauth/introspect', [OauthController::class, 'introspect']);
Route::post('/oauth/revoke', [OauthController::class, 'revoke']);
Route::get('/oauth/userinfo', [OauthController::class, 'userinfo']);

// 管理后台
Route::get('/admin', [AdminController::class, 'index']);
Route::get('/admin/clients', [AdminController::class, 'clients']);
Route::get('/admin/clients/create', [AdminController::class, 'createClient']);
Route::post('/admin/clients', [AdminController::class, 'storeClient']);
Route::get('/admin/clients/edit', [AdminController::class, 'editClient']);
Route::post('/admin/clients/update', [AdminController::class, 'updateClient']);
Route::post('/admin/clients/delete', [AdminController::class, 'deleteClient']);

Route::get('/admin/tokens', [AdminController::class, 'tokens']);
Route::post('/admin/tokens/revoke', [AdminController::class, 'revokeTokenAdmin']);

Route::get('/admin/scopes', [AdminController::class, 'scopes']);
Route::post('/admin/scopes', [AdminController::class, 'storeScope']);
Route::post('/admin/scopes/update', [AdminController::class, 'updateScope']);
Route::post('/admin/scopes/delete', [AdminController::class, 'deleteScope']);

Route::get('/admin/users', [AdminController::class, 'users']);
Route::post('/admin/users', [AdminController::class, 'storeUser']);
Route::post('/admin/users/update', [AdminController::class, 'updateUser']);
Route::post('/admin/users/delete', [AdminController::class, 'deleteUser']);
```

### 7. 启动服务器

**Windows:**

```bash
php windows.php start
```

**Linux/Mac:**

```bash
php start.php start
```

## 默认账户

- 用户名: `admin`
- 密码: `admin123`
- 邮箱: `admin@example.com`

## OAuth 2.0 使用流程

### 1. 创建OAuth客户端

1. 登录管理后台 http://localhost:8787/admin
2. 进入"客户端管理"
3. 点击"创建新客户端"
4. 填写客户端信息并保存
5. 记录生成的 `client_id` 和 `client_secret`

### 2. 授权码流程

#### Step 1: 获取授权码

引导用户访问授权页面:

```
GET /oauth/authorize?client_id={CLIENT_ID}&redirect_uri={REDIRECT_URI}&response_type=code&scope=basic&state={STATE}
```

参数说明:

- `client_id`: 客户端ID
- `redirect_uri`: 回调地址
- `response_type`: 固定为 `code`
- `scope`: 权限范围，空格分隔 (如: `basic profile email`)
- `state`: 可选，防CSRF攻击

用户同意授权后，会重定向到:

```
{REDIRECT_URI}?code={CODE}&state={STATE}
```

#### Step 2: 交换访问令牌

使用授权码交换访问令牌:

```bash
POST /oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code
&code={CODE}
&client_id={CLIENT_ID}
&client_secret={CLIENT_SECRET}
&redirect_uri={REDIRECT_URI}
```

响应:

```json
{
  "access_token": "...",
  "refresh_token": "...",
  "token_type": "Bearer",
  "expires_in": 7200,
  "scope": "basic profile"
}
```

#### Step 3: 使用访问令牌

```bash
GET /oauth/userinfo
Authorization: Bearer {ACCESS_TOKEN}
```

响应:

```json
{
  "user_id": 1,
  "username": "admin",
  "email": "admin@example.com",
  "name": "Administrator",
  "scope": [
    "basic",
    "profile"
  ]
}
```

### 3. 刷新令牌

```bash
POST /oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=refresh_token
&refresh_token={REFRESH_TOKEN}
&client_id={CLIENT_ID}
&client_secret={CLIENT_SECRET}
```

### 4. 撤销令牌

```bash
POST /oauth/revoke
Content-Type: application/x-www-form-urlencoded

token={ACCESS_TOKEN_OR_REFRESH_TOKEN}
```

### 5. 令牌验证（资源服务器）

```bash
POST /oauth/introspect
Content-Type: application/x-www-form-urlencoded

token={ACCESS_TOKEN}
```

响应:

```json
{
  "active": true,
  "user_id": 1,
  "username": "admin",
  "email": "admin@example.com",
  "scope": "basic profile"
}
```

## 数据库表结构

### users - 用户表

- 存储系统用户信息
- 支持管理员和普通用户

### oauth_clients - OAuth客户端表

- 存储第三方应用信息
- 包含 client_id 和 client_secret

### oauth_authorization_codes - 授权码表

- 临时存储授权码
- 10分钟有效期

### oauth_tokens - 令牌表

- 存储访问令牌和刷新令牌
- 访问令牌2小时有效期
- 刷新令牌30天有效期

### oauth_scopes - 权限范围表

- 定义可用的权限范围
- 支持默认权限设置

## 管理后台功能

### 仪表盘

- 显示系统统计信息
- 快速操作入口

### 客户端管理

- 创建/编辑/删除 OAuth 客户端
- 查看客户端详情
- 管理客户端权限范围

### 用户管理

- 创建/编辑/删除用户
- 设置管理员权限
- 启用/禁用用户

### 令牌管理

- 查看所有活跃令牌
- 撤销令牌
- 查看令牌详情

### 权限范围管理

- 创建/编辑/删除权限范围
- 设置默认权限

## 技术栈

- **框架**: Webman (基于 Workerman 的高性能 PHP 框架)
- **ORM**: Laravel Eloquent
- **模板引擎**: Twig
- **CSS框架**: TailwindCSS (CDN)
- **数据库**: MySQL / SQLite / PostgreSQL
- **PHP版本**: >= 8.3

## 安全建议

1. 生产环境中务必修改默认管理员密码
2. 使用 HTTPS 保护所有 OAuth 端点
3. 妥善保管 `client_secret`
4. 定期清理过期的授权码和令牌
5. 启用数据库外键约束
6. 配置合适的会话超时时间

## 许可证

BSD 3-Clause License

## 作者

skyhhjmk - admin@biliwind.com
