<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\BackupArtifact;
use App\Models\BackupAuditLog;
use App\Models\BackupJob;
use App\Models\RestoreJob;
use App\Services\BackupService;
use Illuminate\Support\Facades\Storage;

/**
 * Backup data tenant oleh Admin ISP: buat, unduh (terenkripsi), uji restore.
 * Restore sebenarnya hanya dilakukan Super Admin.
 */
class BackupController extends Controller
{
    public function index()
    {
        $tid = $this->tenant()->id;

        return view('isp.backups.index', [
            'jobs' => BackupJob::with(['artifact', 'creator'])->where('tenant_id', $tid)->latest('id')->paginate(15),
            'tests' => RestoreJob::where('tenant_id', $tid)->latest('id')->limit(10)->get(),
            'logs' => BackupAuditLog::with('user')->where('tenant_id', $tid)->latest('id')->limit(15)->get(),
        ]);
    }

    public function store(BackupService $service)
    {
        $job = $service->backup($this->tenant());

        return back()->with($job->status === 'completed' ? 'success' : 'error', $job->status === 'completed' ? 'Backup selesai dibuat.' : 'Backup gagal: '.$job->error);
    }

    public function download(BackupArtifact $artifact)
    {
        abort_unless($artifact->tenant_id === $this->tenant()->id, 404);
        BackupAuditLog::create(['tenant_id' => $artifact->tenant_id, 'action' => 'backup.downloaded', 'subject_id' => $artifact->backup_job_id, 'user_id' => auth()->id()]);

        return Storage::disk(config('almara.private_disk'))->download($artifact->path, basename($artifact->path));
    }

    public function test(BackupArtifact $artifact, BackupService $service)
    {
        abort_unless($artifact->tenant_id === $this->tenant()->id, 404);
        $job = $service->testRestore($artifact);

        return back()->with($job->status === 'passed' ? 'success' : 'error', $job->status === 'passed'
            ? 'Uji restore LULUS: '.implode(' · ', $job->report['checks'] ?? [])
            : 'Uji restore GAGAL: '.($job->report['error'] ?? implode(' · ', $job->report['checks'] ?? [])));
    }
}
