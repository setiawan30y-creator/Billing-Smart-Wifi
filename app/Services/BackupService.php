<?php

namespace App\Services;

use App\Models\BackupArtifact;
use App\Models\BackupAuditLog;
use App\Models\BackupJob;
use App\Models\RestoreJob;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Backup & restore per tenant.
 * - Backup: seluruh data operasional tenant → JSON → gzip → dienkripsi (APP_KEY) → disk privat + checksum.
 * - Uji restore: dipulihkan ke database SQLite sementara yang terpisah, dicek foreign key, isolasi tenant & jumlah baris.
 * - Restore: hanya Super Admin, wajib lulus uji restore + backup pengaman otomatis sebelum menimpa.
 * Berkas unggahan (KTP, foto, bukti bayar) tidak termasuk; backup folder storage/app/private secara terpisah.
 * @2026-Alifiandra
 */
class BackupService
{
    /** Tabel dengan kolom tenant_id (urutan = urutan dependensi untuk restore). */
    public const TENANT_TABLES = [
        'tenant_profiles', 'tenant_domains', 'tenant_subscriptions', 'users', 'tenant_sequences',
        'service_packages', 'network_devices', 'odcs', 'odps', 'customers', 'customer_accounts', 'customer_services', 'odp_ports',
        'invoices', 'payments', 'payment_webhook_events', 'notification_templates', 'notification_logs',
        'tickets', 'inventory_items', 'inventory_locations', 'inventory_serial_numbers', 'inventory_balances', 'work_orders', 'inventory_movements',
        'technician_availability', 'import_batches', 'monitoring_targets', 'monitoring_alerts', 'network_incidents', 'network_status_checks',
        'service_announcements', 'collection_assignments', 'commission_rules', 'commission_settlements', 'commission_entries',
        'reconciliation_batches', 'accounting_periods', 'financial_adjustments',
    ];

    /** Tabel anak tanpa tenant_id: tabel => [kolom FK, tabel induk]. */
    public const CHILD_TABLES = [
        'role_user' => ['user_id', 'users'],
        'invoice_items' => ['invoice_id', 'invoices'],
        'payment_allocations' => ['payment_id', 'payments'],
        'ticket_messages' => ['ticket_id', 'tickets'],
        'work_order_assignments' => ['work_order_id', 'work_orders'],
        'work_order_checklists' => ['work_order_id', 'work_orders'],
        'work_order_media' => ['work_order_id', 'work_orders'],
        'work_order_materials' => ['work_order_id', 'work_orders'],
        'work_order_status_history' => ['work_order_id', 'work_orders'],
        'import_rows' => ['import_batch_id', 'import_batches'],
        'incident_devices' => ['network_incident_id', 'network_incidents'],
        'incident_affected_customers' => ['network_incident_id', 'network_incidents'],
        'incident_updates' => ['network_incident_id', 'network_incidents'],
        'announcement_targets' => ['service_announcement_id', 'service_announcements'],
        'announcement_deliveries' => ['service_announcement_id', 'service_announcements'],
        'reconciliation_items' => ['reconciliation_batch_id', 'reconciliation_batches'],
    ];

    public function backup(Tenant $tenant, string $trigger = 'manual'): BackupJob
    {
        $job = BackupJob::forceCreate(['tenant_id' => $tenant->id, 'trigger' => $trigger, 'status' => 'running', 'started_at' => now(), 'created_by' => Auth::guard('web')->id()]);

        try {
            $tables = [];
            foreach (self::TENANT_TABLES as $t) {
                $tables[$t] = Schema::hasTable($t) ? DB::table($t)->where('tenant_id', $tenant->id)->orderBy(Schema::hasColumn($t, 'id') ? 'id' : 'tenant_id')->get()->map(fn ($r) => (array) $r)->all() : [];
            }
            foreach (self::CHILD_TABLES as $t => [$fk, $parent]) {
                $ids = array_column($tables[$parent] ?? [], 'id');
                $tables[$t] = $ids ? DB::table($t)->whereIn($fk, $ids)->get()->map(fn ($r) => (array) $r)->all() : [];
            }
            $payload = [
                'meta' => [
                    'format' => 'almara-tenant-backup/1', 'tenant_id' => $tenant->id, 'tenant_code' => $tenant->code, 'tenant' => (array) DB::table('tenants')->find($tenant->id),
                    'created_at' => now()->toIso8601String(), 'app_version' => config('almara.version'),
                    'last_migration' => DB::table('migrations')->orderByDesc('id')->value('migration'),
                ],
                'tables' => $tables,
            ];
            $blob = Crypt::encryptString(base64_encode(gzencode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION), 6)));
            $path = 'backups/tenant-'.$tenant->id.'/'.now()->format('Ymd-His').'-'.$job->id.'.almara';
            Storage::disk(config('almara.private_disk'))->put($path, $blob);

