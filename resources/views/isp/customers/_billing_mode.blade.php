@php $t = $billingTenant ??= app(\App\Support\TenantContext::class)->tenant(); @endphp
@if ($t->billingModeUniform())
  <div class="field"><label>Mode tagihan</label><input class="input" value="{{ \App\Models\CustomerService::BILLING_MODES[$t->defaultBillingMode()] }} (mengikuti pengaturan ISP)" readonly>
    <div class="hint">Diatur untuk semua pelanggan di @can('settings.manage')<a href="{{ route('app.settings.edit') }}">Pengaturan Umum</a>@else Pengaturan Umum @endcan.</div></div>
@else
  <div class="field"><label class="req">Mode tagihan</label><select name="billing_mode" class="input">@foreach (\App\Models\CustomerService::BILLING_MODES as $k => $l)<option value="{{ $k }}" @selected(($current ?? $t->defaultBillingMode()) === $k)>{{ $l }}</option>@endforeach</select>
    <div class="hint">Prabayar: tagih bulan berjalan. Pascabayar: tagih pemakaian bulan sebelumnya.</div></div>
@endif
