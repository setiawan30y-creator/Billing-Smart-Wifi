<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

abstract class PortalController extends Controller
{
    protected function portalTenant(Request $request): Tenant
    {
        return $request->attributes->get('portalTenant');
    }

    protected function account(): CustomerAccount
    {
        return Auth::guard('customer')->user();
    }

    /** Pelanggan milik akun yang login (tenant scope aktif). */
    protected function customer(): Customer
    {
        return Customer::findOrFail($this->account()->customer_id);
    }
}