            BackupArtifact::forceCreate([
                'backup_job_id' => $job->id, 'tenant_id' => $tenant->id, 'path' => $path, 'size_bytes' => strlen($blob),
                'checksum' => hash('sha256', $blob), 'table_counts' => array_map('count', $tables),
                'expires_at' => now()->addDays((int) config('almara.backup_retention_days', 30)),
            ]);
            $job->forceFill(['status' => 'completed', 'finished_at' => now()])->save();
            $this->log($tenant->id, 'backup.completed', $job->id, $trigger);
        } catch (Throwable $e) {
            $job->forceFill(['status' => 'failed', 'finished_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
            $this->log($tenant->id, 'backup.failed', $job->id, mb_substr($e->getMessage(), 0, 190));
        }

        return $job;
    }

    /** Buka & validasi file backup (checksum, dekripsi, format, isolasi tenant). */
    public function load(BackupArtifact $a): array
    {
        $blob = Storage::disk(config('almara.private_disk'))->get($a->path);
        if ($blob === null) {
            throw new RuntimeException('File backup tidak ditemukan di penyimpanan.');
        }
        if (! hash_equals($a->checksum, hash('sha256', $blob))) {
            throw new RuntimeException('Checksum tidak cocok — file backup rusak atau diubah.');
        }
        $data = json_decode(gzdecode(base64_decode(Crypt::decryptString($blob))), true);
        if (($data['meta']['format'] ?? null) !== 'almara-tenant-backup/1' || (int) $data['meta']['tenant_id'] !== (int) $a->tenant_id) {
            throw new RuntimeException('Format/tenant backup tidak sesuai.');
        }
        foreach (self::TENANT_TABLES as $t) {
            foreach ($data['tables'][$t] ?? [] as $row) {
                if ((int) ($row['tenant_id'] ?? 0) !== (int) $a->tenant_id) {
                    throw new RuntimeException("Pelanggaran isolasi: baris {$t} milik tenant lain ditemukan.");
                }
            }
        }

        return $data;
    }

    /** Uji restore ke database SQLite sementara yang terpisah dari database produksi. */
    public function testRestore(BackupArtifact $a): RestoreJob
    {
        $job = RestoreJob::forceCreate(['tenant_id' => $a->tenant_id, 'backup_artifact_id' => $a->id, 'mode' => 'test', 'status' => 'running', 'requested_by' => Auth::guard('web')->id(), 'started_at' => now()]);
        $file = storage_path('app/private/restore-test-'.$job->id.'.sqlite');
        $report = ['checks' => []];

        try {
            $data = $this->load($a);
            $report['checks'][] = 'Checksum & dekripsi OK, isolasi tenant OK';
            if (! extension_loaded('pdo_sqlite')) {
                throw new RuntimeException('Ekstensi pdo_sqlite tidak tersedia — uji restore terisolasi tidak dapat dijalankan.');
            }
            @mkdir(dirname($file), 0775, true);
            touch($file);
            config(['database.connections.restore_test' => ['driver' => 'sqlite', 'database' => $file, 'prefix' => '', 'foreign_key_constraints' => false]]);
            DB::purge('restore_test');
            $default = DB::getDefaultConnection();
            try {
                Artisan::call('migrate', ['--database' => 'restore_test', '--force' => true]);
            } finally {
                DB::setDefaultConnection($default);
            }
            $conn = DB::connection('restore_test');

            // Data global minimum yang dirujuk tenant
            $tenantRow = $data['meta']['tenant'];
            $conn->table('tenants')->insert($this->only($conn, 'tenants', $tenantRow));
            $planIds = array_unique(array_column($data['tables']['tenant_subscriptions'] ?? [], 'saas_plan_id'));
            foreach (DB::table('saas_plans')->whereIn('id', $planIds)->get() as $p) {
                $conn->table('saas_plans')->insertOrIgnore($this->only($conn, 'saas_plans', (array) $p));
            }

            $counts = [];
            foreach (array_merge(self::TENANT_TABLES, array_keys(self::CHILD_TABLES)) as $t) {
                $rows = $data['tables'][$t] ?? [];
                foreach (array_chunk($rows, 200) as $chunk) {
                    $conn->table($t)->insert(array_map(fn ($r) => $this->only($conn, $t, $r), $chunk));
                }
                $restored = $conn->table($t)->count();
                if ($restored !== count($rows)) {
                    throw new RuntimeException("Jumlah baris {$t} tidak sama ({$restored} vs ".count($rows).').');
                }
                $counts[$t] = $restored;
            }
            $report['checks'][] = 'Semua baris terpulihkan ('.array_sum($counts).' baris)';

            // Rujukan yang tidak ada (mis. ke tenant/pengguna lain) terdeteksi sebagai pelanggaran FK.
            $violations = collect($conn->select('PRAGMA foreign_key_check'))
                ->reject(fn ($v) => in_array($v->parent, ['roles', 'permissions'], true)) // data global sistem
                ->groupBy(fn ($v) => $v->table.' → '.$v->parent)->map->count();
            $report['fk_violations'] = $violations->all();
            $report['counts'] = $counts;
            $ok = $violations->isEmpty();
            $report['checks'][] = $ok ? 'Integritas relasi (foreign key) OK' : 'Ditemukan rujukan tidak valid: '.$violations->keys()->implode(', ');
            $job->forceFill(['status' => $ok ? 'passed' : 'failed', 'report' => $report, 'finished_at' => now()])->save();
        } catch (Throwable $e) {
            $report['error'] = mb_substr($e->getMessage(), 0, 500);
            $job->forceFill(['status' => 'failed', 'report' => $report, 'finished_at' => now()])->save();
        } finally {
            DB::purge('restore_test');
            @unlink($file);
        }
        $this->log($a->tenant_id, 'restore.test_'.$job->status, $job->id);

        return $job;
    }

    /**
     * Pulihkan data tenant dari backup (menimpa data operasional tenant tersebut).
     * Audit log tidak dihapus. Backup pengaman dibuat otomatis sebelum menimpa.
     */
    public function restore(BackupArtifact $a, string $reason): RestoreJob
    {
        if (! RestoreJob::where('backup_artifact_id', $a->id)->where('mode', 'test')->where('status', 'passed')->exists()) {
            throw new InvalidArgumentException('Backup ini belum lulus uji restore. Jalankan uji restore terlebih dahulu.');
        }
        $tenant = Tenant::findOrFail($a->tenant_id);
        $data = $this->load($a);

        $safety = $this->backup($tenant, 'pre_restore');
        if ($safety->status !== 'completed') {
            throw new RuntimeException('Backup pengaman gagal dibuat; restore dibatalkan.');
        }

        $job = RestoreJob::forceCreate(['tenant_id' => $a->tenant_id, 'backup_artifact_id' => $a->id, 'mode' => 'restore', 'status' => 'running', 'reason' => $reason, 'requested_by' => Auth::guard('web')->id(), 'started_at' => now()]);
        $conn = DB::connection();
        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($tenant, $data, $conn) {
                // Hapus anak dulu, lalu tabel tenant (urutan terbalik).
                foreach (self::CHILD_TABLES as $t => [$fk, $parent]) {
                    DB::table($t)->whereIn($fk, DB::table($parent)->where('tenant_id', $tenant->id)->select('id'))->delete();
                }
                foreach (array_reverse(self::TENANT_TABLES) as $t) {
                    DB::table($t)->where('tenant_id', $tenant->id)->delete();
                }
                foreach (array_merge(self::TENANT_TABLES, array_keys(self::CHILD_TABLES)) as $t) {
                    foreach (array_chunk($data['tables'][$t] ?? [], 200) as $chunk) {
                        DB::table($t)->insert(array_map(fn ($r) => $this->only($conn, $t, $r), $chunk));
                    }
                }
            });
        } catch (Throwable $e) {
            Schema::enableForeignKeyConstraints();
            $job->forceFill(['status' => 'failed', 'report' => ['error' => mb_substr($e->getMessage(), 0, 500), 'safety_backup_job' => $safety->id], 'finished_at' => now()])->save();
            $this->log($tenant->id, 'restore.failed', $job->id, $reason);
            throw new RuntimeException('Restore gagal dan dibatalkan (data tidak berubah): '.$e->getMessage());
        }
        Schema::enableForeignKeyConstraints();

        $job->forceFill(['status' => 'completed', 'report' => ['counts' => array_map('count', $data['tables']), 'safety_backup_job' => $safety->id], 'finished_at' => now()])->save();
        $this->log($tenant->id, 'restore.completed', $job->id, $reason);
        AuditLogger::log('tenant.restored', $tenant, null, ['artifact' => $a->id, 'safety_backup_job' => $safety->id], $reason, $tenant->id);

        return $job;
    }

    public function prune(): int
    {
        $n = 0;
        foreach (BackupArtifact::whereNotNull('expires_at')->where('expires_at', '<', now())->get() as $a) {
            Storage::disk(config('almara.private_disk'))->delete($a->path);
            $this->log($a->tenant_id, 'backup.expired', $a->backup_job_id);
            $a->delete();
            $n++;
        }

        return $n;
    }

    /** Ambil hanya kolom yang ada di tabel tujuan (aman bila skema bertambah). */
    protected function only($conn, string $table, array $row): array
    {
        static $cols = [];
        $key = $conn->getName().'.'.$table;
        $cols[$key] ??= array_flip($conn->getSchemaBuilder()->getColumnListing($table));

        return array_intersect_key($row, $cols[$key]);
    }

    protected function log(int $tenantId, string $action, ?int $subject = null, ?string $note = null): void
    {
        BackupAuditLog::create(['tenant_id' => $tenantId, 'action' => $action, 'subject_id' => $subject, 'user_id' => Auth::guard('web')->id(), 'note' => $note]);
    }
}
