<?php

namespace App\Http\Controllers\Portal;

use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TicketController extends PortalController
{
    public function index(Request $request)
    {
        return view('portal.tickets.index', [
            'tenant' => $this->portalTenant($request),
            'tickets' => Ticket::where('customer_id', $this->account()->customer_id)->latest()->paginate(15),
        ]);
    }

    public function create(Request $request)
    {
        return view('portal.tickets.create', ['tenant' => $this->portalTenant($request)]);
    }

    public function store(Request $request, string $tenantCode, TicketService $tickets)
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(Ticket::CATEGORIES))],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:3000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
        ], [], ['subject' => 'judul', 'message' => 'pesan']);
        $ticket = $tickets->create($this->portalTenant($request), $data + ['customer_id' => $this->account()->customer_id], 'customer', $this->account()->id, $request->file('attachment'));

        return redirect()->route('portal.tickets.show', [$tenantCode, $ticket->id])->with('success', 'Tiket terkirim. Tim kami akan segera menindaklanjuti.');
    }

    public function show(Request $request, string $tenantCode, int $ticket)
    {
        $t = Ticket::where('customer_id', $this->account()->customer_id)->findOrFail($ticket);

        return view('portal.tickets.show', [
            'tenant' => $this->portalTenant($request),
            'ticket' => $t,
            'messages' => $t->messages()->where('is_internal', false)->get(),
        ]);
    }

    public function reply(Request $request, string $tenantCode, int $ticket, TicketService $tickets)
    {
        $t = Ticket::where('customer_id', $this->account()->customer_id)->findOrFail($ticket);
        abort_if($t->status === 'closed', 403, 'Tiket sudah ditutup.');
        $data = $request->validate(['message' => ['required', 'string', 'max:3000'], 'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096']]);
        $tickets->reply($this->portalTenant($request), $t, 'customer', $this->account()->id, $data['message'], false, $request->file('attachment'));

        return back()->with('success', 'Balasan terkirim.');
    }

    public function attachment(Request $request, string $tenantCode, int $ticket, int $message)
    {
        $t = Ticket::where('customer_id', $this->account()->customer_id)->findOrFail($ticket);
        $m = $t->messages()->where('is_internal', false)->findOrFail($message);
        $disk = Storage::disk(config('almara.private_disk'));
        abort_unless($m->attachment_path && $disk->exists($m->attachment_path), 404);

        return response($disk->get($m->attachment_path), 200, ['Content-Type' => $disk->mimeType($m->attachment_path), 'Cache-Control' => 'no-store, private']);
    }
}
