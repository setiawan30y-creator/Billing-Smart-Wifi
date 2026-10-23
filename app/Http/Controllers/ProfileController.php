<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'theme_preference' => ['nullable', 'in:light,dark'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $user->fill(['name' => trim($data['name']), 'phone' => $data['phone'] ?? null, 'theme_preference' => $data['theme_preference'] ?? null]);
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();
        AuditLogger::log('profile.updated', $user, null, ['password_changed' => ! empty($data['password'])], null, $user->tenant_id);

        return back()->with('success', 'Profil diperbarui.');
    }
}
