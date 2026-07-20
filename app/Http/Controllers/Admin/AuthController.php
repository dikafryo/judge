<?php

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

        $request->session()->put('event_admin_' . $event->id, true);

        return redirect()->route('admin.dashboard', $event);
    }

    public function logout(Request $request, Event $event): RedirectResponse
    {
        $request->session()->forget('event_admin_' . $event->id);

        return redirect()->route('home');
    }
}
