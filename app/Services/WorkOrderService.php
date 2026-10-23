<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\Tenant;
use App\Models\TechnicianAvailability;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Work order: pembuatan, penjadwalan + deteksi benturan, status, checklist, material.
 * @2026-Alifiandra
 */
class WorkOrderService
{
    public function __construct(protected InventoryService $inventory) {}

    public function create(Tenant $tenant, array $data, array $technicianIds): WorkOrder
    {
        return DB::transaction(function () use ($tenant, $data, $technicianIds) {
            $wo = WorkOrder::create($data + [
                'number' => SequenceService::format($tenant->id, 'WO', now($tenant->timezone)->format('Ym')),
                'status' => ! empty($data['scheduled_at']) ? 'scheduled' : 'open',
                'created_by' => Auth::guard('web')->id(),
            ]);
            $this->syncTechnicians($wo, $technicianIds);
            foreach (WorkOrder::DEFAULT_CHECKLIST[$wo->type] ?? [] as $i => $label) {
                $wo->checklist()->create(['label' => $label, 'sort' => $i]);
            }
            $wo->history()->create(['from_status' => null, 'to_status' => $wo->status, 'note' => 'Work order dibuat', 'user_id' => Auth::guard('web')->id()]);
            AuditLogger::log('work_order.created', $wo, null, $wo->only(['number', 'type', 'customer_id', 'scheduled_at']) + ['technicians' => $technicianIds]);

            return $wo;
        });
    }

    public function update(WorkOrder $wo, array $data, array $technicianIds): void
    {
        DB::transaction(function () use ($wo, $data, $technicianIds) {
            $before = $wo->only(['scheduled_at', 'scheduled_end_at', 'priority', 'title']) + ['technicians' => $wo->technicians->pluck('id')->all()];
            $scheduleChanged = ($data['scheduled_at'] ?? null) != optional($wo->scheduled_at)->format('Y-m-d H:i:s');
            $wo->fill($data);
            if ($wo->status === 'open' && $wo->scheduled_at) {
                $wo->status = 'scheduled';
            }
            $wo->save();
            $this->syncTechnicians($wo, $technicianIds);
            if ($scheduleChanged) {
                $wo->history()->create(['from_status' => $wo->status, 'to_status' => $wo->status, 'note' => 'Jadwal diubah ke '.($wo->scheduled_at?->format('d/m/Y H:i') ?? 'belum dijadwalkan'), 'user_id' => Auth::guard('web')->id()]);
            }
            AuditLogger::log('work_order.updated', $wo, $before, $wo->only(['scheduled_at', 'scheduled_end_at', 'priority', 'title']) + ['technicians' => $technicianIds]);
        });
    }

    public function transition(WorkOrder $wo, string $status, ?string $note = null, ?array $location = null): void
    {
        if (! $wo->canTransitionTo($status)) {
            throw new InvalidArgumentException('Perubahan status dari "'.WorkOrder::STATUSES[$wo->status].'" ke "'.WorkOrder::STATUSES[$status].'" tidak diizinkan.');
        }
        if ($status === 'completed') {
            $pending = $wo->checklist()->where('is_done', false)->count();
            if ($pending && ! $note) {
                throw new InvalidArgumentException("Masih ada {$pending} checklist belum selesai. Isi catatan penyelesaian untuk menjelaskan.");
            }
        }
        DB::transaction(function () use ($wo, $status, $note, $location) {
            $from = $wo->status;
            $wo->status = $status;
            if ($status === 'in_progress' && ! $wo->started_at) {
                $wo->started_at = now();
            }
            if ($status === 'completed') {
                $wo->completed_at = now();
                $wo->completion_notes = $note;
                if ($location) {
                    $wo->completion_latitude = $location[0];
                    $wo->completion_longitude = $location[1];
                }
            }
            $wo->save();
            $wo->history()->create(['from_status' => $from, 'to_status' => $status, 'note' => $note, 'user_id' => Auth::guard('web')->id()]);
            AuditLogger::log('work_order.status', $wo, ['status' => $from], ['status' => $status], $note);
        });
    }

    public function useMaterial(WorkOrder $wo, InventoryItem $item, int $locationId, string $qty, ?int $serialId): void
    {
        if (in_array($wo->status, ['completed', 'cancelled'], true)) {
            throw new InvalidArgumentException('Work order sudah ditutup.');
        }
        DB::transaction(function () use ($wo, $item, $locationId, $qty, $serialId) {
            $mv = $this->inventory->issue($item, $locationId, $qty, $serialId, $wo->id, $wo->customer_service_id);
            $wo->materials()->create([
                'inventory_item_id' => $item->id, 'inventory_location_id' => $locationId, 'quantity' => $mv->quantity,
                'inventory_serial_number_id' => $serialId, 'inventory_movement_id' => $mv->id, 'used_by' => Auth::guard('web')->id(),
            ]);
        });
    }

    public function returnMaterial(WorkOrder $wo, \App\Models\WorkOrderMaterial $m, string $reason): void
    {
        if ($m->returned) {
            return;
        }
        DB::transaction(function () use ($wo, $m, $reason) {
            $this->inventory->returnStock($m->item, $m->inventory_location_id, (string) $m->quantity, $m->inventory_serial_number_id, $wo->id, 'Dikembalikan: '.$reason);
            $m->update(['returned' => true]);
        });
    }

    /** Benturan jadwal teknisi (tumpang tindih WO lain / tanggal libur). Mengembalikan pesan peringatan. */
    public function conflicts(array $technicianIds, ?string $start, ?string $end, ?int $exceptId = null): array
    {
        if (! $start || ! $technicianIds) {
            return [];
        }
        $s = Carbon::parse($start);
        $e = $end ? Carbon::parse($end) : $s->copy()->addHours(2);
        $msgs = [];

        $clash = WorkOrder::with('technicians')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->whereNotNull('scheduled_at')
            ->whereHas('technicians', fn ($q) => $q->whereIn('users.id', $technicianIds))
            ->where('scheduled_at', '<', $e)
            ->get()
            ->filter(fn ($w) => ($w->scheduled_end_at ?? $w->scheduled_at->copy()->addHours(2))->gt($s));
        foreach ($clash as $w) {
            $names = $w->technicians->whereIn('id', $technicianIds)->pluck('name')->implode(', ');
            $msgs[] = "Benturan jadwal: {$names} sudah ada {$w->number} pukul {$w->scheduled_at->format('d/m H:i')}.";
        }
        foreach (TechnicianAvailability::with('user')->whereIn('user_id', $technicianIds)->whereDate('date', $s->toDateString())->get() as $a) {
            $msgs[] = "{$a->user->name} tercatat ".strtolower(TechnicianAvailability::STATUSES[$a->status])." pada {$s->format('d/m/Y')}.";
        }

        return $msgs;
    }

    protected function syncTechnicians(WorkOrder $wo, array $ids): void
    {
        $sync = [];
        foreach (array_values(array_unique($ids)) as $i => $id) {
            $sync[$id] = ['role' => $i === 0 ? 'lead' : 'member'];
        }
        $wo->technicians()->sync($sync);
        $wo->unsetRelation('technicians');
    }
}
