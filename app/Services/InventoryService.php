<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventorySerialNumber;
use App\Models\Tenant;
use App\Support\Money;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Mutasi stok transaksional: setiap perubahan saldo selalu disertai catatan mutasi.
 * Saldo tidak boleh minus. Barang ber-serial dilacak per unit.
 * @2026-Alifiandra
 */
class InventoryService
{
    public function receive(InventoryItem $item, int $locationId, string $qty, array $serials = [], ?string $reason = null): void
    {
        DB::transaction(function () use ($item, $locationId, $qty, $serials, $reason) {
            if ($item->track_serial) {
                $serials = $this->cleanSerials($serials);
                if (! $serials) {
                    throw new InvalidArgumentException('Barang ini dilacak per serial number. Isi daftar serial (satu per baris).');
                }
                $dupe = InventorySerialNumber::whereIn('serial', $serials)->pluck('serial')->all();
                if ($dupe) {
                    throw new InvalidArgumentException('Serial sudah terdaftar: '.implode(', ', $dupe));
                }
                foreach ($serials as $sn) {
                    $s = InventorySerialNumber::create(['inventory_item_id' => $item->id, 'serial' => $sn, 'status' => 'in_stock', 'inventory_location_id' => $locationId]);
                    $this->move('receive', $item, null, $locationId, '1', $s->id, null, $reason);
                }
                $this->adjustBalance($item->id, $locationId, (string) count($serials));
            } else {
                $this->assertPositive($qty);
                $this->move('receive', $item, null, $locationId, $qty, null, null, $reason);
                $this->adjustBalance($item->id, $locationId, $qty);
            }
            AuditLogger::log('inventory.received', $item, null, ['location' => $locationId, 'qty' => $item->track_serial ? count($serials) : $qty, 'reason' => $reason]);
        });
    }

    public function transfer(InventoryItem $item, int $fromId, int $toId, string $qty, array $serialIds = [], ?string $reason = null): void
    {
        if ($fromId === $toId) {
            throw new InvalidArgumentException('Lokasi asal dan tujuan tidak boleh sama.');
        }
        DB::transaction(function () use ($item, $fromId, $toId, $qty, $serialIds, $reason) {
            if ($item->track_serial) {
                $serials = InventorySerialNumber::where('inventory_item_id', $item->id)->whereIn('id', $serialIds)
                    ->where('inventory_location_id', $fromId)->where('status', 'in_stock')->lockForUpdate()->get();
                if ($serials->isEmpty() || $serials->count() !== count(array_unique($serialIds))) {
                    throw new InvalidArgumentException('Pilih serial yang tersedia di lokasi asal.');
                }
                foreach ($serials as $s) {
                    $s->update(['inventory_location_id' => $toId]);
                    $this->move('transfer', $item, $fromId, $toId, '1', $s->id, null, $reason);
                }
                $qty = (string) $serials->count();
            } else {
                $this->assertPositive($qty);
                $this->move('transfer', $item, $fromId, $toId, $qty, null, null, $reason);
            }
            $this->adjustBalance($item->id, $fromId, Money::sub('0', $qty));
            $this->adjustBalance($item->id, $toId, $qty);
            AuditLogger::log('inventory.transferred', $item, null, ['from' => $fromId, 'to' => $toId, 'qty' => $qty]);
        });
    }

    /** Pemakaian material untuk work order. Mengembalikan movement. */
    public function issue(InventoryItem $item, int $locationId, string $qty, ?int $serialId, int $workOrderId, ?int $customerServiceId): InventoryMovement
    {
        return DB::transaction(function () use ($item, $locationId, $qty, $serialId, $workOrderId, $customerServiceId) {
            if ($item->track_serial) {
                $s = InventorySerialNumber::where('inventory_item_id', $item->id)->where('inventory_location_id', $locationId)
                    ->where('status', 'in_stock')->lockForUpdate()->find($serialId);
                if (! $s) {
                    throw new InvalidArgumentException('Serial tidak tersedia di lokasi tersebut.');
                }
                $s->update(['status' => 'installed', 'inventory_location_id' => null, 'customer_service_id' => $customerServiceId]);
                $qty = '1';
            } else {
                $this->assertPositive($qty);
            }
            $this->adjustBalance($item->id, $locationId, Money::sub('0', $qty));

            return $this->move('issue', $item, $locationId, null, $qty, $serialId, $workOrderId, 'Pemakaian work order');
        });
    }

