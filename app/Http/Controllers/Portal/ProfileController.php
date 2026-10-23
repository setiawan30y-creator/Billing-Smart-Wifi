<?php

namespace App\Http\Controllers\Portal;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends PortalController
{
    public function edit(Request $request)
    {
        return view('portal.profile', ['tenant' => $this->portalTenant($request), 'customer' => $this->customer()]);
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        $account = $this->account();
        if (! Hash::check($data['current_password'], $account->password)) {
            throw ValidationException::withMessages(['current_password' => 'Password saat ini salah.']);
        }
        $account->password = $data['password'];
        $account->save();

        return back()->with('success', 'Password diperbarui.');
    }
}
