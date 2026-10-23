<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventorySerialNumber;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Inventaris perangkat & material: barang, lokasi, saldo, mutasi, serial number.
 * @2026-Alifiandra
 */
class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $items = InventoryItem::with('balances')
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%")))
            ->when($request->query('category'), fn ($w, $c) => $w->where('category', $c))
            ->orderBy('name')->get();
        if ($request->query('low')) {
            $items = $items->filter(fn ($i) => (float) $i->totalStock() <= (float) $i->min_stock);
        }

        return view('isp.inventory.index', [
            'items' => $items,
            'locations' => InventoryLocation::orderBy('type')->orderBy('name')->get(),
            'lowCount' => InventoryItem::with('balances')->where('is_active', true)->get()->filter(fn ($i) => (float) $i->min_stock > 0 && (float) $i->totalStock() <= (float) $i->min_stock)->count(),
        ]);
    }

    public function show(InventoryItem $item)
    {
        return view('isp.inventory.show', [
            'item' => $item->load('balances.location'),
            'movements' => InventoryMovement::with(['from', 'to', 'serial', 'user'])->where('inventory_item_id', $item->id)->latest('id')->paginate(25),
            'serials' => $item->track_serial ? InventorySerialNumber::with(['location', 'service.customer'])->where('inventory_item_id', $item->id)->orderBy('status')->orderBy('serial')->get() : collect(),
            'locations' => InventoryLocation::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('isp.inventory.item-form', ['item' => new InventoryItem(['category' => 'other', 'unit' => 'pcs', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $item = InventoryItem::create($this->itemData($request));
        AuditLogger::log('inventory.item_created', $item, null, $item->only(['sku', 'name', 'category']));

        return redirect()->route('app.inventory.show', $item)->with('success', 'Barang ditambahkan. Catat penerimaan stok untuk mengisi saldo.');
    }

    public function edit(InventoryItem $item)
    {
        return view('isp.inventory.item-form', ['item' => $item]);
    }

    public function update(Request $request, InventoryItem $item)
    {
        $data = $this->itemData($request, $item);
        if ($item->track_serial !== $data['track_serial'] && $item->balances()->where('quantity', '>', 0)->exists()) {
            return back()->with('error', 'Mode serial tidak dapat diubah selama masih ada stok.');
        }
        $original = $item->getOriginal();
        $item->update($data);
        AuditLogger::changes('inventory.item_updated', $item, $original);

        return redirect()->route('app.inventory.show', $item)->with('success', 'Barang diperbarui.');
    }

    public function movement(Request $request, InventoryItem $item, InventoryService $service)
    {
        $tenantId = $this->tenant()->id;
        $loc = Rule::exists('inventory_locations', 'id')->where('tenant_id', $tenantId);
        $data = $request->validate([
            'type' => ['required', 'in:receive,transfer,adjust'],
            'location_id' => ['required', $loc],
            'to_location_id' => ['required_if:type,transfer', 'nullable', $loc],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'serials' => ['nullable', 'string', 'max:20000'],
            'serial_ids' => ['nullable', 'array'],
            'serial_ids.*' => ['integer'],
            'reason' => ['required_if:type,adjust', 'nullable', 'string', 'max:190'],
        ], ['reason.required_if' => 'Alasan wajib diisi untuk penyesuaian stok.']);

        try {
            match ($data['type']) {
                'receive' => $service->receive($item, (int) $data['location_id'], (string) ($data['quantity'] ?? '0'), preg_split('/[\r\n,;]+/', (string) ($data['serials'] ?? '')), $data['reason'] ?? null),
                'transfer' => $service->transfer($item, (int) $data['location_id'], (int) $data['to_location_id'], (string) ($data['quantity'] ?? '0'), $data['serial_ids'] ?? [], $data['reason'] ?? null),
                'adjust' => $service->adjust($item, (int) $data['location_id'], (string) ($data['quantity'] ?? '0'), $data['reason']),
            };
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }

        return back()->with('success', 'Mutasi stok dicatat.');
    }

    public function serialFaulty(Request $request, InventorySerialNumber $serial, InventoryService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:190']]);
        $service->markSerialFaulty($serial, $data['reason']);

        return back()->with('success', 'Serial ditandai rusak.');
    }

    public function locations()
    {
        return view('isp.inventory.locations', [
            'locations' => InventoryLocation::with('holder')->orderBy('type')->orderBy('name')->get(),
            'balances' => InventoryBalance::selectRaw('inventory_location_id, count(*) as items')->where('quantity', '>', 0)->groupBy('inventory_location_id')->pluck('items', 'inventory_location_id'),
            'users' => User::forCurrentTenant()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeLocation(Request $request)
    {
        $tenantId = $this->tenant()->id;
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('inventory_locations', 'code')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(array_keys(InventoryLocation::TYPES))],
            'user_id' => ['nullable', Rule::in(User::forCurrentTenant()->pluck('id')->all())],
        ]);
        $loc = InventoryLocation::create($data + ['is_active' => true]);
        AuditLogger::log('inventory.location_created', $loc, null, $data);

        return back()->with('success', 'Lokasi stok ditambahkan.');
    }

    public function movements(Request $request)
    {
        return view('isp.inventory.movements', [
            'movements' => InventoryMovement::with(['item', 'from', 'to', 'serial', 'user'])
                ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
                ->latest('id')->paginate(30)->withQueryString(),
        ]);
    }

    protected function itemData(Request $request, ?InventoryItem $item = null): array
    {
        $request->merge(['sku' => strtoupper(trim((string) $request->input('sku')))]);
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9\-_.\/]+$/', Rule::unique('inventory_items', 'sku')->where('tenant_id', $this->tenant()->id)->ignore($item?->id)],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(InventoryItem::CATEGORIES))],
            'unit' => ['required', Rule::in(array_keys(InventoryItem::UNITS))],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $data['track_serial'] = $request->boolean('track_serial');
        $data['is_active'] = $request->boolean('is_active');
        $data['min_stock'] ??= 0;

        return $data;
    }
}
