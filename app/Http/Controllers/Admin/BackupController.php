<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupArtifact;
use App\Models\BackupJob;
use App\Models\RestoreJob;
use App\Models\Tenant;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Throwable;

class BackupController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.backups.index', [
            'tenants' => Tenant::orderBy('name')->get(['id', 'name', 'code']),
            'jobs' => BackupJob::with(['artifact', 'tenant'])->when($request->query('tenant'), fn ($q, $t) => $q->where('tenant_id', $t))->latest('id')->paginate(20)->withQueryString(),
            'restores' => RestoreJob::with(['tenant', 'requester'])->latest('id')->limit(15)->get(),
        ]);
    }

    public function run(Request $request, BackupService $service)
    {
        $data = $request->validate(['tenant_id' => ['required', 'exists:tenants,id']]);
        $job = $service->backup(Tenant::findOrFail($data['tenant_id']));

        return back()->with($job->status === 'completed' ? 'success' : 'error', $job->status === 'completed' ? 'Backup dibuat.' : 'Backup gagal: '.$job->error);
    }

    public function test(BackupArtifact $artifact, BackupService $service)
    {
        $job = $service->testRestore($artifact);

        return back()->with($job->status === 'passed' ? 'success' : 'error', 'Uji restore '.($job->status === 'passed' ? 'LULUS' : 'GAGAL').': '.($job->report['error'] ?? implode(' · ', $job->report['checks'] ?? [])));
    }

    public function restore(Request $request, BackupArtifact $artifact, BackupService $service)
    {
        $tenant = Tenant::findOrFail($artifact->tenant_id);
        $request->validate([
            'confirm_code' => ['required', 'in:'.$tenant->code],
            'reason' => ['required', 'string', 'max:250'],
        ], ['confirm_code.in' => 'Ketik kode ISP '.$tenant->code.' dengan tepat untuk konfirmasi.']);
        try {
            $job = $service->restore($artifact, $request->input('reason'));
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Restore {$tenant->name} selesai. Backup pengaman dibuat sebelum menimpa (job #{$job->report['safety_backup_job']}).");
    }
}
