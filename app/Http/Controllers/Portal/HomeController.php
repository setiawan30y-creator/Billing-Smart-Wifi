<?php

namespace App\Http\Controllers\Portal;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Ticket;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class HomeController extends PortalController
{
    public function __invoke(Request $request)
    {
        $tenant = $this->portalTenant($request);
        $customer = $this->customer()->load(['services.package', 'services.odpPort.odp']);
        $open = Invoice::where('customer_id', $customer->id)->whereIn('status', ['unpaid', 'partial'])->orderBy('due_date')->get();

        return view('portal.home', [
            'tenant' => $tenant->load('profile'),
            'customer' => $customer,
            'openInvoices' => $open,
            'outstanding' => Money::add(...$open->map(fn ($i) => $i->balance())->all()),
            'recentPayments' => Payment::where('customer_id', $customer->id)->latest()->limit(5)->get(),
            'openTickets' => Ticket::where('customer_id', $customer->id)->whereNotIn('status', ['resolved', 'closed'])->count(),
            'announcements' => $this->announcements($customer->id),
        ]);
    }

    /** Pengumuman aktif (modul pengumuman massal); aman bila tabel belum ada. */
    protected function announcements(int $customerId)
    {
        if (! Schema::hasTable('service_announcements')) {
            return collect();
        }

        return \App\Models\ServiceAnnouncement::visibleToCustomer($customerId)->latest()->limit(3)->get();
    }
}
