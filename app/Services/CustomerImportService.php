<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Odp;
use App\Models\OdpPort;
use App\Models\ServicePackage;
use App\Models\Tenant;
use App\Support\Money;
use App\Support\Spreadsheet;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Import pelanggan dari Excel/CSV:
 * 1) unggah → baca → validasi tiap baris (tanpa menulis data pelanggan),
 * 2) preview + laporan kesalahan per baris,
 * 3) eksekusi eksplisit: hanya baris valid yang diimport; baris error dilaporkan, tidak diam-diam dilewati.
 * Aman diulang: baris yang sudah "imported" tidak diproses lagi.
 * @2026-Alifiandra
 */
class CustomerImportService
{
    public const MAX_ROWS = 5000;

    /** kolom => [label, wajib, contoh] */
    public const COLUMNS = [
        'kode_pelanggan' => ['Kode pelanggan', false, 'PLG-00101'],
        'nama' => ['Nama', true, 'Nama Pelanggan'],
        'no_whatsapp' => ['No. WhatsApp', true, '081234567890'],
        'email' => ['Email', false, ''],
        'alamat' => ['Alamat pemasangan', true, 'Jl. Contoh No. 1'],
        'patokan' => ['Patokan', false, 'Dekat masjid'],
        'latitude' => ['Latitude', false, '-6.2000000'],
        'longitude' => ['Longitude', false, '106.8166667'],
        'status_rumah' => ['Status rumah (pribadi/kontrak)', false, 'pribadi'],
        'nama_pemilik_rumah' => ['Nama pemilik rumah (wajib bila kontrak)', false, ''],
        'kode_paket' => ['Kode paket layanan', false, 'HOME10'],
        'tanggal_mulai' => ['Tanggal mulai layanan (YYYY-MM-DD)', false, '2026-01-01'],
        'tanggal_tagih' => ['Tanggal tagih (1-28)', false, '1'],
        'jatuh_tempo_hari' => ['Jatuh tempo (hari)', false, '10'],
        'harga_khusus' => ['Harga khusus (kosong = harga paket)', false, ''],
        'pppoe_username' => ['PPPoE username', false, ''],
        'kode_odp' => ['Kode ODP', false, ''],
        'port_odp' => ['Port ODP', false, ''],
        'catatan' => ['Catatan', false, ''],
    ];

    protected const ALIASES = [
        'nama_pelanggan' => 'nama', 'whatsapp' => 'no_whatsapp', 'no_hp' => 'no_whatsapp', 'telepon' => 'no_whatsapp', 'hp' => 'no_whatsapp',
        'id_pelanggan' => 'kode_pelanggan', 'paket' => 'kode_paket', 'odp' => 'kode_odp', 'lat' => 'latitude', 'lng' => 'longitude', 'long' => 'longitude',
    ];

    public function templateRows(): array
    {
        return [
            array_keys(self::COLUMNS),
            array_map(fn ($c) => $c[2], array_values(self::COLUMNS)),
        ];
    }

    public function upload(Tenant $tenant, UploadedFile $file): ImportBatch
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $rows = Spreadsheet::read($file->getRealPath(), $ext);

        $rows = array_values(array_filter($rows, fn ($r) => count(array_filter($r, fn ($v) => $v !== '')) > 0));
        if (count($rows) < 2) {
            throw new InvalidArgumentException('File kosong atau hanya berisi judul kolom.');
        }

        $headers = array_map(fn ($h) => $this->normalizeHeader($h), array_shift($rows));
        $missing = array_diff(array_keys(array_filter(self::COLUMNS, fn ($c) => $c[1])), $headers);
        if ($missing) {
            throw new InvalidArgumentException('Kolom wajib tidak ditemukan: '.implode(', ', $missing).'. Gunakan template yang disediakan.');
        }
        if (count($rows) > self::MAX_ROWS) {
            throw new InvalidArgumentException('Maksimal '.self::MAX_ROWS.' baris per file. Pecah file menjadi beberapa bagian.');
        }

