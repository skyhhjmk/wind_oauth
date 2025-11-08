<?php
namespace app\controller;

use app\model\OAuthClient;
use app\model\OAuthScope;
use app\model\OAuthToken;
use app\model\OAuthProvider;
use app\model\User;
use app\service\OAuthService;
use support\Request;
use support\Response;

class AdminController
{
    protected OAuthService $oauthService;

    public function __construct()
    {
        $this->oauthService = new OAuthService();
    }

    /**
     * 检查管理员权限
     */
    protected function checkAdmin(Request $request): ?Response
    {
        $userId = session('user_id');
        if (!$userId) {
            return redirect('/login');
        }

        $user = User::find($userId);
        if (!$user || !$user->is_admin) {
            return view('error/403', ['message' => '需要管理员权限']);
        }

        return null;
    }

    /**
     * 后台首页
     */
    public function index(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $stats = [
            'users' => User::count(),
            'clients' => OAuthClient::count(),
            'tokens' => OAuthToken::count(),
            'scopes' => OAuthScope::count(),
        ];

        return view('admin/index', ['stats' => $stats]);
    }

    /**
     * 客户端列表
     */
    public function clients(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $clients = OAuthClient::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin/clients', ['clients' => $clients]);
    }

    /**
     * 创建客户端页面
     */
    public function createClient(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $scopes = OAuthScope::all();
        return view('admin/client_create', ['scopes' => $scopes]);
    }

    /**
     * 保存客户端
     */
    public function storeClient(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $data = $request->post();
        $credentials = $this->oauthService->generateClientCredentials();

        $client = OAuthClient::create([
            'user_id' => session('user_id'),
            'name' => $data['name'],
            'client_id' => $credentials['client_id'],
            'client_secret' => password_hash($credentials['client_secret'], PASSWORD_DEFAULT),
            'redirect_uri' => $data['redirect_uri'],
            'grant_types' => $data['grant_types'] ?? ['authorization_code'],
            'scope' => $data['scope'] ?? [],
            'status' => 1,
        ]);

        return json([
            'success' => true,
            'client' => $client,
            'credentials' => $credentials
        ]);
    }

    /**
     * 编辑客户端
     */
    public function editClient(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $id = $request->get('id');
        $client = OAuthClient::find($id);

        if (!$client) {
            return view('error/404');
        }

        $scopes = OAuthScope::all();
        return view('admin/client_edit', ['client' => $client, 'scopes' => $scopes]);
    }

    /**
     * 更新客户端
     */
    public function updateClient(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $id = $request->post('id');
        $client = OAuthClient::find($id);

        if (!$client) {
            return json(['error' => '客户端不存在'], 404);
        }

        $data = $request->post();
        $client->update([
            'name' => $data['name'] ?? $client->name,
            'redirect_uri' => $data['redirect_uri'] ?? $client->redirect_uri,
            'grant_types' => $data['grant_types'] ?? $client->grant_types,
            'scope' => $data['scope'] ?? $client->scope,
            'status' => $data['status'] ?? $client->status,
        ]);

        return json(['success' => true, 'client' => $client]);
    }

    /**
     * 删除客户端
     */
    public function deleteClient(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $id = $request->post('id');
        $client = OAuthClient::find($id);

        if (!$client) {
            return json(['error' => '客户端不存在'], 404);
        }

        $client->delete();
        return json(['success' => true]);
    }

