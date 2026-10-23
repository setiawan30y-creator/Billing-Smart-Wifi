<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        return view('audit.index', [
            'logs' => AuditLog::with(['user', 'tenant'])
                ->when($request->query('tenant'), fn ($q, $t) => $q->where('tenant_id', $t))
                ->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', $a.'%'))
                ->latest('created_at')->paginate(30)->withQueryString(),
            'tenants' => Tenant::orderBy('name')->get(['id', 'name']),
            'platform' => true,
        ]);
    }
}
