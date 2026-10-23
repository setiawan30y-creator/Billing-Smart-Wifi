<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        return view('audit.index', [
            'logs' => AuditLog::where('tenant_id', $this->tenant()->id)->with('user')
                ->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', $a.'%'))
                ->latest('created_at')->paginate(30)->withQueryString(),
            'tenants' => collect(),
            'platform' => false,
        ]);
    }
}