    /**
     * 令牌列表
     */
    public function tokens(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $tokens = OAuthToken::with(['user', 'client'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('admin/tokens', ['tokens' => $tokens]);
    }

    /**
     * 撤销令牌
     */
    public function revokeTokenAdmin(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $id = $request->post('id');
        $token = OAuthToken::find($id);

        if (!$token) {
            return json(['error' => '令牌不存在'], 404);
        }

        $token->delete();
        return json(['success' => true]);
    }

    /**
     * 权限范围列表
     */
    public function scopes(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $scopes = OAuthScope::orderBy('scope')->get();
        return view('admin/scopes', ['scopes' => $scopes]);
    }

    /**
     * 保存权限范围
     */
    public function storeScope(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $data = $request->post();
        
        $scope = OAuthScope::create([
            'scope' => $data['scope'],
            'description' => $data['description'] ?? '',
            'is_default' => $data['is_default'] ?? 0,
        ]);

        return json(['success' => true, 'scope' => $scope]);
    }

    /**
     * 更新权限范围
     */
    public function updateScope(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $id = $request->post('id');
        $scope = OAuthScope::find($id);

        if (!$scope) {
            return json(['error' => '权限范围不存在'], 404);
        }

        $data = $request->post();
        $scope->update([
            'description' => $data['description'] ?? $scope->description,
            'is_default' => $data['is_default'] ?? $scope->is_default,
        ]);

        return json(['success' => true, 'scope' => $scope]);
    }

    /**
     * 删除权限范围
     */
    public function deleteScope(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $id = $request->post('id');
        $scope = OAuthScope::find($id);

        if (!$scope) {
            return json(['error' => '权限范围不存在'], 404);
        }

        $scope->delete();
        return json(['success' => true]);
    }

    /**
     * 用户列表
     */
    public function users(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $users = User::orderBy('created_at', 'desc')->paginate(20);
        return view('admin/users', ['users' => $users]);
    }

    /**
     * 获取单个用户信息
     */
    public function getUser(Request $request, $id): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $user = User::find($id);

        if (!$user) {
            return json(['error' => '用户不存在'], 404);
        }

        return json(['success' => true, 'data' => $user]);
    }

    /**
     * 创建用户
     */
    public function storeUser(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $data = $request->post();

        $user = User::create([
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'name' => $data['name'] ?? '',
            'is_admin' => $data['is_admin'] ?? 0,
            'status' => 1,
        ]);

        return json(['success' => true, 'user' => $user]);
    }

    /**
     * 更新用户
     */
    public function updateUser(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $id = $request->post('id');
        $user = User::find($id);

        if (!$user) {
            return json(['error' => '用户不存在'], 404);
        }

        $data = $request->post();
        $updateData = [
            'username' => $data['username'] ?? $user->username,
            'email' => $data['email'] ?? $user->email,
            'name' => $data['name'] ?? $user->name,
            'is_admin' => isset($data['is_admin']) ? ($data['is_admin'] ? 1 : 0) : $user->is_admin,
            'status' => $data['status'] ?? $user->status,
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $user->update($updateData);

        return json(['success' => true, 'user' => $user]);
    }

    /**
     * 删除用户
     */
    public function deleteUser(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $id = $request->post('id');
        
        // 不能删除自己
        if ($id == session('user_id')) {
            return json(['error' => '不能删除当前登录用户'], 400);
        }

        $user = User::find($id);

        if (!$user) {
            return json(['error' => '用户不存在'], 404);
        }

        $user->delete();
        return json(['success' => true]);
    }

    /**
     * OAuth提供商列表
     */
    public function providers(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $providers = OAuthProvider::orderBy('sort_order')->orderBy('created_at', 'desc')->get();
        return view('admin/providers', ['providers' => $providers]);
    }

    /**
     * 创建OAuth提供商页面
     */
    public function createProvider(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        return view('admin/provider_create');
    }

    /**
     * 保存OAuth提供商
     */
    public function storeProvider(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $data = $request->post();

        // 验证slug唯一性
        if (OAuthProvider::where('slug', $data['slug'])->exists()) {
            return json(['error' => '提供商标识已存在'], 400);
        }

        $provider = OAuthProvider::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'client_id' => $data['client_id'],
            'client_secret' => $data['client_secret'],
            'authorize_url' => $data['authorize_url'],
            'token_url' => $data['token_url'],
            'userinfo_url' => $data['userinfo_url'],
            'scope' => $data['scope'] ?? '',
            'icon_class' => $data['icon_class'] ?? '',
            'button_color' => $data['button_color'] ?? '#4F46E5',
            'status' => $data['status'] ?? 1,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return json(['success' => true, 'provider' => $provider]);
    }

    /**
     * 编辑OAuth提供商
     */
    public function editProvider(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $id = $request->get('id');
        $provider = OAuthProvider::find($id);

        if (!$provider) {
            return view('error/404');
        }

        return view('admin/provider_edit', ['provider' => $provider]);
    }

    /**
     * 更新OAuth提供商
     */
    public function updateProvider(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $id = $request->post('id');
        $provider = OAuthProvider::find($id);

        if (!$provider) {
            return json(['error' => 'OAuth提供商不存在'], 404);
        }

        $data = $request->post();

        // 验证slug唯一性（排除自己）
        if (isset($data['slug']) && $data['slug'] !== $provider->slug) {
            if (OAuthProvider::where('slug', $data['slug'])->exists()) {
                return json(['error' => '提供商标识已存在'], 400);
            }
        }

        $provider->update([
            'name' => $data['name'] ?? $provider->name,
            'slug' => $data['slug'] ?? $provider->slug,
            'client_id' => $data['client_id'] ?? $provider->client_id,
            'client_secret' => $data['client_secret'] ?? $provider->client_secret,
            'authorize_url' => $data['authorize_url'] ?? $provider->authorize_url,
            'token_url' => $data['token_url'] ?? $provider->token_url,
            'userinfo_url' => $data['userinfo_url'] ?? $provider->userinfo_url,
            'scope' => $data['scope'] ?? $provider->scope,
            'icon_class' => $data['icon_class'] ?? $provider->icon_class,
            'button_color' => $data['button_color'] ?? $provider->button_color,
            'status' => isset($data['status']) ? ($data['status'] ? 1 : 0) : $provider->status,
            'sort_order' => $data['sort_order'] ?? $provider->sort_order,
        ]);

        return json(['success' => true, 'provider' => $provider]);
    }

    /**
     * 删除OAuth提供商
     */
    public function deleteProvider(Request $request): Response
    {
        if ($error = $this->checkAdmin($request)) {
            return $error;
        }

        $id = $request->post('id');
        $provider = OAuthProvider::find($id);

        if (!$provider) {
            return json(['error' => 'OAuth提供商不存在'], 404);
        }

        $provider->delete();
        return json(['success' => true]);
    }
}
