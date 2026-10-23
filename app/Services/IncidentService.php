<?php

namespace App\Services;

use App\Models\CustomerService;
use App\Models\NetworkIncident;
use App\Models\NetworkStatusCheck;
use App\Models\Odp;
use App\Models\OdpPort;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Support\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Gangguan massal. Pelanggan terdampak diturunkan dari topologi tersimpan
 * (OLT → ODC → ODP → port → layanan), bukan tebakan.
 * @2026-Alifiandra
 */
class IncidentService
{
    /** @param array<int, array{0:string,1:int}> $affected [[type, id], ...] */
    public function create(Tenant $tenant, array $data, array $affected, bool $auto = false, ?string $reason = null): NetworkIncident
    {
        return DB::transaction(function () use ($tenant, $data, $affected, $auto, $reason) {
            $inc = NetworkIncident::create($data + [
                'number' => SequenceService::format($tenant->id, 'INC', now($tenant->timezone)->format('Ym')),
                'status' => 'investigating', 'auto_detected' => $auto, 'detection_reason' => $reason,
                'started_at' => $data['started_at'] ?? now(), 'created_by' => Auth::guard('web')->id(),
            ]);
            $this->setAffected($inc, $affected);
            $inc->updates()->create(['status' => 'investigating', 'note' => $auto ? 'Terdeteksi otomatis: '.$reason : 'Insiden dibuat', 'user_id' => Auth::guard('web')->id()]);
            AuditLogger::log('incident.created', $inc, null, ['number' => $inc->number, 'affected' => $affected, 'auto' => $auto]);

            return $inc;
        });
    }

    public function setAffected(NetworkIncident $inc, array $affected): void
    {
        $inc->devices()->delete();
        $inc->affectedCustomers()->delete();
        foreach ($affected as [$type, $id]) {
            $inc->devices()->firstOrCreate(['affected_type' => $type, 'affected_id' => $id]);
        }
        foreach ($this->resolveCustomers($affected) as $row) {
            $inc->affectedCustomers()->firstOrCreate(['customer_id' => $row['customer_id']], ['customer_service_id' => $row['service_id'], 'via' => $row['via']]);
        }
    }

    /** @return Collection<int, array{customer_id:int, service_id:int, via:string}> */
    public function resolveCustomers(array $affected): Collection
    {
        $odpIds = collect();
        foreach ($affected as [$type, $id]) {
            $odpIds = $odpIds->merge(match ($type) {
                'odp' => [$id],
                'odc' => Odp::where('odc_id', $id)->pluck('id'),
                'device' => Odp::where('network_device_id', $id)->pluck('id')
                    ->merge(Odp::whereIn('odc_id', \App\Models\Odc::where('network_device_id', $id)->pluck('id'))->pluck('id')),
                default => [],
            });
        }
        $odps = Odp::whereIn('id', $odpIds->unique())->get()->keyBy('id');

        return OdpPort::with('service')->whereIn('odp_id', $odps->keys())->where('status', 'used')->get()
            ->filter(fn ($p) => $p->service && $p->service->status !== 'terminated')
            ->map(fn ($p) => ['customer_id' => $p->service->customer_id, 'service_id' => $p->service->id, 'via' => 'ODP '.$odps[$p->odp_id]->code])
            ->unique('customer_id')->values();
    }

    public function addUpdate(NetworkIncident $inc, string $status, string $note, ?string $cause = null, ?string $eta = null): void
    {
        DB::transaction(function () use ($inc, $status, $note, $cause, $eta) {
            $before = $inc->only(['status', 'confirmed_cause']);
            $inc->status = $status;
            if ($cause !== null) {
                $inc->confirmed_cause = $cause ?: null;
            }
            if ($eta !== null) {
                $inc->estimated_recovery_at = $eta ?: null;
            }
            $inc->resolved_at = $status === 'resolved' ? ($inc->resolved_at ?? now()) : null;
            $inc->save();
            $inc->updates()->create(['status' => $status, 'note' => $note, 'user_id' => Auth::guard('web')->id()]);
            AuditLogger::log('incident.updated', $inc, $before, $inc->only(['status', 'confirmed_cause']), $note);
        });
    }

    /**
     * Deteksi kandidat gangguan massal: banyak tiket gangguan dari pelanggan pada ODP yang sama
     * dalam jendela waktu tertentu. Ambang dapat diatur per tenant.
     */
    public function detect(Tenant $tenant): int
    {
        return app(TenantContext::class)->run($tenant, function () use ($tenant) {
            $threshold = (int) $tenant->setting('incident.ticket_threshold', 3);
            $window = (int) $tenant->setting('incident.window_minutes', 60);
            $created = 0;

            $rows = Ticket::where('category', 'gangguan')->whereNotIn('status', ['resolved', 'closed'])
                ->where('created_at', '>=', now()->subMinutes($window))->whereNotNull('customer_id')
                ->get(['id', 'customer_id']);
            if ($rows->isEmpty()) {
                return 0;
            }
            $portByCustomer = OdpPort::with('service')->where('status', 'used')->get()
                ->filter(fn ($p) => $p->service)->mapWithKeys(fn ($p) => [$p->service->customer_id => $p->odp_id]);

            $byOdp = $rows->groupBy(fn ($t) => $portByCustomer[$t->customer_id] ?? null)->forget('');
            foreach ($byOdp as $odpId => $tickets) {
                $count = $tickets->pluck('customer_id')->unique()->count();
                $triggered = $count >= $threshold;
                $existing = \App\Models\IncidentDevice::where('affected_type', 'odp')->where('affected_id', $odpId)
                    ->whereHas('incident', fn ($q) => $q->where('status', '!=', 'resolved'))->exists();

                $incident = null;
                if ($triggered && ! $existing) {
                    $odp = Odp::find($odpId);
                    $incident = $this->create($tenant, [
                        'title' => 'Dugaan gangguan di area ODP '.$odp->code,
                        'description' => "{$count} pelanggan pada ODP {$odp->code} melaporkan gangguan dalam {$window} menit terakhir. Penyebab belum dikonfirmasi.",
                        'severity' => 'major',
                    ], [['odp', (int) $odpId]], true, "{$count} tiket gangguan / {$window} menit (ambang {$threshold})");
                    $created++;
                }
                NetworkStatusCheck::create(['check' => 'ticket_cluster', 'scope_type' => 'odp', 'scope_id' => $odpId, 'observed' => $count, 'threshold' => $threshold, 'triggered' => $triggered, 'network_incident_id' => $incident?->id, 'checked_at' => now()]);
            }

            return $created;
        });
    }
}
