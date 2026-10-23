<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserInvitation;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class ActivationController extends Controller
{
    public function show(string $token)
    {
        $inv = UserInvitation::findValid($token);
        abort_unless($inv, 404, 'Tautan aktivasi tidak valid atau sudah kedaluwarsa.');

        return view('auth.activate', ['invitation' => $inv->load('user.tenant'), 'token' => $token]);
    }

    public function store(Request $request, string $token)
    {
        $request->validate(['password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]]);

        $user = DB::transaction(function () use ($token, $request) {
            $inv = UserInvitation::where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            abort_unless($inv && ! $inv->used_at && $inv->expires_at->isFuture(), 404, 'Tautan aktivasi tidak valid atau sudah kedaluwarsa.');

            $inv->forceFill(['used_at' => now()])->save();
            $user = $inv->user;
            $user->password = $request->input('password');
            $user->email_verified_at = now();
            $user->save();

            return $user;
        });

        AuditLogger::log('auth.activated', $user, null, null, null, $user->tenant_id);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('success', 'Akun berhasil diaktifkan.');
    }
}