        return DB::transaction(function () use ($tenant, $file, $rows, $headers) {
            $batch = ImportBatch::create([
                'type' => 'customers',
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 190),
                'file_path' => $file->store('tenants/'.$tenant->id.'/imports', config('almara.private_disk')),
                'status' => 'validated',
                'created_by' => auth()->id(),
            ]);

            $ctx = $this->context();
            $valid = $errors = 0;
            foreach ($rows as $i => $raw) {
                $assoc = [];
                foreach ($headers as $ci => $h) {
                    if ($h && array_key_exists($h, self::COLUMNS)) {
                        $assoc[$h] = $raw[$ci] ?? '';
                    }
                }
                $ctx['_row'] = $i + 2;
                [$norm, $errs, $warns] = $this->validateRow($assoc, $ctx);
                $ok = ! $errs;
                $ok ? $valid++ : $errors++;

                ImportRow::create([
                    'import_batch_id' => $batch->id,
                    'row_number' => $i + 2, // +1 judul, +1 indeks mulai 1
                    'data' => $assoc,
                    'normalized' => $ok ? $norm : null,
                    'status' => $ok ? 'valid' : 'error',
                    'errors' => $errs ?: null,
                    'warnings' => $warns ?: null,
                ]);
            }

            $batch->update(['total_rows' => count($rows), 'valid_rows' => $valid, 'error_rows' => $errors]);
            AuditLogger::log('import.uploaded', $batch, null, ['file' => $batch->original_name, 'total' => count($rows), 'valid' => $valid, 'error' => $errors]);

            return $batch;
        });
    }

    /** Eksekusi baris valid. Dipanggil dari job antrean. */
    public function execute(ImportBatch $batch): void
    {
        $tenant = Tenant::findOrFail($batch->tenant_id);

        app(TenantContext::class)->run($tenant, function () use ($batch, $tenant) {
            $batch->refresh();
            if (! in_array($batch->status, ['validated', 'processing'], true)) {
                return;
            }
            $batch->update(['status' => 'processing']);
            $ctx = $this->context();

            ImportRow::where('import_batch_id', $batch->id)->where('status', 'valid')->orderBy('id')
                ->chunkById(200, function ($rows) use ($tenant, &$ctx) {
                    foreach ($rows as $row) {
                        $ctx['_row'] = $row->row_number;
                        try {
                            // Validasi ulang terhadap kondisi database terkini (data bisa berubah sejak preview).
                            [$norm, $errs] = $this->validateRow($row->data, $ctx);
                            if ($errs) {
                                $row->update(['status' => 'failed', 'errors' => $errs]);

                                continue;
                            }
                            $customer = DB::transaction(fn () => $this->createFromRow($tenant, $norm));
                            $row->update(['status' => 'imported', 'customer_id' => $customer->id, 'normalized' => $norm]);
                        } catch (Throwable $e) {
                            $row->update(['status' => 'failed', 'errors' => ['Gagal disimpan: '.mb_substr($e->getMessage(), 0, 200)]]);
                        }
                    }
                });

            $imported = ImportRow::where('import_batch_id', $batch->id)->where('status', 'imported')->count();
            $failed = ImportRow::where('import_batch_id', $batch->id)->where('status', 'failed')->count();
            $batch->update(['status' => 'completed', 'imported_rows' => $imported, 'failed_rows' => $failed, 'completed_at' => now()]);
            AuditLogger::log('import.completed', $batch, null, ['imported' => $imported, 'failed' => $failed, 'skipped_errors' => $batch->error_rows], null, $tenant->id);
        });
    }

    protected function createFromRow(Tenant $tenant, array $n): Customer
    {
        $c = new Customer([
            'name' => $n['nama'], 'phone' => $n['no_whatsapp'], 'email' => $n['email'], 'address' => $n['alamat'],
            'landmark' => $n['patokan'], 'latitude' => $n['latitude'], 'longitude' => $n['longitude'],
            'ownership' => $n['status_rumah'], 'owner_name' => $n['nama_pemilik_rumah'], 'status' => 'active', 'notes' => $n['catatan'],
        ]);
        $c->customer_code = $n['kode_pelanggan'] ?: sprintf('PLG-%05d', SequenceService::next($tenant->id, 'customer'));
        $c->save();

        if ($n['service_package_id']) {
            $svc = $c->services()->create([
                'service_package_id' => $n['service_package_id'], 'status' => 'active',
                'price_override' => $n['harga_khusus'], 'billing_day' => $n['tanggal_tagih'], 'due_days' => $n['jatuh_tempo_hari'],
                'pppoe_username' => $n['pppoe_username'], 'started_at' => $n['tanggal_mulai'],
            ]);
            if ($n['odp_port_id']) {
                app(OdpService::class)->assign(OdpPort::findOrFail($n['odp_port_id']), $svc);
            }
        }

        return $c;
    }

    /** Data referensi untuk validasi (sekali per batch). */
    protected function context(): array
    {
        return [
            'packages' => ServicePackage::get(['id', 'code', 'is_active'])->keyBy(fn ($p) => strtoupper($p->code)),
            'odps' => Odp::with('ports')->get()->keyBy(fn ($o) => strtoupper($o->code)),
            'codes' => array_flip(Customer::withTrashed()->pluck('customer_code')->map(fn ($c) => strtoupper($c))->all()),
            'phones' => array_flip(Customer::pluck('phone')->map(fn ($p) => preg_replace('/\D/', '', $p))->all()),
            'seenCodes' => [], 'seenPorts' => [], 'seenPhones' => [],
        ];
    }

    /** @return array{0: array, 1: string[], 2: string[]} [normalized, errors, warnings] */
    public function validateRow(array $r, array &$ctx): array
    {
        $g = fn ($k) => trim((string) ($r[$k] ?? ''));
        $e = [];
        $w = [];
        $n = [];

        $n['nama'] = $g('nama');
        if ($n['nama'] === '') {
            $e[] = 'Nama wajib diisi.';
        } elseif (mb_strlen($n['nama']) > 150) {
            $e[] = 'Nama maksimal 150 karakter.';
        }

        $phone = preg_replace('/[^0-9+]/', '', $g('no_whatsapp'));
        if (preg_match('/^8\d{7,12}$/', $phone)) {
            $phone = '0'.$phone; // Excel sering menghapus angka 0 di depan
        }
        $n['no_whatsapp'] = $phone;
        if ($phone === '') {
            $e[] = 'No. WhatsApp wajib diisi.';
        } elseif (! preg_match('/^(\+62|62|0)8\d{7,12}$/', $phone)) {
            $e[] = "No. WhatsApp \"{$g('no_whatsapp')}\" tidak valid (contoh 081234567890).";
        } else {
            $key = preg_replace('/\D/', '', $phone);
            if (isset($ctx['phones'][$key])) {
                $w[] = 'Nomor WhatsApp sudah dipakai pelanggan lain (tetap diimport).';
            } elseif (isset($ctx['seenPhones'][$key])) {
                $w[] = 'Nomor WhatsApp sama dengan baris '.$ctx['seenPhones'][$key].'.';
            }
        }

        $n['email'] = $g('email') ?: null;
        if ($n['email'] && ! filter_var($n['email'], FILTER_VALIDATE_EMAIL)) {
            $e[] = 'Format email tidak valid.';
        }

        $n['alamat'] = $g('alamat');
        if ($n['alamat'] === '') {
            $e[] = 'Alamat wajib diisi.';
        }
        $n['patokan'] = $g('patokan') ?: null;
        $n['catatan'] = $g('catatan') ?: null;

        $lat = str_replace(',', '.', $g('latitude'));
        $lng = str_replace(',', '.', $g('longitude'));
        $n['latitude'] = $n['longitude'] = null;
        if ($lat !== '' || $lng !== '') {
            if (! is_numeric($lat) || ! is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
                $e[] = 'Latitude/longitude tidak valid (keduanya harus diisi, mis. -6.2 dan 106.8).';
            } else {
                $n['latitude'] = round((float) $lat, 7);
                $n['longitude'] = round((float) $lng, 7);
            }
        }

        $own = strtolower($g('status_rumah'));
        $n['status_rumah'] = in_array($own, ['kontrak', 'sewa', 'rent', 'kos'], true) ? 'rent' : 'own';
        if ($own !== '' && ! in_array($own, ['kontrak', 'sewa', 'rent', 'kos', 'pribadi', 'milik', 'milik pribadi', 'own', 'sendiri'], true)) {
            $e[] = 'Status rumah harus "pribadi" atau "kontrak".';
        }
        $n['nama_pemilik_rumah'] = $g('nama_pemilik_rumah') ?: null;
        if ($n['status_rumah'] === 'rent' && ! $n['nama_pemilik_rumah']) {
            $e[] = 'Nama pemilik rumah wajib bila status rumah kontrak.';
        }

        $code = strtoupper($g('kode_pelanggan'));
        $n['kode_pelanggan'] = $code ?: null;
        if ($code !== '') {
            if (! preg_match('/^[A-Z0-9_\-]{1,30}$/', $code)) {
                $e[] = 'Kode pelanggan hanya huruf, angka, - dan _ (maks 30).';
            } elseif (isset($ctx['codes'][$code])) {
                $e[] = "Kode pelanggan {$code} sudah ada di sistem.";
            } elseif (isset($ctx['seenCodes'][$code])) {
                $e[] = "Kode pelanggan {$code} duplikat dengan baris ".$ctx['seenCodes'][$code].'.';
            }
        }

        // Layanan
        $n['service_package_id'] = null;
        $n['odp_port_id'] = null;
        $n['tanggal_mulai'] = $n['harga_khusus'] = $n['pppoe_username'] = null;
        $n['tanggal_tagih'] = 1;
        $n['jatuh_tempo_hari'] = 10;
        $pkgCode = strtoupper($g('kode_paket'));
        if ($pkgCode !== '') {
            $pkg = $ctx['packages'][$pkgCode] ?? null;
            if (! $pkg) {
                $e[] = "Kode paket {$pkgCode} tidak ditemukan.";
            } elseif (! $pkg->is_active) {
                $e[] = "Paket {$pkgCode} nonaktif.";
            } else {
                $n['service_package_id'] = $pkg->id;
            }

            $start = $g('tanggal_mulai');
            if ($start === '') {
                $n['tanggal_mulai'] = now()->toDateString();
            } elseif ($d = Spreadsheet::excelDate($start)) {
                $n['tanggal_mulai'] = $d;
            } else {
                $parsed = null;
                foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'] as $fmt) {
                    try {
                        $dt = Carbon::createFromFormat('!'.$fmt, $start);
                        if ($dt && $dt->format($fmt) === $start) {
                            $parsed = $dt->toDateString();
                            break;
                        }
                    } catch (Throwable) {
                    }
                }
                $parsed ? $n['tanggal_mulai'] = $parsed : $e[] = 'Tanggal mulai tidak valid (gunakan YYYY-MM-DD atau DD/MM/YYYY).';
            }

            if ($g('tanggal_tagih') !== '') {
                $bd = $g('tanggal_tagih');
                ctype_digit($bd) && (int) $bd >= 1 && (int) $bd <= 28 ? $n['tanggal_tagih'] = (int) $bd : $e[] = 'Tanggal tagih harus 1–28.';
            }
            if ($g('jatuh_tempo_hari') !== '') {
                $dd = $g('jatuh_tempo_hari');
                ctype_digit($dd) && (int) $dd <= 60 ? $n['jatuh_tempo_hari'] = (int) $dd : $e[] = 'Jatuh tempo harus 0–60 hari.';
            }
            if ($g('harga_khusus') !== '') {
                $n['harga_khusus'] = Money::parse($g('harga_khusus'));
            }
            $n['pppoe_username'] = $g('pppoe_username') ?: null;

            $odpCode = strtoupper($g('kode_odp'));
            if ($odpCode !== '') {
                $odp = $ctx['odps'][$odpCode] ?? null;
                $portNo = $g('port_odp');
                if (! $odp) {
                    $e[] = "Kode ODP {$odpCode} tidak ditemukan.";
                } elseif (! ctype_digit($portNo)) {
                    $e[] = 'Port ODP wajib diisi angka bila kode ODP diisi.';
                } else {
                    $port = $odp->ports->firstWhere('port_number', (int) $portNo);
                    $key = $odp->id.':'.(int) $portNo;
                    if (! $port) {
                        $e[] = "ODP {$odpCode} tidak memiliki port {$portNo} (kapasitas {$odp->capacity}).";
                    } elseif ($port->status !== 'available' || $port->customer_service_id) {
                        $e[] = "Port {$portNo} di ODP {$odpCode} sudah terpakai/rusak.";
                    } elseif (isset($ctx['seenPorts'][$key])) {
                        $e[] = "Port {$portNo} di ODP {$odpCode} duplikat dengan baris ".$ctx['seenPorts'][$key].'.';
                    } else {
                        $n['odp_port_id'] = $port->id;
                    }
                }
            }
        } elseif ($g('kode_odp') !== '') {
            $e[] = 'Kode ODP diisi tetapi kode paket kosong.';
        }

        // Tandai yang sudah terlihat di file (untuk deteksi duplikat baris berikutnya).
        $rowNo = $ctx['_row'] ?? null;
        if (! $e) {
            if ($code) {
                $ctx['seenCodes'][$code] = $rowNo ?? 'sebelumnya';
            }
            if ($n['odp_port_id']) {
                $ctx['seenPorts'][$odp->id.':'.(int) $g('port_odp')] = $rowNo ?? 'sebelumnya';
            }
            if ($phone) {
                $ctx['seenPhones'][preg_replace('/\D/', '', $phone)] ??= $rowNo ?? 'sebelumnya';
            }
        }

        return [$n, $e, $w];
    }

    protected function normalizeHeader(string $h): string
    {
        $h = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)));
        $h = preg_replace('/\s*\(.*\)\s*/', '', $h);
        $h = trim(str_replace(['*', '.', '/'], '', $h));
        $h = preg_replace('/[\s\-]+/', '_', $h);

        return self::ALIASES[$h] ?? $h;
    }
}
