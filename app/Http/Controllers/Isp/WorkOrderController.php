<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventorySerialNumber;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklist;
use App\Models\WorkOrderMaterial;
use App\Models\WorkOrderMedia;
use App\Services\AuditLogger;
use App\Services\WorkOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Work order teknisi. Teknisi (tanpa izin manage) hanya melihat & mengerjakan WO yang ditugaskan kepadanya.
 * @2026-Alifiandra
 */
class WorkOrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $manage = $user->can('work_orders.manage');
        $status = $request->query('status', 'aktif');

        $wos = WorkOrder::with(['customer', 'technicians'])
            ->when(! $manage || $request->query('mine'), fn ($q) => $q->assignedTo($user->id))
            ->when($status === 'aktif', fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled']))
            ->when($status && ! in_array($status, ['aktif', 'semua'], true), fn ($q) => $q->where('status', $status))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('date'), fn ($q, $d) => $q->whereDate('scheduled_at', $d))
            ->orderByRaw('scheduled_at is null')->orderBy('scheduled_at')->latest('id')
            ->paginate(20)->withQueryString();

        return view('isp.work-orders.index', ['workOrders' => $wos, 'status' => $status, 'manage' => $manage]);
    }

    public function create(Request $request)
    {
        $ticket = $request->integer('ticket') ? Ticket::find($request->integer('ticket')) : null;

        return view('isp.work-orders.form', [
            'wo' => new WorkOrder([
                'type' => $ticket ? 'repair' : 'installation', 'priority' => $ticket?->priority ?? 'normal',
                'customer_id' => $ticket?->customer_id ?? $request->integer('customer') ?: null,
                'ticket_id' => $ticket?->id, 'title' => $ticket?->subject, 'description' => $ticket?->messages()->first()?->body,
            ]),
            'selectedTechs' => [],
        ] + $this->formData());
    }

    public function store(Request $request, WorkOrderService $service)
    {
        [$data, $techs] = $this->validated($request);
        $wo = $service->create($this->tenant(), $data, $techs);
        $warnings = $service->conflicts($techs, $data['scheduled_at'] ?? null, $data['scheduled_end_at'] ?? null, $wo->id);

        return redirect()->route('app.work-orders.show', $wo)->with('success', "Work order {$wo->number} dibuat.")->with('warnings', $warnings);
    }

    public function show(Request $request, WorkOrder $workOrder)
    {
        $this->authorizeView($request, $workOrder);
        $workOrder->load(['customer', 'service.package', 'service.odpPort.odp', 'ticket', 'technicians', 'checklist', 'media', 'materials.item', 'materials.location', 'materials.serial', 'history.user']);

        // Lokasi stok yang boleh dipakai: gudang + stok milik teknisi ini.
        $locations = InventoryLocation::where('is_active', true)
            ->when(! $request->user()->can('inventory.manage'), fn ($q) => $q->where(fn ($w) => $w->where('type', 'warehouse')->orWhere('user_id', $request->user()->id)))
            ->orderBy('type')->orderBy('name')->get();
        $stock = InventoryBalance::with('item')->whereIn('inventory_location_id', $locations->pluck('id'))->where('quantity', '>', 0)->get();
        $serials = InventorySerialNumber::with('item')->where('status', 'in_stock')->whereIn('inventory_location_id', $locations->pluck('id'))->get();

        return view('isp.work-orders.show', [
            'wo' => $workOrder,
            'locations' => $locations,
            'stock' => $stock,
            'serials' => $serials,
            'canWork' => $this->canWork($request, $workOrder),
        ]);
    }

    public function edit(WorkOrder $workOrder)
    {
        return view('isp.work-orders.form', ['wo' => $workOrder, 'selectedTechs' => $workOrder->technicians->pluck('id')->all()] + $this->formData());
    }

    public function update(Request $request, WorkOrder $workOrder, WorkOrderService $service)
    {
        [$data, $techs] = $this->validated($request, $workOrder);
        $service->update($workOrder, $data, $techs);
        $warnings = $service->conflicts($techs, $data['scheduled_at'] ?? null, $data['scheduled_end_at'] ?? null, $workOrder->id);

        return redirect()->route('app.work-orders.show', $workOrder)->with('success', 'Work order diperbarui.')->with('warnings', $warnings);
    }

    public function status(Request $request, WorkOrder $workOrder, WorkOrderService $service)
    {
        $this->authorizeWork($request, $workOrder);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(WorkOrder::STATUSES))],
            'note' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
        if ($data['status'] === 'cancelled' && ! $request->user()->can('work_orders.manage')) {
            abort(403, 'Hanya koordinator yang dapat membatalkan work order.');
        }
        try {
            $service->transition($workOrder, $data['status'], $data['note'] ?? null, isset($data['latitude'], $data['longitude']) ? [$data['latitude'], $data['longitude']] : null);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }

        return back()->with('success', 'Status menjadi '.WorkOrder::STATUSES[$data['status']].'.');
    }

    public function checklist(Request $request, WorkOrder $workOrder, WorkOrderChecklist $item)
    {
        $this->authorizeWork($request, $workOrder);
        abort_unless($item->work_order_id === $workOrder->id, 404);
        abort_if(in_array($workOrder->status, ['completed', 'cancelled'], true), 403, 'Work order sudah ditutup.');
        $done = ! $item->is_done;
        $item->update(['is_done' => $done, 'done_by' => $done ? $request->user()->id : null, 'done_at' => $done ? now() : null]);

        return back();
    }

    public function addChecklist(Request $request, WorkOrder $workOrder)
    {
        $this->authorizeWork($request, $workOrder);
        $data = $request->validate(['label' => ['required', 'string', 'max:190']]);
        $workOrder->checklist()->create(['label' => $data['label'], 'sort' => 99]);

        return back();
    }

    public function media(Request $request, WorkOrder $workOrder)
    {
        $this->authorizeWork($request, $workOrder);
        $data = $request->validate([
            'photos' => ['required', 'array', 'max:10'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'kind' => ['required', 'in:before,after,other'],
            'caption' => ['nullable', 'string', 'max:190'],
        ]);
        foreach ($request->file('photos') as $file) {
            $workOrder->media()->create([
                'path' => $file->store('tenants/'.$workOrder->tenant_id.'/work-orders/'.$workOrder->id, config('almara.private_disk')),
                'kind' => $data['kind'], 'caption' => $data['caption'] ?? null, 'uploaded_by' => $request->user()->id,
            ]);
        }
        AuditLogger::log('work_order.media_uploaded', $workOrder, null, ['count' => count($request->file('photos')), 'kind' => $data['kind']]);

        return back()->with('success', 'Foto diunggah.');
    }

    public function mediaFile(Request $request, WorkOrder $workOrder, WorkOrderMedia $media)
    {
        $this->authorizeView($request, $workOrder);
        abort_unless($media->work_order_id === $workOrder->id, 404);
        $disk = Storage::disk(config('almara.private_disk'));
        abort_unless($disk->exists($media->path), 404);

        return response($disk->get($media->path), 200, ['Content-Type' => $disk->mimeType($media->path), 'Cache-Control' => 'no-store, private']);
    }

    public function material(Request $request, WorkOrder $workOrder, WorkOrderService $service)
    {
        $this->authorizeWork($request, $workOrder);
        $tenantId = $this->tenant()->id;
        $data = $request->validate([
            'inventory_location_id' => ['required', Rule::exists('inventory_locations', 'id')->where('tenant_id', $tenantId)],
            'inventory_item_id' => ['required', Rule::exists('inventory_items', 'id')->where('tenant_id', $tenantId)],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'inventory_serial_number_id' => ['nullable', Rule::exists('inventory_serial_numbers', 'id')->where('tenant_id', $tenantId)],
        ]);
        $loc = InventoryLocation::findOrFail($data['inventory_location_id']);
        if (! $request->user()->can('inventory.manage') && $loc->type !== 'warehouse' && $loc->user_id !== $request->user()->id) {
            abort(403, 'Anda hanya boleh memakai stok gudang atau stok milik sendiri.');
        }
        try {
            $service->useMaterial($workOrder, InventoryItem::findOrFail($data['inventory_item_id']), $loc->id, (string) ($data['quantity'] ?? '1'), $data['inventory_serial_number_id'] ?? null);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e);
        }

        return back()->with('success', 'Material dicatat & stok dikurangi.');
    }

    public function returnMaterial(Request $request, WorkOrder $workOrder, WorkOrderMaterial $material, WorkOrderService $service)
    {
        $this->authorizeWork($request, $workOrder);
        abort_unless($material->work_order_id === $workOrder->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:190']]);
        $service->returnMaterial($workOrder, $material, $data['reason']);

        return back()->with('success', 'Material dikembalikan ke stok.');
    }

    protected function canWork(Request $request, WorkOrder $wo): bool
    {
        $u = $request->user();

        return $u->can('work_orders.manage') || ($u->can('work_orders.update') && $wo->isAssignedTo($u));
    }

    protected function authorizeView(Request $request, WorkOrder $wo): void
    {
        $u = $request->user();
        abort_unless($u->can('work_orders.manage') || $u->can('audit_logs.view') || $wo->isAssignedTo($u), 403, 'Work order ini tidak ditugaskan kepada Anda.');
    }

    protected function authorizeWork(Request $request, WorkOrder $wo): void
    {
        abort_unless($this->canWork($request, $wo), 403, 'Work order ini tidak ditugaskan kepada Anda.');
    }

    protected function formData(): array
    {
        return [
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'customer_code']),
            'services' => CustomerService::with('package')->whereNotIn('status', ['terminated'])->get(['id', 'customer_id', 'service_package_id']),
            'technicians' => User::forCurrentTenant()->where('is_active', true)->whereHas('roles', fn ($q) => $q->whereIn('slug', ['technician', 'noc', 'isp_admin']))->orderBy('name')->get(['id', 'name']),
        ];
    }

    protected function validated(Request $request, ?WorkOrder $wo = null): array
    {
        $tenantId = $this->tenant()->id;
        $techIds = $this->formData()['technicians']->pluck('id')->all();
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(WorkOrder::TYPES))],
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:3000'],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'customer_service_id' => ['nullable', Rule::exists('customer_services', 'id')->where('tenant_id', $tenantId)->where('customer_id', $request->integer('customer_id'))],
            'ticket_id' => ['nullable', Rule::exists('tickets', 'id')->where('tenant_id', $tenantId)],
            'priority' => ['required', Rule::in(array_keys(Ticket::PRIORITIES))],
            'scheduled_at' => ['nullable', 'date'],
            'scheduled_end_at' => ['nullable', 'date', 'after:scheduled_at'],
            'technicians' => ['nullable', 'array'],
            'technicians.*' => ['integer', Rule::in($techIds)],
        ], [], ['scheduled_end_at' => 'selesai', 'customer_service_id' => 'layanan']);
        $techs = array_map('intval', $data['technicians'] ?? []);
        unset($data['technicians']);
        foreach (['scheduled_at', 'scheduled_end_at'] as $k) {
            $data[$k] = ! empty($data[$k]) ? \Carbon\Carbon::parse($data[$k])->format('Y-m-d H:i:s') : null;
        }

        return [$data, $techs];
    }
}
