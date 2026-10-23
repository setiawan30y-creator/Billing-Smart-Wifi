<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('isp.settings', ['tenant' => $this->tenant()->load('profile')]);
    }

    public function update(Request $request)
    {
        $tenant = $this->tenant();
        $data = $request->validate([
            'brand_name' => ['nullable', 'string', 'max:120'],
            'theme' => ['required', 'in:default,light,dark'],
            'quota_policy' => ['required', 'in:warn,block_new'],
            'default_billing_mode' => ['required', 'in:prepaid,postpaid'],
            'invoice_prefix' => ['required', 'alpha_dash', 'max:10'],
            'reminder_offsets' => ['nullable', 'array'],
            'reminder_offsets.*' => ['integer', 'in:-7,-3,-1,0,1,3,7'],
            'payment_instructions' => ['nullable', 'string', 'max:1500'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:max_width=2000,max_height=2000'],
        ]);

        $before = ['brand_name' => $tenant->brand_name, 'theme' => $tenant->theme, 'settings' => $tenant->settings];
        $settings = $tenant->settings ?? [];
        $settings['quota_policy'] = $data['quota_policy'];
        $settings['default_billing_mode'] = $data['default_billing_mode'];
        $settings['invoice_prefix'] = strtoupper($data['invoice_prefix']);
        $settings['reminder_offsets'] = array_values(array_map('intval', $data['reminder_offsets'] ?? []));
        $settings['payment_instructions'] = $data['payment_instructions'] ?? null;

        $tenant->brand_name = $data['brand_name'] ?? null;
        $tenant->theme = $data['theme'];
        $tenant->settings = $settings;
        if ($request->hasFile('logo')) {
            $tenant->logo_path = $request->file('logo')->store('tenant-logos', 'public');
        }
        $tenant->save();
        AuditLogger::log('settings.updated', $tenant, $before, ['brand_name' => $tenant->brand_name, 'theme' => $tenant->theme, 'settings' => $settings]);

        return back()->with('success', 'Pengaturan disimpan.');
    }
}
