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
        $userId = session('user_id');
        $oauthCookie = $request->cookie('oauth_request');
        
        file_put_contents(runtime_path() . '/debug.log', date('Y-m-d H:i:s') . ' [DEBUG] login() - user_id: ' . ($userId ?: 'null') . "\n", FILE_APPEND);
        file_put_contents(runtime_path() . '/debug.log', date('Y-m-d H:i:s') . ' [DEBUG] login() - oauth_request cookie exists: ' . ($oauthCookie ? 'yes' : 'no') . "\n", FILE_APPEND);
        
        if ($userId) {
            // 如果有OAuth授权请求，重定向回授权页面
            if ($oauthCookie) {
                file_put_contents(runtime_path() . '/debug.log', date('Y-m-d H:i:s') . ' [DEBUG] login() - Redirecting to OAuth authorize with cookie data' . "\n", FILE_APPEND);
                // URL解码cookie值
                $oauthRequest = json_decode(urldecode($oauthCookie), true);
                if ($oauthRequest && is_array($oauthRequest)) {
                    $queryString = http_build_query($oauthRequest);
                    $response = redirect('/oauth/authorize?' . $queryString);
                    // 使用正确的方式清除cookie：设置过期时间为过去
                    $response->cookie('oauth_request', '', time() - 3600, '/');
                    return $response;
                }
            }
            file_put_contents(runtime_path() . '/debug.log', date('Y-m-d H:i:s') . ' [DEBUG] login() - No OAuth request, redirecting to profile' . "\n", FILE_APPEND);
            return redirect('/profile');
        }

        file_put_contents(runtime_path() . '/debug.log', date('Y-m-d H:i:s') . ' [DEBUG] login() - Not logged in, showing login form' . "\n", FILE_APPEND);
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
        $request->session()->set('user_id', $user->id);
        $request->session()->set('username', $user->username);
        
        file_put_contents(runtime_path() . '/debug.log', date('Y-m-d H:i:s') . ' [DEBUG] loginSubmit() - Set session - user_id: ' . $user->id . "\n", FILE_APPEND);
        file_put_contents(runtime_path() . '/debug.log', date('Y-m-d H:i:s') . ' [DEBUG] loginSubmit() - Verify session - user_id: ' . session('user_id') . "\n", FILE_APPEND);
        file_put_contents(runtime_path() . '/debug.log', date('Y-m-d H:i:s') . ' [DEBUG] loginSubmit() - oauth_request cookie: ' . ($request->cookie('oauth_request') ?: 'null') . "\n", FILE_APPEND);

        // 如果有OAuth授权请求，重定向回授权页面
        if ($oauthRequestJson = $request->cookie('oauth_request')) {
            // URL解码cookie值
            $oauthRequest = json_decode(urldecode($oauthRequestJson), true);
            if ($oauthRequest && is_array($oauthRequest)) {
                $queryString = http_build_query($oauthRequest);
                return json([
                    'redirect' => '/oauth/authorize?' . $queryString,
                    'clear_cookie' => 'oauth_request'
                ]);
            }
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
            $request->session()->set('user_id', $user->id);
            $request->session()->set('username', $user->username);

            // 如果有OAuth授权请求，重定向回授权页靈
            if ($oauthRequestJson = $request->cookie('oauth_request')) {
                // URL解码cookie值
                $oauthRequest = json_decode(urldecode($oauthRequestJson), true);
                if ($oauthRequest && is_array($oauthRequest)) {
                    $queryString = http_build_query($oauthRequest);
                    return json([
                        'success' => true,
                        'redirect' => '/oauth/authorize?' . $queryString,
                        'clear_cookie' => 'oauth_request'
                    ]);
                }
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
