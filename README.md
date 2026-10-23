# ALMARA Billing WiFi SaaS

Platform multi-tenant untuk perusahaan ISP: manajemen ISP penyewa (Super Admin), paket SaaS & kuota,
pelanggan internet, invoice otomatis, pembayaran + verifikasi + reversal, notifikasi WhatsApp,
webhook Payment Gateway, registry router/perangkat, audit log, dan PWA.

> Dibangun dari nol sesuai *ALMARA Billing WiFi SaaS — Master Blueprint (23 Okt 2026)*.
> Developer: **@2026-Alifiandra**

---

## Status implementasi (v1.0.0)

| Fase blueprint | Status |
|---|---|
| **Fase 1 — Fondasi**: multi-tenant, RBAC, audit, Manajemen Supplier ISP, provisioning, paket SaaS & langganan | ✅ |
| **Fase 2 — Billing**: pelanggan, paket layanan, invoice, pembayaran, alokasi, reversal, SaaS Billing & usage metering | ✅ |
| **Fase 3 — Gateway & notifikasi**: adapter WhatsApp, template pesan, webhook Payment Gateway (HMAC + idempoten), pengingat H-7…H+7 | ✅ (adapter generik — sesuaikan dengan API gateway Anda) |
| Fase 4 — Monitoring jaringan (RouterOS API, SNMP, ICMP, alarm, GIS) | ⏳ Registry perangkat sudah ada; polling/metrik belum |
| Fase 5–7 — Work order, kalender teknisi, inventaris, portal pelanggan, komisi kolektor, import Excel, backup/restore | ⏳ Belum |

Monitoring **tidak** menampilkan angka apa pun yang belum benar-benar terukur.

## Prinsip yang ditegakkan di kode

- **Isolasi tenant**: semua model tenant memakai global scope (`BelongsToTenant`). `tenant_id` selalu diambil dari user login / konteks job di server — nilai dari form diabaikan. Tanpa konteks tenant, query mengembalikan **nol** baris (fail closed).
- **RBAC di backend**: setiap route dilindungi `can:<permission>`; role sistem: Super Admin, Admin ISP, NOC, Billing, Teknisi, Kolektor, Auditor.
- **Uang = DECIMAL** + aritmetika `brick/math` (tanpa float).
- **Pembayaran**: dicatat → *pending* → diverifikasi (alokasi ke invoice) → bisa *reversal*. Invoice hanya lunas dari pembayaran terverifikasi. Tidak ada hapus transaksi.
- **Idempotensi**: invoice unik per (layanan, periode); `idempotency_key` pada pembayaran; webhook unik per (provider, event_id); provisioning job unik.
- **Harga paket blueprint = usulan**. Paket dengan `is_price_approved = false` menghasilkan invoice SaaS berlabel **SIMULASI** yang tidak bisa dibayar.
- **Data sensitif**: NIK terenkripsi & ter-masking, KTP (sisi depan saja) & foto rumah di disk privat, akses KTP diaudit; kredensial router terenkripsi.
- **Audit log append-only** untuk aksi penting (nilai sebelum/sesudah + alasan).
- **PWA** hanya meng-cache aset statis; halaman data selalu `no-store`.

---

## Instalasi lokal (Laragon, Windows) — PowerShell

Kebutuhan: **PHP 8.3+** (disarankan 8.4), Composer, MySQL 8. Ekstensi PHP: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `intl`, `gd`.

```powershell
cd C:\laragon\www\Billing-Smart-Wifi

# 1. Dependensi
composer install

# 2. Konfigurasi
Copy-Item .env.example .env
php artisan key:generate

# 3. Buat database (MySQL Laragon, user root tanpa password)
mysql -u root -e "CREATE DATABASE IF NOT EXISTS billing_smart_wifi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. (Opsional) atur email & password Super Admin di .env sebelum seeding:
#    SUPERADMIN_EMAIL=... dan SUPERADMIN_PASSWORD=...
#    Bila password dikosongkan, password acak ditampilkan SEKALI di terminal.

# 5. Migrasi + data awal (permission, role, paket SaaS, Super Admin)
php artisan migrate --seed
php artisan storage:link

# 6. (Opsional) data contoh untuk mencoba
php artisan db:seed --class=DemoSeeder

# 7. Jalankan
php artisan serve
```

