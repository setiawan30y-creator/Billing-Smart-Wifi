<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ImportBatch;
use App\Models\Odp;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Support\Spreadsheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CustomerImportTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatform();
        $this->tenant = $this->makeTenant();
        $this->inTenant($this->tenant, function () {
            ServicePackage::create(['code' => 'HOME10', 'name' => 'Home 10', 'price' => 150000, 'is_active' => true]);
            Odp::create(['code' => 'ODP-1', 'name' => 'A', 'capacity' => 4, 'status' => 'active'])->syncPorts();
        });
    }

    public function test_csv_preview_validation_and_execute(): void
    {
        $csv = "\xEF\xBB\xBFnama;no_whatsapp;alamat;kode_paket;tanggal_mulai;kode_odp;port_odp;status_rumah;nama_pemilik_rumah;kode_pelanggan\r\n"
            ."Pelanggan Satu;81234567890;Jl. A;home10;01/02/2026;ODP-1;1;pribadi;;\r\n"
            ."Pelanggan Dua;081298765432;Jl. B;HOME10;;ODP-1;1;;;\r\n"          // port duplikat
            .";08123;Jl. C;;;;;;;\r\n"                                       // nama kosong, nomor salah
            ."Pelanggan Empat;081255556666;Jl. D;;;;;kontrak;;\r\n"          // pemilik wajib
            ."Pelanggan Lima;081277778888;Jl. E;XYZ;;;;;;KODE-5\r\n"         // paket tidak ada
            ."Pelanggan Enam;081299990000;Jl. F;;;;;;;KODE-6\r\n";

        $user = $this->tenantUser($this->tenant, 'billing');
        $file = UploadedFile::fake()->createWithContent('pelanggan.csv', $csv);
        $this->actingAs($user)->post(route('app.imports.store'), ['file' => $file])->assertRedirect();

        $batch = $this->inTenant($this->tenant, fn () => ImportBatch::firstOrFail());
        $this->assertSame(6, $batch->total_rows);
        $this->assertSame(2, $batch->valid_rows);
        $this->assertSame(4, $batch->error_rows);
        $this->assertSame(0, $this->inTenant($this->tenant, fn () => Customer::count()), 'Preview tidak boleh membuat pelanggan');

        $this->actingAs($user)->get(route('app.imports.show', $batch))->assertOk()->assertSee('duplikat dengan baris 2')->assertSee('Nama pemilik rumah wajib');
        $this->actingAs($user)->get(route('app.imports.errors', $batch))->assertOk();

        $this->actingAs($user)->post(route('app.imports.execute', $batch))->assertRedirect();
        $batch->refresh();
        $this->assertSame('completed', $batch->status);
        $this->assertSame(2, $batch->imported_rows);

        $c = $this->inTenant($this->tenant, fn () => Customer::with('services.odpPort')->where('name', 'Pelanggan Satu')->first());
        $this->assertSame('081234567890', $c->phone);
        $this->assertSame('2026-02-01', $c->services->first()->started_at->toDateString());
        $this->assertSame(1, $c->services->first()->odpPort->port_number);

        // Eksekusi ulang tidak menduplikasi
        $this->actingAs($user)->post(route('app.imports.execute', $batch))->assertSessionHas('error');
        $this->assertSame(2, $this->inTenant($this->tenant, fn () => Customer::count()));
    }

    public function test_xlsx_template_roundtrip_and_permission(): void
    {
        $user = $this->tenantUser($this->tenant);
        $this->actingAs($user)->get(route('app.imports.template'))->assertOk();

        $path = Spreadsheet::writeXlsx([
            ['nama', 'no_whatsapp', 'alamat', 'kode_paket', 'tanggal_mulai'],
            ['Pelanggan Xlsx', '081234560000', 'Jl. X', 'HOME10', '46023'], // serial Excel = 2026-01-01
        ]);
        $file = new UploadedFile($path, 'data.xlsx', null, null, true);
        $this->actingAs($user)->post(route('app.imports.store'), ['file' => $file])->assertRedirect();
        $batch = $this->inTenant($this->tenant, fn () => ImportBatch::latest('id')->first());
        $this->assertSame(1, $batch->valid_rows);
        $this->actingAs($user)->post(route('app.imports.execute', $batch));
        $svc = $this->inTenant($this->tenant, fn () => Customer::where('name', 'Pelanggan Xlsx')->first()->services->first());
        $this->assertSame('2026-01-01', $svc->started_at->toDateString());

        $this->actingAs($this->tenantUser($this->tenant, 'technician'))->get(route('app.imports.index'))->assertForbidden();
    }
}
