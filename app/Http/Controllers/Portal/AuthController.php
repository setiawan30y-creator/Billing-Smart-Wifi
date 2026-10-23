<?php

namespace App\Http\Controllers\Portal;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Login portal pelanggan: ID pelanggan + password, dibatasi pada tenant di URL.
 */
class AuthController extends PortalController
{
    public function show(Request $request)
    {
        return view('portal.login', ['tenant' => $this->portalTenant($request)]);
    }

    public function login(Request $request, string $tenantCode)
    {
        $tenant = $this->portalTenant($request);
        $data = $request->validate(['customer_code' => ['required', 'string', 'max:30'], 'password' => ['required', 'string']]);
        $key = 'portal-login:'.$tenant->id.':'.strtoupper($data['customer_code']).':'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['customer_code' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.']);
        }

        $customer = Customer::where('customer_code', strtoupper(trim($data['customer_code'])))->first();
        $account = $customer ? CustomerAccount::where('tenant_id', $tenant->id)->where('customer_id', $customer->id)->first() : null;

        if (! $account || ! $account->is_active || ! $account->password || ! Hash::check($data['password'], $account->password)) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['customer_code' => 'ID pelanggan atau password salah, atau akun portal belum aktif.']);
        }

        RateLimiter::clear($key);
        Auth::guard('customer')->login($account, $request->boolean('remember'));
        $request->session()->regenerate();
        $account->forceFill(['last_login_at' => now()])->save();
        AuditLogger::log('portal.login', $customer, null, null, null, $tenant->id);

        return redirect()->intended(route('portal.home', $tenant->code));
    }

    public function logout(Request $request, string $tenantCode)
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login', $tenantCode);
    }

    public function activation(Request $request, string $tenantCode, string $token)
    {
        $account = $this->findByToken($request, $token);
        abort_unless($account, 404, 'Tautan aktivasi tidak valid atau sudah kedaluwarsa.');

        return view('portal.activate', ['tenant' => $this->portalTenant($request), 'token' => $token, 'customer' => $account->customer]);
    }

    public function activate(Request $request, string $tenantCode, string $token)
    {
        $account = $this->findByToken($request, $token);
        abort_unless($account, 404, 'Tautan aktivasi tidak valid atau sudah kedaluwarsa.');
        $request->validate(['password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()]]);

        $account->password = $request->input('password');
        $account->activation_token_hash = null;
        $account->activation_expires_at = null;
        $account->save();
        AuditLogger::log('portal.activated', $account->customer, null, null, null, $account->tenant_id);

        Auth::guard('customer')->login($account);
        $request->session()->regenerate();

        return redirect()->route('portal.home', $tenantCode)->with('success', 'Akun portal aktif. Selamat datang!');
    }

    protected function findByToken(Request $request, string $token): ?CustomerAccount
    {
        return CustomerAccount::where('tenant_id', $this->portalTenant($request)->id)
            ->where('activation_token_hash', hash('sha256', $token))
            ->where('activation_expires_at', '>', now())
            ->where('is_active', true)->first();
    }
}
