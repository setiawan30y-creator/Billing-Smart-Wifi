<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials = ['email' => strtolower(trim($data['email'])), 'password' => $data['password'], 'is_active' => true];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Email atau password salah, atau akun belum aktif.']);
        }

        $request->session()->regenerate();
        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();
        AuditLogger::log('auth.login', $user, null, null, null, $user->tenant_id);

        return redirect()->intended($user->isSuperAdmin() ? route('admin.dashboard') : route('app.dashboard'));
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        AuditLogger::log('auth.logout', $user, null, null, null, $user?->tenant_id);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
