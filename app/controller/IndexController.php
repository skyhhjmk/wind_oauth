<?php

namespace app\controller;

use app\model\User;
use support\Request;

class IndexController
{
    public function index(Request $request)
    {
        $userId = session('user_id');
        $user = null;
        $isAdmin = false;
        
        if ($userId) {
            $user = User::find($userId);
            $isAdmin = $user && $user->is_admin;
        }
        
        return view('index', [
            'user_id' => $userId,
            'username' => session('username'),
            'is_admin' => $isAdmin
        ]);
    }

    public function view(Request $request)
    {
        return view('index/view', ['name' => 'webman']);
    }

    public function json(Request $request)
    {
        return json(['code' => 0, 'msg' => 'ok']);
    }

}
