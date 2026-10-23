<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $status = $request->query('status', 'aktif');

        return view('isp.tickets.index', [
            'tickets' => Ticket::with(['customer', 'assignee'])
                ->when($status === 'aktif', fn ($w) => $w->whereNotIn('status', ['resolved', 'closed']))
                ->when($status && $status !== 'aktif' && $status !== 'semua', fn ($w) => $w->where('status', $status))
                ->when($request->query('category'), fn ($w, $c) => $w->where('category', $c))
                ->when($request->query('mine'), fn ($w) => $w->where('assigned_to', \Illuminate\Support\Facades\Auth::guard('web')->id()))
                ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('number', 'like', "%{$q}%")->orWhere('subject', 'like', "%{$q}%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%"))))
                ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")
                ->latest()->paginate(20)->withQueryString(),
            'status' => $status,
            'counts' => Ticket::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function create(Request $request)
    {
        return view('isp.tickets.create', [
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'customer_code']),
            'selected' => $request->integer('customer'),
            'staff' => $this->staff(),
        ]);
    }

    public function store(Request $request, TicketService $tickets)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $this->tenant()->id)],
            'category' => ['required', Rule::in(array_keys(Ticket::CATEGORIES))],
            'priority' => ['required', Rule::in(array_keys(Ticket::PRIORITIES))],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:3000'],
            'assigned_to' => ['nullable', Rule::in($this->staff()->pluck('id')->all())],
        ]);
        $ticket = $tickets->create($this->tenant(), $data, 'user', \Illuminate\Support\Facades\Auth::guard('web')->id());

        return redirect()->route('app.tickets.show', $ticket)->with('success', "Tiket {$ticket->number} dibuat.");
    }

    public function show(Ticket $ticket)
    {
        return view('isp.tickets.show', [
            'ticket' => $ticket->load(['customer.services.package', 'customer.services.odpPort.odp', 'assignee']),
            'messages' => $ticket->messages()->get(),
            'staff' => $this->staff(),
        ]);
    }

    public function reply(Request $request, Ticket $ticket, TicketService $tickets)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:3000'],
            'internal' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(array_keys(Ticket::STATUSES))],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
        ]);
        $tickets->reply($this->tenant(), $ticket, 'user', \Illuminate\Support\Facades\Auth::guard('web')->id(), $data['message'], $request->boolean('internal'), $request->file('attachment'), $data['status'] ?? null);

        return back()->with('success', $request->boolean('internal') ? 'Catatan internal disimpan.' : 'Balasan terkirim ke pelanggan.');
    }

    public function update(Request $request, Ticket $ticket, TicketService $tickets)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Ticket::STATUSES))],
            'priority' => ['required', Rule::in(array_keys(Ticket::PRIORITIES))],
            'assigned_to' => ['nullable', Rule::in($this->staff()->pluck('id')->all())],
        ]);
        $tickets->changeStatus($this->tenant(), $ticket, $data['status'], $data['assigned_to'] ?? null, $data['priority']);

        return back()->with('success', 'Tiket diperbarui.');
    }

    public function attachment(Ticket $ticket, TicketMessage $message)
    {
        abort_unless($message->ticket_id === $ticket->id, 404);
        $disk = Storage::disk(config('almara.private_disk'));
        abort_unless($message->attachment_path && $disk->exists($message->attachment_path), 404);

        return response($disk->get($message->attachment_path), 200, ['Content-Type' => $disk->mimeType($message->attachment_path), 'Cache-Control' => 'no-store, private']);
    }

    protected function staff()
    {
        return User::forCurrentTenant()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }
}
