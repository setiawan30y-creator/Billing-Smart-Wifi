@extends('layouts.app')
@section('title', 'Invoice manual')
@section('content')
<div class="page-head"><h1>Invoice manual</h1><a href="{{ route('app.invoices.index') }}" class="btn">Kembali</a></div>
<form method="POST" action="{{ route('app.invoices.store') }}" class="card" style="max-width:900px"
  x-data="{ items: {{ \Illuminate\Support\Js::from(old('items', [['description' => '', 'quantity' => 1, 'unit_price' => '']])) }}, discount: '{{ old('discount', 0) }}',
    num(v){ return parseFloat(String(v).replace(/\./g,'').replace(',', '.')) || 0 },
    get total(){ return Math.max(0, this.items.reduce((s,i) => s + this.num(i.unit_price) * (parseFloat(i.quantity)||0), 0) - this.num(this.discount)) } }">
  @csrf
  <div class="card-body form-grid">
    <div class="field"><label class="req">Pelanggan</label><select name="customer_id" class="input" required><option value="">Pilih pelanggan</option>@foreach ($customers as $c)<option value="{{ $c->id }}" @selected(old('customer_id', $selected) == $c->id)>{{ $c->name }} ({{ $c->customer_code }})</option>@endforeach</select></div>
    <div></div>
    <x-field name="issue_date" label="Tanggal terbit" type="date" :value="$today" required/>
    <x-field name="due_date" label="Jatuh tempo" type="date" :value="\Carbon\Carbon::parse($today)->addDays(7)->toDateString()" required/>
  </div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Deskripsi</th><th style="width:100px">Qty</th><th style="width:180px">Harga satuan (Rp)</th><th style="width:40px"></th></tr></thead>
    <tbody>
      <template x-for="(it, idx) in items" :key="idx"><tr>
        <td><input class="input" :name="`items[${idx}][description]`" x-model="it.description" placeholder="mis. Biaya pemasangan" required></td>
        <td><input class="input" type="number" step="0.01" min="0.01" :name="`items[${idx}][quantity]`" x-model="it.quantity" required></td>
        <td><input class="input" :name="`items[${idx}][unit_price]`" x-model="it.unit_price" inputmode="numeric" required></td>
        <td><button type="button" class="btn btn-sm btn-ghost" @click="items.splice(idx,1)" x-show="items.length > 1">✕</button></td>
      </tr></template>
    </tbody></table></div>
  <div class="card-body form-grid">
    <div><button type="button" class="btn btn-sm" @click="items.push({description:'', quantity:1, unit_price:''})">+ Tambah item</button></div>
    <div class="field"><label>Diskon (Rp)</label><input name="discount" class="input" x-model="discount" inputmode="numeric"></div>
    <x-field name="notes" label="Catatan" type="textarea" full rows="2"/>
    <div class="full right"><span class="muted">Perkiraan total:</span> <strong style="font-size:1.2rem" x-text="'Rp' + total.toLocaleString('id-ID')"></strong></div>
  </div>
  <div class="card-body" style="border-top:1px solid var(--border);text-align:right"><button class="btn btn-primary">Buat invoice</button></div>
</form>
@endsection
