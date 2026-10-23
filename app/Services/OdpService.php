<?php

namespace App\Services;

use App\Models\CustomerService;
use App\Models\OdpPort;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Penempatan layanan pelanggan pada port ODP (transaksional, satu layanan = satu port).
 * @2026-Alifiandra
 */
class OdpService
{
    public function assign(OdpPort $port, CustomerService $service): void
    {
        DB::transaction(function () use ($port, $service) {
            $port = OdpPort::lockForUpdate()->findOrFail($port->id);
            if ($port->customer_service_id === $service->id) {
                return;
            }
            if ($port->status !== 'available' || $port->customer_service_id) {
                throw new InvalidArgumentException("Port {$port->port_number} tidak tersedia.");
            }
            if ($service->status === 'terminated') {
                throw new InvalidArgumentException('Layanan yang sudah berhenti tidak dapat dipasang ke port.');
            }

            $old = OdpPort::where('customer_service_id', $service->id)->lockForUpdate()->first();
            if ($old) {
                $old->update(['customer_service_id' => null, 'status' => 'available']);
            }

            $port->update(['customer_service_id' => $service->id, 'status' => 'used']);
            AuditLogger::log('odp.port_assigned', $port, $old ? ['port' => $old->label()] : null, [
                'port' => $port->label(), 'customer_service_id' => $service->id, 'customer_id' => $service->customer_id,
            ]);
        });
    }

    public function release(OdpPort $port, ?string $reason = null): void
    {
        DB::transaction(function () use ($port, $reason) {
            $port = OdpPort::lockForUpdate()->findOrFail($port->id);
            if (! $port->customer_service_id) {
                return;
            }
            $before = ['customer_service_id' => $port->customer_service_id];
            $port->update(['customer_service_id' => null, 'status' => 'available']);
            AuditLogger::log('odp.port_released', $port, $before, ['port' => $port->label()], $reason);
        });
    }

    public function setBroken(OdpPort $port, bool $broken, ?string $notes = null): void
    {
        if ($broken && $port->customer_service_id) {
            throw new InvalidArgumentException('Lepaskan pelanggan dari port ini sebelum menandai rusak.');
        }
        $before = $port->only(['status', 'notes']);
        $port->update(['status' => $broken ? 'broken' : 'available', 'notes' => $notes]);
        AuditLogger::log('odp.port_status', $port, $before, $port->only(['status', 'notes']));
    }
}
