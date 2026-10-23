<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\ServiceAnnouncement;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pengumuman massal: preview penerima → kirim (deduplikasi per pelanggan, antrean + rate limit WhatsApp).
 * @2026-Alifiandra
 */
class AnnouncementService
{
    public function __construct(protected NotificationService $notifications, protected IncidentService $incidents) {}

    /** @return Collection<int, Customer> */
    public function recipients(ServiceAnnouncement $a): Collection
    {
        $targets = $a->targets->map(fn ($t) => [$t->target_type, (int) $t->target_id])->all();

        $ids = match ($a->target_mode) {
            'all' => CustomerService::whereIn('status', ['active', 'isolated'])->pluck('customer_id'),
            'incident' => $a->incident ? $a->incident->affectedCustomers()->pluck('customer_id') : collect(),
            'odp', 'odc', 'device' => $this->incidents->resolveCustomers($targets)->pluck('customer_id'),
            'package' => CustomerService::whereIn('status', ['active', 'isolated'])->whereIn('service_package_id', array_column($targets, 1))->pluck('customer_id'),
            default => collect(),
        };

        return Customer::whereIn('id', $ids->unique())->orderBy('name')->get();
    }

    public function send(Tenant $tenant, ServiceAnnouncement $a): int
    {
        if ($a->status !== 'draft') {
            throw new InvalidArgumentException('Pengumuman ini sudah dikirim.');
        }
        $recipients = $this->recipients($a);
        if ($recipients->isEmpty()) {
            throw new InvalidArgumentException('Tidak ada penerima untuk target yang dipilih.');
        }

        return DB::transaction(function () use ($tenant, $a, $recipients) {
            $a->update(['status' => 'sending']);
            foreach ($recipients as $c) {
                $log = null;
                if ($a->send_whatsapp && $c->phone) {
                    $log = $this->notifications->queue($tenant, $c, 'mass_announcement',
                        $this->notifications->baseData($tenant, $c) + ['isi_pengumuman' => $a->title."\n".$a->body],
                        "announcement:{$a->id}:{$c->id}", $a);
                }
                $a->deliveries()->firstOrCreate(['customer_id' => $c->id], [
                    'notification_log_id' => $log?->id,
                    'status' => $log ? 'queued' : ($a->send_whatsapp ? 'skipped' : 'portal_only'),
                ]);
            }
            $a->update(['status' => 'sent', 'sent_at' => now(), 'sent_by' => Auth::guard('web')->id(), 'recipient_count' => $recipients->count()]);
            AuditLogger::log('announcement.sent', $a, null, ['recipients' => $recipients->count(), 'mode' => $a->target_mode]);

            return $recipients->count();
        });
    }
}
