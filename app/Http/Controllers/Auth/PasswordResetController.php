<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Lupa password: tautan reset sekali pakai (berlaku 60 menit) dikirim ke email.
 * Pesan selalu sama agar tidak membocorkan email mana yang terdaftar.
 * @2026-Alifiandra
 */
class PasswordResetController extends Controller
{
    public function request()
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request)
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink(['email' => $request->input('email'), 'is_active' => true]);

        if ($status === Password::RESET_THROTTLED) {
            return back()->withInput()->withErrors(['email' => 'Permintaan terlalu sering. Coba lagi dalam beberapa menit.']);
        }

        return back()->with('success', 'Jika email tersebut terdaftar dan aktif, tautan reset password telah dikirim. Periksa kotak masuk / spam Anda.');
    }

    public function edit(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request)
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token') + ['is_active' => true],
            function (User $user, string $password) {
                $user->password = $password;
                $user->email_verified_at ??= now();
                $user->setRememberToken(Str::random(60));
                $user->save();
                event(new PasswordReset($user));
                AuditLogger::log('auth.password_reset', $user, null, null, null, $user->tenant_id);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Tautan reset tidak valid atau sudah kedaluwarsa. Silakan minta tautan baru.']);
        }

        return redirect()->route('login')->with('success', 'Password berhasil diubah. Silakan masuk dengan password baru.');
    }
}
