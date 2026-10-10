<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(Event $event): View
    {
        return view('admin.login', compact('event'));
    }

    public function login(Request $request, Event $event): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ], [], ['password' => '비밀번호']);

        if (! Hash::check($request->input('password'), $event->admin_password)) {
            return back()->withErrors(['password' => '비밀번호가 올바르지 않습니다.']);
        }

        // 로그인 전의 세션 id 를 그대로 쓰면 미리 심어 둔 id 로 권한을 가로챌 수 있다(세션 고정)
        $request->session()->regenerate();
        $request->session()->put($event->adminSessionKey(), true);

        return redirect()->route('admin.dashboard', $event);
    }

    public function logout(Request $request, Event $event): RedirectResponse
    {
        $request->session()->forget($event->adminSessionKey());

        return redirect()->route('home');
    }
}