    /** Pengembalian material (mis. salah pakai / pencabutan) ke lokasi. */
    public function returnStock(InventoryItem $item, int $locationId, string $qty, ?int $serialId, ?int $workOrderId, string $reason, bool $faulty = false): void
    {
        DB::transaction(function () use ($item, $locationId, $qty, $serialId, $workOrderId, $reason, $faulty) {
            if ($item->track_serial) {
                $s = InventorySerialNumber::where('inventory_item_id', $item->id)->lockForUpdate()->findOrFail($serialId);
                $s->update(['status' => $faulty ? 'faulty' : 'in_stock', 'inventory_location_id' => $locationId, 'customer_service_id' => null]);
                $qty = '1';
            }
            $this->assertPositive($qty);
            $this->move('return', $item, null, $locationId, $qty, $serialId, $workOrderId, $reason);
            if (! $faulty) {
                $this->adjustBalance($item->id, $locationId, $qty);
            }
        });
    }

    /** Penyesuaian stok (stock opname) — alasan wajib, tercatat sebagai mutasi. */
    public function adjust(InventoryItem $item, int $locationId, string $newQty, string $reason): void
    {
        if ($item->track_serial) {
            throw new InvalidArgumentException('Stok barang ber-serial disesuaikan per unit (tandai rusak/terima serial baru).');
        }
        DB::transaction(function () use ($item, $locationId, $newQty, $reason) {
            $bal = $this->balanceRow($item->id, $locationId);
            $old = (string) $bal->quantity;
            $delta = Money::sub($newQty, $old);
            if (Money::cmp($delta, '0') === 0) {
                return;
            }
            if (Money::cmp($newQty, '0') < 0) {
                throw new InvalidArgumentException('Stok tidak boleh minus.');
            }
            $this->move('adjust', $item, Money::cmp($delta, '0') < 0 ? $locationId : null, Money::cmp($delta, '0') > 0 ? $locationId : null, ltrim($delta, '-'), null, null, $reason);
            $bal->quantity = Money::of($newQty);
            $bal->save();
            AuditLogger::log('inventory.adjusted', $item, ['location' => $locationId, 'qty' => $old], ['location' => $locationId, 'qty' => $newQty], $reason);
        });
    }

    public function markSerialFaulty(InventorySerialNumber $s, string $reason): void
    {
        DB::transaction(function () use ($s, $reason) {
            $s = InventorySerialNumber::lockForUpdate()->findOrFail($s->id);
            if ($s->status === 'in_stock' && $s->inventory_location_id) {
                $this->adjustBalance($s->inventory_item_id, $s->inventory_location_id, '-1');
                $this->move('adjust', InventoryItem::findOrFail($s->inventory_item_id), $s->inventory_location_id, null, '1', $s->id, null, 'Rusak: '.$reason);
            }
            $s->update(['status' => 'faulty']);
            AuditLogger::log('inventory.serial_faulty', $s, null, ['serial' => $s->serial], $reason);
        });
    }

    protected function adjustBalance(int $itemId, int $locationId, string $delta): void
    {
        $bal = $this->balanceRow($itemId, $locationId);
        $new = Money::add($bal->quantity, $delta);
        if (Money::cmp($new, '0') < 0) {
            throw new InvalidArgumentException('Stok tidak mencukupi di lokasi tersebut (tersedia '.InventoryItem::qty($bal->quantity).').');
        }
        $bal->quantity = $new;
        $bal->save();
    }

    protected function balanceRow(int $itemId, int $locationId): InventoryBalance
    {
        $tenantId = app(TenantContext::class)->id();
        DB::table('inventory_balances')->insertOrIgnore(['tenant_id' => $tenantId, 'inventory_item_id' => $itemId, 'inventory_location_id' => $locationId, 'quantity' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return InventoryBalance::where('inventory_item_id', $itemId)->where('inventory_location_id', $locationId)->lockForUpdate()->firstOrFail();
    }

    protected function move(string $type, InventoryItem $item, ?int $from, ?int $to, string $qty, ?int $serialId, ?int $woId, ?string $reason): InventoryMovement
    {
        return InventoryMovement::create([
            'number' => SequenceService::format(app(TenantContext::class)->id(), 'STK', now()->format('Ym')),
            'type' => $type, 'inventory_item_id' => $item->id, 'from_location_id' => $from, 'to_location_id' => $to,
            'quantity' => Money::of($qty), 'inventory_serial_number_id' => $serialId, 'work_order_id' => $woId,
            'reason' => $reason, 'user_id' => Auth::guard('web')->id(),
        ]);
    }

    protected function assertPositive(string $qty): void
    {
        if (! Money::isPositive($qty)) {
            throw new InvalidArgumentException('Jumlah harus lebih dari 0.');
        }
    }

    protected function cleanSerials(array $serials): array
    {
        return array_values(array_unique(array_filter(array_map(fn ($s) => strtoupper(trim($s)), $serials))));
    }
}
