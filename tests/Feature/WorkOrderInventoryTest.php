<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventorySerialNumber;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class WorkOrderInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        $this->tenant = $this->makeTenant();
    }

    public function test_inventory_receive_transfer_adjust_and_no_negative(): void
    {
        $admin = $this->tenantUser($this->tenant);
        [$gdg, $mobil] = $this->inTenant($this->tenant, fn () => [
            InventoryLocation::create(['code' => 'GDG', 'name' => 'Gudang', 'type' => 'warehouse', 'is_active' => true]),
            InventoryLocation::create(['code' => 'MBL', 'name' => 'Mobil', 'type' => 'vehicle', 'is_active' => true]),
        ]);
        $this->actingAs($admin)->post(route('app.inventory.store'), ['sku' => 'kbl-drop', 'name' => 'Kabel drop', 'category' => 'cable', 'unit' => 'meter', 'is_active' => 1])->assertRedirect();
        $cable = $this->inTenant($this->tenant, fn () => InventoryItem::where('sku', 'KBL-DROP')->first());

        $this->actingAs($admin)->post(route('app.inventory.movement', $cable), ['type' => 'receive', 'location_id' => $gdg->id, 'quantity' => 1000, 'reason' => 'Pembelian'])->assertSessionHas('success');
        $this->actingAs($admin)->post(route('app.inventory.movement', $cable), ['type' => 'transfer', 'location_id' => $gdg->id, 'to_location_id' => $mobil->id, 'quantity' => 300])->assertSessionHas('success');
        $this->actingAs($admin)->post(route('app.inventory.movement', $cable), ['type' => 'transfer', 'location_id' => $mobil->id, 'to_location_id' => $gdg->id, 'quantity' => 500])->assertSessionHas('error');
        $this->actingAs($admin)->post(route('app.inventory.movement', $cable), ['type' => 'adjust', 'location_id' => $gdg->id, 'quantity' => 690])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post(route('app.inventory.movement', $cable), ['type' => 'adjust', 'location_id' => $gdg->id, 'quantity' => 690, 'reason' => 'Opname'])->assertSessionHas('success');

        $bal = fn ($loc) => (string) $this->inTenant($this->tenant, fn () => InventoryBalance::where('inventory_item_id', $cable->id)->where('inventory_location_id', $loc)->value('quantity'));
        $this->assertSame('690.00', $bal($gdg->id));
        $this->assertSame('300.00', $bal($mobil->id));
        $this->assertSame(3, $this->inTenant($this->tenant, fn () => InventoryMovement::count())); // transfer gagal di-rollback

        $this->expectException(\LogicException::class);
        $this->inTenant($this->tenant, fn () => InventoryMovement::first()->update(['quantity' => 1]));
    }

    public function test_work_order_flow_with_technician_and_serial_material(): void
    {
        $admin = $this->tenantUser($this->tenant);
        $tech = $this->tenantUser($this->tenant, 'technician');
        $other = $this->tenantUser($this->tenant, 'technician');

        [$customer, $service, $gdg, $ont] = $this->inTenant($this->tenant, function () use ($tech) {
            $pkg = ServicePackage::create(['code' => 'H', 'name' => 'Home', 'price' => 100000, 'is_active' => true]);
            $c = new Customer(['name' => 'Pelanggan WO', 'phone' => '081234567890', 'address' => 'Jl. X', 'ownership' => 'own', 'status' => 'active']);
            $c->customer_code = 'WO1';
            $c->save();
            $s = $c->services()->create(['service_package_id' => $pkg->id, 'status' => 'active', 'started_at' => '2026-01-01']);
            $g = InventoryLocation::create(['code' => 'GDG', 'name' => 'Gudang', 'type' => 'warehouse', 'is_active' => true]);
            $ont = InventoryItem::create(['sku' => 'ONT', 'name' => 'ONT', 'category' => 'ont', 'unit' => 'pcs', 'track_serial' => true, 'is_active' => true]);

            return [$c, $s, $g, $ont];
        });
        $this->actingAs($admin)->post(route('app.inventory.movement', $ont), ['type' => 'receive', 'location_id' => $gdg->id, 'serials' => "SN001\nSN002"])->assertSessionHas('success');

        $start = now()->addDay()->setTime(9, 0)->format('Y-m-d H:i');
        $this->actingAs($admin)->post(route('app.work-orders.store'), [
            'type' => 'installation', 'title' => 'Pasang baru', 'customer_id' => $customer->id, 'customer_service_id' => $service->id,
            'priority' => 'normal', 'scheduled_at' => $start, 'technicians' => [$tech->id],
        ])->assertRedirect();
        $wo = $this->inTenant($this->tenant, fn () => WorkOrder::first());
        $this->assertSame('scheduled', $wo->status);
        $this->assertGreaterThan(0, $wo->checklist()->count());

        // Benturan jadwal → peringatan
        $this->actingAs($admin)->post(route('app.work-orders.store'), ['type' => 'repair', 'title' => 'Lain', 'priority' => 'normal', 'scheduled_at' => $start, 'technicians' => [$tech->id]])
            ->assertSessionHas('warnings', fn ($w) => str_contains($w[0], 'Benturan jadwal'));

        // Teknisi lain tidak boleh melihat
        $this->actingAs($other)->get(route('app.work-orders.show', $wo))->assertForbidden();
        $this->actingAs($other)->get(route('app.work-orders.index'))->assertOk()->assertDontSee('Pasang baru');

        // Teknisi yang ditugaskan
        $this->actingAs($tech)->get(route('app.work-orders.index'))->assertOk()->assertSee('Pasang baru');
        $this->actingAs($tech)->get(route('app.work-orders.show', $wo))->assertOk();
        $this->actingAs($tech)->post(route('app.work-orders.status', $wo), ['status' => 'in_progress'])->assertSessionHas('success');
        $this->actingAs($tech)->post(route('app.work-orders.status', $wo), ['status' => 'cancelled'])->assertForbidden();

        $sn = $this->inTenant($this->tenant, fn () => InventorySerialNumber::where('serial', 'SN001')->first());
        $this->actingAs($tech)->post(route('app.work-orders.material', $wo), ['inventory_location_id' => $gdg->id, 'inventory_item_id' => $ont->id, 'inventory_serial_number_id' => $sn->id])->assertSessionHas('success');
        $this->assertSame('installed', $sn->fresh()->status);
        $this->assertSame($service->id, $sn->fresh()->customer_service_id);

        $this->actingAs($tech)->post(route('app.work-orders.media', $wo), ['kind' => 'after', 'photos' => [UploadedFile::fake()->image('a.jpg')]])->assertSessionHas('success');

        // Selesai tanpa catatan padahal checklist belum lengkap → ditolak
        $this->actingAs($tech)->post(route('app.work-orders.status', $wo), ['status' => 'completed'])->assertSessionHas('error');
        $this->actingAs($tech)->post(route('app.work-orders.status', $wo), ['status' => 'completed', 'note' => 'Terpasang, redaman -19 dBm', 'latitude' => -6.2, 'longitude' => 106.8])->assertSessionHas('success');
        $this->assertSame('completed', $wo->fresh()->status);

        foreach ([route('app.work-orders.show', $wo), route('app.schedule.index'), route('app.inventory.index'), route('app.inventory.show', $ont), route('app.inventory.locations'), route('app.inventory.movements'), route('app.work-orders.create'), route('app.inventory.create')] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
