<?php
namespace app\controller;

use app\model\User;
use support\Request;
use support\Response;

class AuthController
{
    /**
     * 登录页面
     */
    public function login(Request $request): Response
    {
        if (session('user_id')) {
            return redirect('/profile');
        }

        return view('auth/login');
    }

    /**
     * 处理登录
     */
    public function loginSubmit(Request $request): Response
    {
        $username = $request->post('username');
        $password = $request->post('password');

        if (!$username || !$password) {
            return json(['error' => '用户名和密码不能为空'], 400);
        }

        $user = User::where('username', $username)
            ->orWhere('email', $username)
            ->first();

        if (!$user || !password_verify($password, $user->password)) {
            return json(['error' => '用户名或密码错误'], 401);
        }

        if ($user->status != 1) {
            return json(['error' => '账号已被禁用'], 403);
        }

        // 设置session
        session(['user_id' => $user->id, 'username' => $user->username]);

        // 如果有OAuth授权请求，重定向回授权页面
        if ($oauthRequest = session('oauth_request')) {
            session(['oauth_request' => null]);
            $queryString = http_build_query($oauthRequest);
            return json(['redirect' => '/oauth/authorize?' . $queryString]);
        }

        return json(['redirect' => '/profile']);
    }

    /**
     * 登出
     */
    public function logout(Request $request): Response
    {
        session(['user_id' => null, 'username' => null]);
        return redirect('/login');
    }

    /**
     * 注册页面
     */
    public function register(Request $request): Response
    {
        if (session('user_id')) {
            return redirect('/profile');
        }

        return view('auth/register');
    }

    /**
     * 处理注册
     */
    public function registerSubmit(Request $request): Response
    {
        $username = $request->post('username');
        $email = $request->post('email');
        $password = $request->post('password');
        $name = $request->post('name');

        // 验证
        if (!$username || !$email || !$password) {
            return json(['error' => '请填写完整信息'], 400);
        }

        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            return json(['error' => '用户名格式不正确'], 400);
        }

        if (strlen($password) < 6) {
            return json(['error' => '密码至少需要6个字符'], 400);
        }

        // 检查用户名是否已存在
        if (User::where('username', $username)->exists()) {
            return json(['error' => '用户名已被使用'], 400);
        }

        // 检查邮箱是否已存在
        if (User::where('email', $email)->exists()) {
            return json(['error' => '邮箱已被使用'], 400);
        }

        try {
            // 创建用户
            $user = User::create([
                'username' => $username,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'name' => $name,
                'is_admin' => 0,
                'status' => 1,
            ]);

            // 自动登录
            session(['user_id' => $user->id, 'username' => $user->username]);

            // 如果有OAuth授权请求，重定向回授权页面
            if ($oauthRequest = session('oauth_request')) {
                session(['oauth_request' => null]);
                $queryString = http_build_query($oauthRequest);
                return json(['success' => true, 'redirect' => '/oauth/authorize?' . $queryString]);
            }

            return json(['success' => true, 'redirect' => '/profile']);
        } catch (\Exception $e) {
            return json(['error' => '注册失败：' . $e->getMessage()], 500);
        }
    }

    /**
     * 个人中心
     */
    public function profile(Request $request): Response
    {
        $userId = session('user_id');
        if (!$userId) {
            return redirect('/login');
        }

        $user = User::find($userId);
        if (!$user) {
            return redirect('/login');
        }

        return view('profile', [
            'user' => $user,
            'is_admin' => $user->is_admin
        ]);
    }

    /**
     * 更新个人信息
     */
    public function profileUpdate(Request $request): Response
    {
        $userId = session('user_id');
        if (!$userId) {
            return json(['error' => 'unauthorized'], 401);
        }

        $user = User::find($userId);
        if (!$user) {
            return json(['error' => 'user not found'], 404);
        }

        $email = $request->post('email');
        $name = $request->post('name');

        try {
            $user->update([
                'email' => $email,
                'name' => $name,
            ]);

            return json(['success' => true]);
        } catch (\Exception $e) {
            return json(['error' => '更新失败'], 500);
        }
    }

    /**
     * 修改密码
     */
    public function profilePassword(Request $request): Response
    {
        $userId = session('user_id');
        if (!$userId) {
            return json(['error' => 'unauthorized'], 401);
        }

        $user = User::find($userId);
        if (!$user) {
            return json(['error' => 'user not found'], 404);
        }

        $oldPassword = $request->post('old_password');
        $newPassword = $request->post('new_password');

        // 验证旧密码
        if (!password_verify($oldPassword, $user->getAuthPassword())) {
            return json(['error' => '当前密码错误'], 400);
        }

        if (strlen($newPassword) < 6) {
            return json(['error' => '新密码至少需要6个字符'], 400);
        }

        try {
            $user->update([
                'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            ]);

            return json(['success' => true]);
        } catch (\Exception $e) {
            return json(['error' => '修改失败'], 500);
        }
    }
}
