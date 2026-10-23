<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\CustomerService;
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
            'billing_mode_scope' => ['required', 'in:all,per_service'],
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
        $settings['billing_mode_scope'] = $data['billing_mode_scope'];
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

        // Mode seragam: semua layanan (kecuali yang berhenti) disamakan dengan mode ISP.
        $warnings = [];
        if ($data['billing_mode_scope'] === 'all') {
            $mode = $data['default_billing_mode'];
            $toChange = CustomerService::where('status', '!=', 'terminated')->where('billing_mode', '!=', $mode);
            $fromPostpaid = (clone $toChange)->where('billing_mode', 'postpaid')->count();
            $changed = $toChange->update(['billing_mode' => $mode]);
            if ($changed) {
                AuditLogger::log('services.billing_mode_bulk', null, null, ['mode' => $mode, 'services' => $changed]);
                $warnings[] = "{$changed} layanan diubah menjadi ".CustomerService::BILLING_MODES[$mode].'.';
                if ($mode === 'prepaid' && $fromPostpaid) {
                    $warnings[] = "{$fromPostpaid} layanan beralih dari pascabayar ke prabayar: pemakaian bulan berjalan sebelum peralihan tidak tertagih otomatis. Buat invoice manual bila perlu.";
                }
            }
        }
        AuditLogger::log('settings.updated', $tenant, $before, ['brand_name' => $tenant->brand_name, 'theme' => $tenant->theme, 'settings' => $settings]);

        return back()->with('success', 'Pengaturan disimpan.')->with('warnings', $warnings);
    }
}
