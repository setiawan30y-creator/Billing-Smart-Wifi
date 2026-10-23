@if ($svc && $svc->customer)
  @can('customers.view')<a href="{{ route('app.customers.show', $svc->customer_id) }}">{{ $svc->customer->name }}</a>@else{{ $svc->customer->name }}@endcan
  <div class="small faint">{{ $svc->customer->customer_code }} · {{ $svc->package?->name }}
    @if ($svc->status !== 'active') <x-badge :status="$svc->status" :label="\App\Models\CustomerService::STATUSES[$svc->status] ?? $svc->status"/>@endif</div>
@else
  <span class="faint small">— belum terhubung</span>
@endif