Buka `http://127.0.0.1:8000` (atau `http://billing-smart-wifi.test` via Laragon — sesuaikan `APP_URL`).

### Alur pertama kali

1. Login sebagai Super Admin → **Manajemen Supplier ISP → Tambah ISP**.
2. Administrator ISP menerima email aktivasi (default `MAIL_MAILER=log` → lihat `storage/logs/laravel.log`).
   Atau buka detail ISP → **Buat link aktivasi** (link sekali pakai, 48 jam).
3. Admin ISP login → buat **Paket Layanan** → tambah **Pelanggan** → **Invoice → Generate** → **Catat & verifikasi pembayaran**.

### Perintah terjadwal

```powershell
php artisan billing:generate-invoices            # invoice bulanan (idempoten)
php artisan billing:send-reminders               # pengingat WhatsApp H-7…H+7
php artisan saas:snapshot-usage                  # snapshot kuota client/router
php artisan schedule:work                        # jalankan scheduler saat development
```

Produksi (Ubuntu 24.04): cron `* * * * * php /path/artisan schedule:run`, `QUEUE_CONNECTION=redis`,
dan Supervisor untuk `php artisan queue:work`.

### Integrasi

- **WhatsApp**: `WHATSAPP_DRIVER=log` (default, hanya dicatat). Untuk gateway Anda: `WHATSAPP_DRIVER=http`,
  `WHATSAPP_HTTP_URL`, `WHATSAPP_HTTP_TOKEN`, nama field via `WHATSAPP_HTTP_NUMBER_FIELD` / `..._MESSAGE_FIELD`.
  Adapter ada di `app/Integrations/WhatsApp`.
- **Payment Gateway**: `POST /webhooks/payment/{provider}` dengan header `X-Signature = HMAC-SHA256(body, PAYMENT_GATEWAY_WEBHOOK_SECRET)`.
  Payload generik: `event_id, tenant_code, invoice_number, amount, currency, status`. Nominal yang tidak cocok masuk status *unmatched* untuk rekonsiliasi manual.
  Sesuaikan `app/Services/PaymentWebhookService.php` setelah dokumentasi gateway diverifikasi.

### Pengujian

```powershell
php artisan test
```

Mencakup isolasi tenant, onboarding ISP & provisioning, aktivasi sekali pakai, transisi status, idempotensi invoice/pembayaran/webhook,
alur partial → lunas → reversal, pembatasan permission, perhitungan overage SaaS, dan render seluruh halaman.

---

## Struktur

```
app/Models/Concerns/BelongsToTenant.php   tenant scope + auto tenant_id
app/Support/TenantContext.php             konteks tenant server-side
app/Support/Permissions.php               katalog permission & role
app/Services/                             Provisioning, Invoice, Payment, SaasBilling, UsageMeter, Notification, Webhook, Audit
app/Http/Controllers/Admin                Super Admin (ISP, paket SaaS, tagihan SaaS, audit)
app/Http/Controllers/Isp                  Area ISP (pelanggan, invoice, pembayaran, perangkat, WhatsApp, pengguna)
resources/views                           Blade + Alpine.js, responsif (sidebar desktop, navigasi bawah di ponsel)
public/sw.js, manifest.webmanifest        PWA
```

## Keputusan yang masih menunggu (dari blueprint §23)

Harga final paket & overage, definisi final kuota client (saat ini: pelanggan unik dengan ≥1 layanan aktif/terisolir),
fitur per paket, dokumentasi API WhatsApp/Payment Gateway/MikroTik/OLT, kebijakan retensi KTP/foto/backup, dan masa tenggang.

---

© 2026 ALMARA Billing WiFi · @2026-Alifiandra
