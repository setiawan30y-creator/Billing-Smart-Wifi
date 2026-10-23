<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Tiket bantuan: dibuat dari portal pelanggan atau oleh staf, percakapan, status, penugasan.
 * @2026-Alifiandra
 */
class TicketService
{
    public function __construct(protected NotificationService $notifications) {}

    public function create(Tenant $tenant, array $data, string $authorType, ?int $authorId, ?UploadedFile $file = null): Ticket
    {
        return DB::transaction(function () use ($tenant, $data, $authorType, $authorId, $file) {
            $ticket = Ticket::create([
                'customer_id' => $data['customer_id'] ?? null,
                'number' => SequenceService::format($tenant->id, 'TKT', now($tenant->timezone)->format('Ym')),
                'subject' => $data['subject'],
                'category' => $data['category'],
                'priority' => $data['priority'] ?? 'normal',
                'status' => 'open',
                'source' => $authorType === 'customer' ? 'portal' : 'staff',
                'assigned_to' => $data['assigned_to'] ?? null,
                'created_by' => $authorType === 'user' ? $authorId : null,
            ]);
            $this->addMessage($ticket, $authorType, $authorId, $data['message'], false, $file);
            AuditLogger::log('ticket.created', $ticket, null, $ticket->only(['number', 'subject', 'category', 'source', 'customer_id']));

            return $ticket;
        });
    }

    public function reply(Tenant $tenant, Ticket $ticket, string $authorType, ?int $authorId, string $body, bool $internal = false, ?UploadedFile $file = null, ?string $newStatus = null): TicketMessage
    {
        $msg = DB::transaction(function () use ($ticket, $authorType, $authorId, $body, $internal, $file, $newStatus) {
            $msg = $this->addMessage($ticket, $authorType, $authorId, $body, $internal, $file);

            if ($authorType === 'user' && ! $internal && ! $ticket->first_response_at) {
                $ticket->first_response_at = now();
            }
            if ($authorType === 'customer' && in_array($ticket->status, ['waiting_customer', 'resolved'], true)) {
                $ticket->status = 'open'; // pelanggan membalas → tiket aktif kembali
                $ticket->resolved_at = null;
            }
            if ($newStatus) {
                $this->applyStatus($ticket, $newStatus);
            } elseif ($authorType === 'user' && ! $internal && $ticket->status === 'open') {
                $ticket->status = 'in_progress';
            }
            $ticket->save();

            return $msg;
        });

        if ($authorType === 'user' && ! $internal) {
            $this->notifyCustomer($tenant, $ticket, 'msg'.$msg->id);
        }

        return $msg;
    }

    public function changeStatus(Tenant $tenant, Ticket $ticket, string $status, ?int $assignedTo, ?string $priority): void
    {
        $before = $ticket->only(['status', 'assigned_to', 'priority']);
        $this->applyStatus($ticket, $status);
        $ticket->assigned_to = $assignedTo;
        if ($priority) {
            $ticket->priority = $priority;
        }
        $ticket->save();
        AuditLogger::log('ticket.updated', $ticket, $before, $ticket->only(['status', 'assigned_to', 'priority']));

        if ($before['status'] !== $status) {
            $this->addMessage($ticket, 'system', null, 'Status diubah menjadi: '.Ticket::STATUSES[$status], false);
            $this->notifyCustomer($tenant, $ticket, 'status-'.$status.'-'.now()->timestamp);
        }
    }

    protected function applyStatus(Ticket $ticket, string $status): void
    {
        $ticket->status = $status;
        $ticket->resolved_at = $status === 'resolved' ? now() : ($status === 'closed' ? ($ticket->resolved_at ?? now()) : null);
        $ticket->closed_at = $status === 'closed' ? now() : null;
    }

    protected function addMessage(Ticket $ticket, string $authorType, ?int $authorId, string $body, bool $internal, ?UploadedFile $file = null): TicketMessage
    {
        return $ticket->messages()->create([
            'author_type' => $authorType,
            'author_id' => $authorId,
            'body' => $body,
            'is_internal' => $internal,
            'attachment_path' => $file?->store('tenants/'.$ticket->tenant_id.'/tickets', config('almara.private_disk')),
        ]);
    }

    protected function notifyCustomer(Tenant $tenant, Ticket $ticket, string $suffix): void
    {
        $customer = $ticket->customer_id ? Customer::find($ticket->customer_id) : null;
        if (! $customer) {
            return;
        }
        $data = $this->notifications->baseData($tenant, $customer) + [
            'nomor_tiket' => $ticket->number,
            'status_tiket' => Ticket::STATUSES[$ticket->status],
            'link_portal' => route('portal.tickets.show', [$tenant->code, $ticket->id]),
        ];
        $this->notifications->queue($tenant, $customer, 'ticket_updated', $data, "ticket_updated:{$ticket->id}:{$suffix}", $ticket);
    }
}
