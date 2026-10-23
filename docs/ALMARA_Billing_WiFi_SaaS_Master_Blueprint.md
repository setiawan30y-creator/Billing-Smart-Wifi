# ALMARA Billing WiFi SaaS

## Master Blueprint --- Versi Konsolidasi

**Status dokumen:** Blueprint produk dan teknis\
**Tanggal pembaruan:** 23 Oktober 2026\
**Status harga:** Usulan awal, belum disahkan sebagai harga komersial
final\
**Status implementasi:** Aplikasi tahap pengembangan (lihat tabel status
di README repositori); belum dipasang di produksi\
**Revisi 23 Oktober 2026:** ditambahkan mode tagihan prabayar/pascabayar
(§6), halaman PPPoE MikroTik dan status online/offline pelanggan di
dashboard (§8.8--8.9), Peta Topologi dengan jalur animasi (§8.10), dan
gaya tabel buku tulis (§10)

------------------------------------------------------------------------

## 1. Ringkasan Produk

ALMARA Billing WiFi SaaS adalah platform multi-tenant untuk membantu
perusahaan Internet Service Provider (ISP) mengelola pelanggan, layanan
internet, penagihan, pembayaran, perangkat jaringan, monitoring,
teknisi, inventaris, notifikasi, dan operasional.

Satu perusahaan ISP terdaftar sebagai satu **tenant**. Satu tenant dapat
mempunyai banyak cabang, router MikroTik, perangkat OLT, teknisi, dan
pelanggan, sesuai paket serta kapasitas sistem.

Platform dibangun sebagai proyek baru dari nol. Blueprint ini **tidak
menginstruksikan untuk menimpa, menghapus, atau langsung menggunakan
database/kode aplikasi lama**. Proyek lama hanya boleh dijadikan
referensi kebutuhan setelah ditinjau secara terpisah.

### Tujuan utama

-   Menyediakan billing dan operasional ISP dalam satu platform.
-   Menyediakan SaaS multi-tenant dengan isolasi data yang ketat.
-   Mengintegrasikan MikroTik, OLT, WhatsApp Gateway, dan Payment
    Gateway yang sudah tersedia setelah API, kredensial, dan
    kemampuannya diverifikasi.
-   Memberikan monitoring trafik dan kualitas jaringan per
    router/perangkat serta per paket atau layanan pelanggan.
-   Menyediakan PWA yang nyaman digunakan di desktop dan ponsel.
-   Menyediakan panel Super Admin untuk mengelola ISP, paket SaaS,
    langganan, kuota, dan operasional platform.

------------------------------------------------------------------------

## 2. Keputusan Teknologi dan Infrastruktur

### 2.1 Stack aplikasi

-   Backend: Laravel 13.
-   Runtime: PHP 8.4+.
-   Database utama: MySQL 8.
-   Frontend: Blade, Livewire, dan Alpine.js.
-   Cache, antrean, dan koordinasi pekerjaan: Redis.
-   PWA: web app installable dengan service worker yang aman.
-   Peta: Leaflet.js dan OpenStreetMap.
-   Integrasi jaringan: MikroTik RouterOS API; SNMP/ICMP bila sesuai;
    adapter vendor untuk OLT/perangkat lain.
-   Job background: Laravel Queue dan Scheduler.
-   Proses worker: Supervisor.
-   Web server: Nginx.
-   OS target produksi: Ubuntu Server 24.04 LTS.
-   TLS: HTTPS untuk seluruh domain aplikasi dan API.

### 2.2 Prinsip arsitektur

1.  Multi-tenant harus menjadi bagian fondasi, bukan tambahan
    belakangan.
2.  Tenant context harus ditentukan di server berdasarkan
    domain/session/token yang tervalidasi, bukan menerima `tenant_id`
    bebas dari client.
3.  Setiap query, API, job, export, upload, peta, notifikasi, dan
    integrasi wajib menerapkan scope tenant.
4.  Super Admin dapat melakukan akses lintas tenant hanya melalui
    permission eksplisit dan audit.
5.  Uang disimpan menggunakan DECIMAL, bukan FLOAT.
6.  Mutasi finansial dan stok menggunakan transaksi database serta
    mekanisme idempotensi yang tepat.
7.  Transaksi keuangan yang sudah diposting dikoreksi melalui
    reversal/adjustment, bukan dihapus diam-diam.
8.  PWA hanya meng-cache aset statis yang aman; status pembayaran dan
    dokumen sensitif tidak dianggap tepercaya saat offline.
9.  Kredensial gateway/router disimpan terenkripsi dan tidak ditulis ke
    log.
10. Semua integrasi eksternal menggunakan adapter supaya pergantian
    vendor tidak mengubah seluruh domain aplikasi.

------------------------------------------------------------------------

## 3. Struktur Peran dan Hak Akses

### 3.1 Peran dasar

-   **Super Admin ALMARA:** mengelola tenant, paket SaaS, langganan,
    fitur global, kebijakan platform, dan akses administratif yang
    diaudit.
-   **Admin ISP:** mengelola operasional tenant sendiri.
-   **NOC / Network Engineer:** mengelola monitoring dan perangkat
    jaringan sesuai permission.
-   **Staf billing/keuangan:** mengelola invoice, pembayaran,
    rekonsiliasi, dan laporan yang diizinkan.
-   **Teknisi:** mengakses work order, jadwal, pelanggan yang
    ditugaskan, checklist, dan dokumentasi pekerjaan.
-   **Petugas kolektor/agen:** mengakses penugasan penagihan dan
    transaksi yang menjadi kewenangannya.
-   **Customer/pelanggan internet:** mengakses portal miliknya sendiri.
-   **Auditor/role baca-saja:** opsional, dengan izin baca terbatas.

Permission harus ditegakkan di backend. Menyembunyikan tombol atau menu
di frontend tidak cukup.

Contoh permission: - `tenants.view`, `tenants.create`, `tenants.update`,
`tenants.suspend` - `saas_plans.manage`, `saas_subscriptions.manage` -
`customers.view`, `customers.create`, `customers.update` -
`invoices.view`, `invoices.create`, `payments.verify` -
`monitoring.view`, `monitoring.devices.manage`,
`monitoring.settings.manage` - `monitoring.alerts.acknowledge`,
`monitoring.reports.export` - `work_orders.manage`, `inventory.manage`,
`audit_logs.view`

------------------------------------------------------------------------

## 4. Super Admin --- Manajemen Supplier ISP

### 4.1 Pengertian

Menu **Super Admin → Manajemen Supplier ISP** dipakai untuk mendaftarkan
perusahaan ISP yang menyewa platform ALMARA. Istilah "supplier ISP"
dalam konteks ini berarti perusahaan ISP pelanggan SaaS, bukan pemasok
bandwidth/upstream. Pemasok bandwidth, bila dibutuhkan, dibuat sebagai
modul terpisah.

### 4.2 Daftar ISP

Halaman daftar menyediakan: - Pencarian nama ISP, kode, email, atau
kontak. - Filter status tenant, paket, dan masa berlangganan. -
Ringkasan jumlah tenant dan status. - Tindakan lihat detail, edit, ubah
paket, kelola domain, suspend/aktifkan sesuai izin, dan arsip sesuai
kebijakan. - Riwayat perubahan dan audit. - Status provisioning yang
terlihat jelas.

### 4.3 Form Tambah ISP

#### A. Profil perusahaan

Field: - Nama perusahaan ISP (wajib). - Kode ISP unik (wajib; boleh
dibuat otomatis). - Nama penanggung jawab/PIC (wajib). - Nomor WhatsApp
(wajib sesuai kebijakan onboarding). - Email perusahaan (wajib). - Nomor
telepon kantor (opsional). - Alamat lengkap (wajib). - Provinsi dan
kota/kabupaten (wajib). - Negara (default Indonesia). - Website
perusahaan (opsional).

Validasi: - Trim dan normalisasi input. - Validasi format email, nomor
kontak, dan URL. - Kode ISP unik dengan unique index di database. -
Peringatan duplikasi nama/email/nomor; aturan penolakan harus
mempertimbangkan kontak yang dipakai bersama secara sah. - Validasi
server adalah sumber kebenaran; validasi browser hanya membantu
pengguna.

#### B. Paket dan langganan SaaS

Field/konfigurasi: - Paket SaaS dari katalog resmi. - Periode
bulanan/tahunan sesuai paket. - Status awal: Draft, Pending Activation,
Trial, atau Active jika kebijakan mengizinkan. - Durasi trial yang
ditetapkan Super Admin. - Kuota pelanggan aktif. - Kuota router
MikroTik. - Fitur yang tersedia berdasarkan paket. - Tanggal mulai,
tanggal akhir, masa tenggang, dan status tagihan dikelola oleh modul
SaaS Billing.

Harga, fitur, dan kuota tidak boleh diambil dari input bebas saat
onboarding; semuanya harus berasal dari katalog paket yang berwenang.
Perubahan paket dan harga untuk langganan yang sedang berjalan mengikuti
tanggal efektif dan kebijakan kontrak.

#### C. Domain dan branding

-   Subdomain tenant unik, jika menggunakan domain platform.
-   Domain custom opsional.
-   Nama aplikasi/brand tenant.
-   Logo PNG/JPEG/WebP dengan validasi MIME, ukuran, dan dimensi.
-   Tema default, Light, Dark, atau tema khusus sesuai paket.
-   Zona waktu default `Asia/Jakarta`.
-   Bahasa default Bahasa Indonesia.

Domain tidak dianggap aktif hanya karena diisi di form. Sistem harus
memverifikasi kepemilikan domain, DNS, dan TLS sebelum menyatakan domain
custom aktif.

#### D. Administrator pertama

-   Nama administrator.
-   Email login.
-   Metode aktivasi: undangan email atau proses manual yang terkontrol.
-   Akun diberi role `isp_admin` dan diikat ke tenant baru.
-   Password tidak dikirim atau disimpan dalam teks biasa.
-   Token aktivasi harus singkat masa berlakunya, sekali pakai, dan
    disimpan dengan aman.
-   Admin ISP tidak boleh memperoleh permission Super Admin.

### 4.4 Alur penyimpanan dan provisioning

1.  Pastikan pengguna memiliki `tenants.create`.
2.  Validasi field, relasi paket, domain, kuota, dan duplikasi di
    backend.
3.  Dalam transaksi database, buat tenant, profil, langganan awal, serta
    audit log secara konsisten.
4.  Buat record provisioning/outbox untuk pekerjaan eksternal.
5.  Queue menyiapkan konfigurasi awal tenant, status domain pending,
    branding, dan undangan administrator.
6.  Gunakan idempotency key/job identity agar retry tidak membuat
    duplikasi.
7.  Jika provisioning gagal, simpan status dan error yang aman; jangan
    hilangkan tenant yang sudah berhasil dibuat.
8.  Sediakan retry yang diaudit.
9.  Tampilkan ringkasan tenant dan status aktivasi kepada Super Admin.

### 4.5 Status tenant

-   `draft`
-   `pending_activation`
-   `provisioning`
-   `trial`
-   `active`
-   `suspended`
-   `provisioning_failed`

Transisi status harus eksplisit dan tervalidasi. Gagal mengirim email
tidak otomatis membatalkan tenant yang sudah tersimpan. Penangguhan
tidak menghapus data tenant.

### 4.6 Tabel inti

-   `tenants`
-   `tenant_profiles`
-   `tenant_domains`
-   `saas_plans`
-   `tenant_subscriptions`
-   `users`
-   `roles`
-   `permissions`
-   `audit_logs`
-   `tenant_provisioning_jobs`

Sebelum migration final, definisikan foreign key, unique index, soft
delete/arsip, kebijakan retensi, tenant scope, dan transisi status.

------------------------------------------------------------------------

## 5. Paket SaaS, Kuota Client, MikroTik, dan Harga Sewa

### 5.1 Prinsip penagihan

Pisahkan dua transaksi: 1. **Sewa ALMARA kepada ISP:** biaya platform
SaaS yang dibayar perusahaan ISP kepada ALMARA. 2. **Tagihan internet
ISP kepada pelanggan:** biaya layanan internet yang ditentukan oleh
masing-masing ISP.

Harga berikut merupakan **usulan awal blueprint, belum harga komersial
final yang disahkan**.

  ------------------------------------------------------------------------
  Paket            Harga usulan per       Client aktif   MikroTik termasuk
                              bulan           termasuk 
  -------------- ------------------ ------------------ -------------------
  Starter                  Rp99.000                100                   3

  Business                Rp199.000                500                  10

  Professional            Rp399.000              1.500                  25

  Enterprise                 Custom     Sesuai kontrak  Target 100+ sesuai
                                                         kapasitas/kontrak
  ------------------------------------------------------------------------

Angka kapasitas merupakan batas paket usulan, bukan jaminan kapasitas
teknis tanpa pengujian beban. Fitur yang disertakan pada setiap paket
harus ditetapkan di katalog; integrasi WhatsApp, Payment Gateway, biaya
vendor, dan infrastruktur pihak ketiga perlu dijelaskan terpisah jika
ada biaya.

### 5.2 Usulan biaya kelebihan kuota

-   Client aktif tambahan: **Rp300/client/bulan** di atas kuota paket.
-   MikroTik tambahan: **Rp15.000/router/bulan** di atas kuota paket.

Contoh paket Starter dengan 150 client aktif dan 3 MikroTik: - Biaya
dasar: Rp99.000. - Tambahan 50 client × Rp300 = Rp15.000. - Total
contoh: Rp114.000/bulan, sebelum pajak atau biaya lain yang berlaku.

Tarif tambahan di atas juga masih usulan dan perlu disetujui sebelum
dipakai untuk tagihan nyata.

### 5.3 Definisi client aktif

Untuk billing SaaS, client aktif dihitung berdasarkan pelanggan yang
mempunyai layanan internet aktif sesuai aturan penghitungan yang
dipublikasikan. Layanan yang sudah berhenti atau diarsipkan tidak
dihitung. Aturan harus menghindari hitung ganda bila satu pelanggan
memiliki beberapa layanan; blueprint perlu memilih apakah kuota dihitung
per pelanggan unik atau per layanan aktif sebelum produksi. Rekomendasi
awal: hitung **pelanggan unik dengan minimal satu layanan aktif**, dan
catat jumlah layanan terpisah untuk analitik.

### 5.4 Kuota dan overage

-   Penggunaan diukur secara periodik dan dicatat dalam
    `saas_usage_records`.
-   Tampilkan kuota, pemakaian, waktu snapshot, tarif, dan biaya
    tambahan di invoice.
-   Beri peringatan sebelum mendekati dan saat melewati kuota.
-   Jangan menghapus pelanggan, router, invoice, atau riwayat karena
    kuota terlampaui.
-   Kebijakan menolak pendaftaran baru atau menawarkan upgrade harus
    transparan, dapat dikonfigurasi, dan tidak memutus layanan pelanggan
    secara otomatis.
-   Perubahan paket tidak boleh diam-diam mengubah kontrak aktif.
-   SaaS Billing harus menggunakan payment gateway yang diverifikasi dan
    rekonsiliasi pembayaran.

### 5.5 Tabel SaaS Billing

-   `saas_plans`
-   `tenant_subscriptions`
-   `saas_invoices`
-   `saas_invoice_items`
-   `saas_payments`
-   `saas_usage_records`

Simpan uang dengan DECIMAL. Pembayaran baru dianggap lunas setelah event
tepercaya diverifikasi atau rekonsiliasi resmi dilakukan.

------------------------------------------------------------------------

## 6. Billing ISP dan Pelanggan

Modul billing tenant mengelola: - Data pelanggan dan layanan internet. -
Paket layanan internet per ISP. - Siklus invoice bulanan. - Item
invoice, pajak/biaya bila relevan, jatuh tempo, pembayaran, kuitansi,
dan riwayat. - Pembayaran online melalui Payment Gateway yang sudah
ada. - Pembayaran manual yang dicatat dengan identitas petugas,
referensi, dan bukti sesuai kebijakan. - Rekonsiliasi pembayaran
otomatis dan manual. - Penyesuaian, refund/reversal sesuai hak akses. -
Laporan piutang, pembayaran, dan closing.

### Otomasi tagihan dan pengingat

Pengingat dapat dikonfigurasi per tenant, misalnya H-7, H-3, H-1, hari
jatuh tempo, H+1, H+3, dan H+7. Sistem harus: - Berhenti mengirim
pengingat setelah pembayaran terverifikasi. - Menggunakan queue, retry,
deduplikasi, dan log pengiriman. - Menyimpan timezone tenant. -
Memastikan scheduler tidak membuat invoice ganda. - Memisahkan status
pesan terkirim, diterima, dan dibaca bila gateway menyediakan status
tersebut.

### Mode tagihan: prabayar dan pascabayar

-   **Prabayar (default):** invoice untuk periode bulan berjalan, terbit
    pada tanggal tagih bulan tersebut; pelanggan membayar di muka.
-   **Pascabayar:** invoice untuk pemakaian bulan sebelumnya, terbit pada
    tanggal tagih bulan penagihan. Layanan pascabayar yang baru mulai
    belum ditagih sampai ada satu periode pemakaian.
-   Pengaturan di **Pengaturan Umum → Billing**:
    -   *Mode tagihan ISP* (Prabayar/Pascabayar).
    -   *Penerapan*: **Semua pelanggan ikut mode ISP** (default,
        disarankan) atau **Boleh berbeda per pelanggan**.
-   Mode seragam: sekali disimpan, seluruh layanan yang tidak berhenti
    ikut berubah; pilihan di detail pelanggan dan kolom import dikunci.
    Jumlah layanan yang berubah ditampilkan dan dicatat di audit log.
-   Mode per pelanggan: mode ISP menjadi default layanan baru; pilihan
    tersedia di form tambah/ubah layanan dan kolom `mode_tagihan` pada
    import.
-   Invoice tetap unik per (layanan, periode pemakaian) sehingga
    peralihan prabayar → pascabayar tidak menagih ganda. Peralihan
    pascabayar → prabayar memunculkan peringatan karena pemakaian bulan
    peralihan perlu ditagih manual bila diperlukan.
-   Deskripsi item invoice menyebut mode dan periode pemakaian.

Isolasi layanan pelanggan karena menunggak hanya boleh dilakukan sesuai
kebijakan tenant yang eksplisit, status overdue yang terverifikasi,
pengecualian, dry-run bila diperlukan, dan audit log. Tidak boleh
berdasarkan webhook tidak tepercaya.

------------------------------------------------------------------------

## 7. Integrasi WhatsApp Gateway dan Payment Gateway

Kedua layanan sudah tersedia sebagai sistem terpisah. ALMARA Billing
WiFi tidak membangun ulang gateway; integrasikan melalui adapter setelah
memverifikasi dokumentasi API, URL, autentikasi, rate limit, callback,
dan lingkungan uji.

### 7.1 WhatsApp

Menu: `Pengaturan → WhatsApp → Template Pesan`.

Template per tenant: - Invoice dibuat. - Pengingat sebelum jatuh
tempo. - Tagihan jatuh tempo. - Tagihan overdue. - Pembayaran diterima
dan kuitansi. - Gangguan layanan. - Aktivasi, penangguhan, dan pemulihan
layanan. - Pembaruan tiket bantuan. - Pengumuman gangguan massal.

Fitur: - Editor template dan placeholder tervalidasi. - Preview pesan. -
Enable/disable per template. - Test send dengan permission. - Log
pengiriman, retry, deduplikasi, dan status delivery. - Rate limit serta
penanganan error gateway.

### 7.2 Payment Gateway

-   Checkout atau payment link sesuai kemampuan gateway.
-   Verifikasi signature callback/webhook.
-   Idempotensi event.
-   Pencocokan invoice, referensi, mata uang, nominal, dan status.
-   Pembayaran parsial, berlebih, pending, gagal, refund, dan unmatched
    masuk alur pengecualian.
-   Jangan tandai invoice lunas hanya berdasarkan redirect browser atau
    callback yang belum diverifikasi.
-   Rekonsiliasi serta laporan yang dapat diaudit.
-   Simpan kredensial terenkripsi dan batasi aksesnya.

Tabel terkait: `payment_transactions`, `payment_allocations`,
`reconciliation_batches`, `reconciliation_items`,
`payment_webhook_events`.

------------------------------------------------------------------------

## 8. Manajemen Trafik dan Kualitas Jaringan

### 8.1 Tujuan dan cakupan

Modul resmi **Manajemen Trafik & Kualitas Jaringan** berfokus pada
monitoring, bukan otomatis mengubah routing atau bandwidth. Monitoring
tersedia: - Per router/perangkat. - Per interface yang didukung. - Per
paket dan layanan pelanggan melalui pemetaan sumber data yang valid. -
Per lokasi/POP/cabang jika topologi telah dimasukkan. - OLT/ODP/ONU jika
API atau protokol perangkat mendukung data yang diperlukan.

### 8.2 Menu

`Monitoring Jaringan`: - Router & Perangkat. - PPPoE MikroTik (secret &
online). - Peta Topologi. - Trafik Real-time. - Kualitas Jaringan. -
Monitoring per Paket. - Monitoring OLT/ODP. - Alarm & Gangguan. -
Laporan & Riwayat. - Pengaturan Monitoring.

### 8.3 Metrik

-   Throughput upload/download.
-   Utilisasi interface.
-   Reachability dan uptime.
-   Latency dan packet loss dari probe.
-   Jitter hanya jika benar-benar diukur.
-   CPU/RAM/interface errors jika perangkat menyediakan.
-   Sinyal optik OLT/ONU hanya jika tersedia dari integrasi vendor.
-   Counter queue/PPPoE/pelanggan bila pemetaan dan kemampuan router
    mendukungnya.

Batas kecepatan paket bukan bukti kecepatan aktual. Sistem harus
menunjukkan sumber data, timestamp, satuan, kesegaran sampel, dan celah
pengukuran. Tidak boleh membuat angka seolah-olah terukur ketika data
tidak tersedia.

### 8.4 Integrasi

-   MikroTik RouterOS API dengan akun read-only bila memungkinkan.
-   SNMP untuk perangkat yang mendukung dan sesuai konfigurasi.
-   ICMP probe untuk reachability, latency, dan packet loss.
-   Adapter OLT/vendor berdasarkan API dan metrik yang benar-benar
    tersedia.
-   Leaflet.js/OpenStreetMap untuk menampilkan perangkat dan insiden
    berdasarkan lokasi tersimpan.
-   Laravel Scheduler, Queue, Redis, dan worker untuk polling serta
    pengolahan sampel.

Frekuensi polling dapat diatur sesuai perangkat dan skala tenant. Sistem
harus membatasi polling berlebihan, memonitor kegagalan collector, dan
memisahkan kegagalan sistem monitoring dari gangguan jaringan.

### 8.5 Klasifikasi Local/Indonesia/Internasional

Kategori trafik dapat dikonfigurasi untuk pelaporan jika tersedia data
routing, prefix, interface, atau klasifikasi lain yang relevan. Sistem
tidak boleh menganggap seluruh IP Indonesia pasti melewati IIX atau
menyatakan jalur trafik tanpa bukti konfigurasi/data yang memadai. Modul
ini tidak mengubah routing otomatis.

### 8.6 Hak akses monitoring

-   Super Admin: pengaturan kemampuan monitoring platform dan status
    collector.
-   Admin ISP: melihat monitoring tenant sendiri dan mengatur target,
    threshold, jadwal, serta laporan.
-   NOC/Network Engineer: menangani metrik dan alarm sesuai permission.
-   Teknisi: melihat status yang dibutuhkan untuk tugasnya.
-   Customer: opsional, hanya melihat status layanan miliknya sendiri.

Permission: - `monitoring.view` - `monitoring.devices.view` -
`monitoring.devices.manage` - `monitoring.settings.manage` -
`monitoring.alerts.view` - `monitoring.alerts.acknowledge` -
`monitoring.reports.export` - `monitoring.customer_metrics.view`

Monitoring read-only secara default. Perubahan router di masa depan
harus menjadi fitur terpisah dengan preview, validasi, konfirmasi
eksplisit, audit, dan rollback.

### 8.7 Tabel monitoring

-   `monitoring_targets`
-   `monitoring_samples`
-   `monitoring_device_mappings`
-   `monitoring_alert_rules`
-   `monitoring_alerts`
-   `monitoring_collection_runs`
-   `monitoring_settings`

Alarm mencakup threshold, durasi kondisi, deduplikasi, severity, waktu
mulai/pulih, acknowledgement, dan audit penanganan.

### 8.8 PPPoE MikroTik --- secret dan sesi online

Halaman **PPPoE MikroTik** membaca langsung dari router melalui RouterOS
API (read-only):

-   Tab **Secret**: `/ppp/secret` --- username, profile, service, remote
    address, status Online/Offline/Dinonaktifkan, waktu terakhir logout,
    alasan putus, dan MAC terakhir.
-   Tab **Online**: `/ppp/active` --- username, IP address, caller ID
    (MAC), uptime, service, dan encoding.
-   Password secret **tidak pernah** diminta dari router (`.proplist`
    tanpa field password) dan tidak disimpan.
-   Setiap username dicocokkan dengan kolom *Username PPPoE* pada layanan
    pelanggan; ditandai bila belum terhubung ke pelanggan dan bila
    layanan **terisolir tetapi masih online**.
-   Pencarian (username, pelanggan, IP, MAC, komentar), filter, pilihan
    router, pagination, cache singkat 30 detik, dan tombol muat ulang.
-   Bila API router tidak terjangkau, tampil pesan error yang jelas dengan
    daftar pemeriksaan; tidak ada angka yang dikarang.
-   Permission: `monitoring.devices.view`. Penambahan, perubahan, atau
    pemutusan secret di masa depan mengikuti aturan perubahan router
    pada §8.6 (izin khusus, konfirmasi, audit).

### 8.9 Status online/offline pelanggan di dashboard

-   Kartu **Status pelanggan PPPoE** di dashboard ISP: jumlah Online,
    Offline, dan Terisolir tetapi online, beserta bar proporsi.
-   Daftar pelanggan offline dan pelanggan terisolir yang masih online,
    dengan tautan ke detail pelanggan.
-   Pelanggan dianggap online bila username PPPoE layanannya memiliki
    sesi aktif di salah satu router MikroTik aktif tenant.
-   Dimuat asinkron setelah dashboard tampil dan diperbarui otomatis
    setiap 60 detik, sehingga router lambat tidak memperlambat dashboard.
-   Bila salah satu router gagal dibaca, angka offline **tidak**
    ditampilkan agar tidak menyesatkan; yang tampil adalah peringatan
    router yang gagal.
-   Menampilkan jumlah layanan aktif yang belum diisi username PPPoE.

### 8.10 Peta Topologi dengan jalur animasi

Halaman **Peta Topologi** (Leaflet.js + OpenStreetMap) menampilkan
hubungan jaringan Router/OLT → ODC → ODP → pelanggan:

-   Titik: Router/OLT, ODC, ODP (warna = okupansi port, angka = port
    terpakai), dan rumah pelanggan.
-   Jalur animasi bergerak dari sumber ke tujuan; jalur backbone
    (OLT/ODC/ODP) lebih tebal daripada jalur drop ke pelanggan.
-   Warna pelanggan dan jalur drop mengikuti status PPPoE: hijau online,
    merah offline, oranye terisolir, biru belum diketahui; diperbarui
    setiap 60 detik.
-   Perangkat, ODC, atau ODP yang terdampak gangguan massal aktif atau
    terdeteksi down oleh monitoring ditandai merah berkedip.
-   Panel: ringkasan jumlah, pencarian titik, lapisan on/off, matikan
    animasi, dan filter hanya pelanggan offline. Animasi otomatis mati
    bila pelanggan lebih dari 1.500 agar browser tetap ringan, dan
    menghormati pengaturan *reduced motion*.
-   Jalur digambar sebagai garis lurus antar titik --- **perkiraan**,
    bukan rute kabel sebenarnya.
-   Titik tanpa koordinat tidak tampil; halaman menyebut jumlah ODP dan
    pelanggan tanpa koordinat serta pelanggan yang belum dipasang ke port
    ODP.
-   Router MikroTik saat ini tampil sebagai titik tanpa jalur karena
    relasi uplink Router → OLT belum dimodelkan (lihat §23).
-   Permission: `network.odp.view`.

------------------------------------------------------------------------

## 9. Deteksi Gangguan Massal dan Pengumuman

### Deteksi gangguan

Tabel: - `network_incidents` - `incident_devices` -
`incident_affected_customers` - `network_status_checks`

Sumber: monitoring, MikroTik, OLT adapter, dan topologi tersimpan. Alarm
gangguan massal memerlukan ambang pemeriksaan yang dapat diatur.
Pelanggan terdampak diturunkan dari pemetaan topologi yang benar, bukan
tebakan. Jangan menyebut kabel putus kecuali ada bukti yang mendukung.

### Pengumuman massal

Tabel: - `service_announcements` - `announcement_targets` -
`announcement_deliveries`

Admin memilih target berdasarkan area, ODP, perangkat, insiden, atau
kelompok pelanggan. Sistem menampilkan preview penerima, melakukan
deduplikasi dan rate limit, serta menyimpan status pengiriman. Pesan
dapat berisi waktu mulai, estimasi pemulihan bila tersedia, pembaruan,
dan penutupan insiden.

------------------------------------------------------------------------

## 10. PWA Customer dan pengalaman UI

Portal pelanggan mendukung: - Ringkasan layanan dan status. - Invoice,
pembayaran, riwayat, dan kuitansi. - Status layanan dan pengumuman. -
Tiket bantuan. - Profil dan preferensi. - Instalasi PWA di ponsel dan
desktop.

UI mengambil inspirasi dari pola pengalaman aplikasi mobile: navigasi
bawah di layar kecil, sidebar di desktop, komponen konsisten, kartu,
transisi ringan, dan layout responsif. Ini tetap aplikasi web PWA, bukan
aplikasi Flutter native.

PWA: - Cache aset statis yang aman saja. - Jangan cache KTP, foto rumah,
dokumen kerja, token, kredensial, atau status pembayaran sensitif. -
Jangan mengizinkan pembayaran atau status invoice dianggap final saat
offline. - Akses data harus tetap melalui autentikasi dan otorisasi
backend.

### Tabel

Semua tabel memakai gaya **buku tulis**: baris rapat dengan tinggi
seragam, garis horizontal biru muda tipis, garis judul kolom lebih tebal,
dan garis margin merah ganda di sisi kiri. Tombol, label status, dan
input di dalam tabel diperkecil; mode gelap memakai warna garis yang
disesuaikan, dan tabel cetak (invoice/kuitansi) ikut lebih rapat.

### Tema dan branding

-   Light, Dark, dan Custom Theme Studio sesuai izin/paket.
-   Pengaturan warna font, background, kartu, sidebar, header, tombol,
    tabel, border, ikon, status, link, dan modal.
-   Tema tenant dipisahkan dari preferensi pribadi pengguna.
-   Validasi warna dan kontras untuk aksesibilitas.
-   Logo tenant digunakan pada portal, invoice, laporan, dan notifikasi
    yang mendukungnya.
-   Semua pengaturan tenant diisolasi.

------------------------------------------------------------------------

## 11. Data Pelanggan dan Instalasi

Form pelanggan dapat mencakup: - Nama, ID pelanggan, WhatsApp/telepon,
email. - Paket layanan. - Alamat pemasangan. - Foto rumah/titik
instalasi. - Link Google Maps, latitude/longitude, dan pin peta. -
Status kepemilikan rumah: pribadi atau kontrak. - Jika kontrak: nama
pemilik rumah wajib. - Patokan rumah. - Pemetaan ODP dan jalur
jaringan. - NIK 16 digit dan nama/alamat sesuai kebutuhan kebijakan
ISP. - Upload KTP **sisi depan saja**; tidak ada field atau upload sisi
belakang. - Penyimpanan file privat, validasi format/ukuran, pembatasan
akses, masking NIK di daftar, audit akses, dan kebijakan retensi.

Pengumpulan KTP harus proporsional dan mengikuti kebijakan hukum/privasi
yang berlaku; opsional kecuali ada alasan dan kebijakan yang sah. Foto
rumah, KTP, foto kerja, dan backup tidak boleh menggunakan URL publik
terbuka.

------------------------------------------------------------------------

## 12. Teknisi, Work Order, dan Kalender

### Work order

Tabel: - `work_orders` - `work_order_assignments` -
`work_order_checklists` - `work_order_media` - `work_order_materials` -
`work_order_status_history`

Mendukung pemasangan baru, perbaikan, kunjungan, dan tindak lanjut
tiket. Teknisi menerima tugas, memperbarui status, mengisi checklist,
waktu/lokasi sesuai izin, foto sebelum/sesudah, dan material yang
digunakan. Data pelanggan dibatasi berdasarkan penugasan dan permission.

### Kalender teknisi

Tabel: - `technician_schedules` - `schedule_assignments` -
`technician_availability` - `schedule_change_logs`

Mendukung penjadwalan, deteksi benturan, perubahan jadwal yang tercatat,
notifikasi, dan navigasi ke lokasi pelanggan. Menggunakan timezone
tenant.

------------------------------------------------------------------------

## 13. Inventaris Perangkat dan Material

Tabel: - `inventory_items` - `inventory_locations` -
`inventory_balances` - `inventory_movements` -
`inventory_serial_numbers` - `stock_adjustments`

Mendukung router, ONT/ONU, kabel, splitter, konektor, perangkat ODP, dan
material lainnya. Penerimaan, perpindahan, penggunaan, pengembalian,
serta penyesuaian stok harus tercatat. Serial number dilacak jika
relevan. Penggunaan material di work order mengurangi stok melalui
transaksi yang konsisten; perubahan stok tidak boleh tanpa catatan.

------------------------------------------------------------------------

## 14. Import Excel/CSV

Tabel: - `import_batches` - `import_rows` - `import_error_logs`

Fitur: - Template yang dapat diunduh. - Preview sebelum import. -
Validasi tipe, field wajib, duplikasi, dan relasi. - Laporan kesalahan
per baris. - Tenant scope dan audit. - Proses besar melalui Queue. -
Tidak ada partial import yang tidak dijelaskan. - Opening balance harus
memiliki akun, tanggal efektif, dan approval sesuai kebijakan; jangan
membuat transaksi historis palsu untuk menyeimbangkan saldo.

------------------------------------------------------------------------

## 15. Rekonsiliasi Pembayaran dan Audit Keuangan

### Rekonsiliasi

Tabel: - `payment_transactions` - `payment_allocations` -
`reconciliation_batches` - `reconciliation_items` -
`payment_webhook_events`

Harus mencocokkan referensi, nominal, mata uang, status, dan invoice.
Mendukung unmatched, partial, overpaid, pending, refund/reversal, dan
transaksi manual. Semua event idempotent. Invoice hanya menjadi lunas
setelah pembayaran terverifikasi.

### Audit keuangan

Tabel: - `audit_logs` - `financial_adjustments` - `accounting_periods` -
`journal_entries` (opsional sesuai lingkup akuntansi)

Catat pelaku, tenant, waktu, tindakan, alasan, serta nilai
sebelum/sesudah yang relevan. Transaksi keuangan yang sudah diposting
tidak boleh dihapus atau diedit tanpa jejak. Koreksi dilakukan melalui
reversal/adjustment. Log dilindungi dari perubahan tidak berwenang dan
mendukung filter/export sesuai permission.

------------------------------------------------------------------------

## 16. Backup dan Restore Tenant

Tabel: - `backup_jobs` - `backup_artifacts` - `restore_jobs` -
`backup_audit_logs`

Persyaratan: - Backup terjadwal, status pekerjaan, retensi, dan
monitoring. - Enkripsi saat penyimpanan dan pemindahan. - Penyimpanan
offsite dan manajemen kunci yang aman. - Restore diuji di lingkungan
terpisah. - Verifikasi isolasi tenant saat restore. - Restore memerlukan
izin/approval dan audit. - Tentukan dan uji target RPO/RTO. - File
backup yang ada bukan bukti restore berhasil.

------------------------------------------------------------------------

## 17. Komisi Kolektor/Agen

Tabel: - `collection_assignments` - `commission_rules` -
`commission_entries` - `commission_settlements`

Komisi tenant-specific dihitung hanya dari pembayaran yang terverifikasi
dan memenuhi aturan. Cegah komisi ganda; koreksi atau reversal dilakukan
jika pembayaran dibalik. Laporan dan penyelesaian komisi dibatasi oleh
permission.

------------------------------------------------------------------------

## 18. Monitoring Kualitas dan Kapasitas Platform

Monitoring jaringan tenant berbeda dari monitoring kesehatan platform
SaaS. Platform juga perlu memantau: - Kesehatan queue dan scheduler. -
Kegagalan collector. - Latensi dan error API. - Pemakaian
CPU/RAM/database. - Retensi sampel monitoring. - Rate limit dan
kegagalan integrasi. - Status backup/restore. - Kapasitas tenant
terhadap paketnya.

Gangguan collector tidak boleh otomatis ditampilkan sebagai gangguan
router.

------------------------------------------------------------------------

## 19. Model Data Inti --- Ringkasan Kelompok

### SaaS dan identitas

`tenants`, `tenant_profiles`, `tenant_domains`, `saas_plans`,
`tenant_subscriptions`, `users`, `roles`, `permissions`, `audit_logs`,
`tenant_provisioning_jobs`.

### Pelanggan dan billing ISP

`customers`, `customer_services`, `service_packages`, `invoices`,
`invoice_items`, `payments`, `payment_allocations`.

`customer_services.billing_mode` (`prepaid` \| `postpaid`, default
`prepaid`); pengaturan tenant `default_billing_mode` dan
`billing_mode_scope` (`all` \| `per_service`).

### SaaS Billing

`saas_invoices`, `saas_invoice_items`, `saas_payments`,
`saas_usage_records`.

### Finansial dan rekonsiliasi

`payment_transactions`, `reconciliation_batches`,
`reconciliation_items`, `financial_adjustments`, `journal_entries`,
`accounting_periods`.

### Jaringan dan GIS

`network_devices`, `olts`, `odcs`, `odps`, `odp_ports`, `network_links`,
`customer_connections`.

Peta Topologi memakai relasi yang ada: `odcs.network_device_id` (OLT),
`odps.odc_id`/`odps.network_device_id`, `odp_ports.customer_service_id`,
dan koordinat pada perangkat, ODC, ODP, serta pelanggan. Data PPP secret
dan sesi aktif dibaca langsung dari router dan tidak disimpan permanen
(hanya cache singkat).

### Monitoring dan insiden

`monitoring_targets`, `monitoring_samples`,
`monitoring_device_mappings`, `monitoring_alert_rules`,
`monitoring_alerts`, `monitoring_collection_runs`,
`monitoring_settings`, `network_incidents`, `incident_devices`,
`incident_affected_customers`, `network_status_checks`.

### Teknisi dan inventaris

`work_orders`, `work_order_assignments`, `work_order_checklists`,
`work_order_media`, `work_order_materials`, `technician_schedules`,
`schedule_assignments`, `inventory_items`, `inventory_locations`,
`inventory_balances`, `inventory_movements`, `inventory_serial_numbers`,
`stock_adjustments`.

### Notifikasi dan import

`notification_templates`, `notification_rules`, `notification_logs`,
`service_announcements`, `announcement_targets`,
`announcement_deliveries`, `import_batches`, `import_rows`,
`import_error_logs`.

### Backup dan komisi

`backup_jobs`, `backup_artifacts`, `restore_jobs`, `backup_audit_logs`,
`collection_assignments`, `commission_rules`, `commission_entries`,
`commission_settlements`.

Daftar ini adalah logical schema. Sebelum migrasi, hilangkan duplikasi
konsep, tetapkan nama final, relasi, foreign key, index, retention, dan
aturan tenant scope.

------------------------------------------------------------------------

## 20. Roadmap Implementasi

### Fase 1 --- Fondasi platform

-   Laravel project baru dan konfigurasi lingkungan.
-   Auth, RBAC, tenant resolution, isolasi data, audit.
-   Manajemen Supplier ISP dan provisioning.
-   Paket SaaS, kuota, dan langganan awal.
-   Backup dasar dan pengujian restore awal.

### Fase 2 --- Pelanggan dan billing

-   Customer, service package, invoice, payment.
-   SaaS Billing dan usage metering.
-   Rekonsiliasi pembayaran.
-   Audit keuangan dan import preview.

### Fase 3 --- Gateway dan notifikasi

-   Adapter WhatsApp Gateway.
-   Template pesan.
-   Payment Gateway dan webhook aman.
-   Scheduler pengingat, queue, retry, delivery logs.

### Fase 4 --- Jaringan dan monitoring

-   Registry router dan perangkat.
-   Integrasi MikroTik read-only.
-   Monitoring throughput/kualitas.
-   Pemetaan pelanggan ke router/queue.
-   OLT/vendor adapters sesuai kemampuan.
-   Alarm, insiden, GIS, dan laporan.
-   Halaman PPPoE MikroTik (secret & online) dan status online/offline
    pelanggan di dashboard.
-   Peta Topologi dengan jalur animasi dan status pelanggan.

### Fase 5 --- Teknisi dan inventaris

-   Work order dan kalender.
-   PWA teknisi.
-   Foto/checklist.
-   Inventaris dan pergerakan stok.

### Fase 6 --- Portal pelanggan

-   Portal PWA.
-   Invoice, pembayaran, kuitansi, tiket.
-   Branding dan Theme Studio.
-   Status layanan dan pengumuman.

### Fase 7 --- Fitur operasional lanjutan

-   Pengumuman gangguan massal.
-   Komisi kolektor.
-   Analitik dan laporan lanjutan.
-   Optimasi biaya serta kapasitas SaaS.

### Fase 8 --- Produksi

-   Uji tenant isolation dan permission.
-   Uji beban untuk jumlah tenant/router/client yang ditargetkan.
-   Uji keamanan webhook dan kredensial.
-   Uji idempotensi dan concurrency invoice/payment.
-   Uji backup/restore dan RPO/RTO.
-   Uji pemulihan kegagalan queue/collector.
-   Dokumentasi operasional dan deployment.

------------------------------------------------------------------------

## 21. Prioritas Fitur

### P0 --- Fondasi wajib

-   Multi-tenancy dan RBAC.
-   Manajemen Supplier ISP/provisioning.
-   Paket SaaS, kuota, dan SaaS Billing.
-   Billing pelanggan dan pembayaran.
-   Rekonsiliasi pembayaran.
-   Audit keuangan.
-   Import data yang tervalidasi.
-   Backup dan restore yang diuji.

### P1 --- Operasional jaringan

-   Monitoring router dan kualitas jaringan.
-   PPPoE MikroTik read-only dan status online/offline pelanggan.
-   Peta Topologi Router/OLT → ODC → ODP → pelanggan.
-   Deteksi gangguan massal.
-   PWA teknisi dan work order.
-   Inventaris.
-   Pengumuman gangguan.
-   Kalender teknisi.
-   Portal pelanggan dasar.

### P2 --- Pengembangan lanjutan

-   Komisi agen/kolektor.
-   Analitik lanjutan dan optimasi.
-   Fitur jaringan tambahan sesuai perangkat serta kebutuhan tenant.

------------------------------------------------------------------------

## 22. Kriteria Kesiapan Produksi

Sebelum produksi, pastikan: - Tidak ada akses silang antar-tenant
melalui UI, API, export, job, atau file. - Hak akses dites di backend. -
Webhook terverifikasi dan idempotent. - Invoice scheduler tidak membuat
duplikat. - Nilai uang dan stok dimutasi secara transaksional. - Data
sensitif privat, terenkripsi sesuai kebutuhan, dan aksesnya diaudit. -
Monitoring menunjukkan data yang benar-benar terukur dan menandai data
basi/hilang. - Backup dapat dipulihkan dan hasilnya diverifikasi. -
Kuota paket dan overage dapat dihitung ulang dan diaudit. - Penangguhan
tenant tidak menghapus data. - Harga yang belum disahkan tidak
diaktifkan untuk penagihan nyata. - Integrasi gateway dan perangkat
sudah diuji pada lingkungan uji sebelum produksi. - Domain, SSL, queue,
scheduler, Redis, Supervisor, dan monitoring platform siap.

------------------------------------------------------------------------

## 23. Keputusan yang Masih Menunggu Persetujuan

1.  Harga komersial final paket SaaS dan biaya overage.
2.  Definisi final kuota client: rekomendasi awal pelanggan unik dengan
    minimal satu layanan aktif.
3.  Fitur yang termasuk di setiap paket dan biaya integrasi pihak
    ketiga.
4.  Kapasitas final router/client per paket setelah uji beban.
5.  Domain produksi, direktori proyek, nama database baru, dan
    konfigurasi deployment.
6.  Dokumentasi/API aktual untuk WhatsApp Gateway, Payment Gateway,
    MikroTik, dan setiap vendor OLT.
7.  Kebijakan retensi data, KTP, foto, backup, dan target RPO/RTO.
8.  Kebijakan masa tenggang, pembatasan tenant, dan penghentian
    langganan.
9.  Relasi uplink Router → OLT/perangkat agar jalur router ikut tergambar
    di Peta Topologi.
10. Kebijakan penagihan masa peralihan pascabayar → prabayar (tagih
    manual, prorata, atau dibebaskan).
11. Apakah penambahan/perubahan/pemutusan PPP secret dari aplikasi
    diaktifkan, beserta role yang berhak dan alur persetujuannya.

------------------------------------------------------------------------

## 24. Prinsip Final

-   Proyek dibangun dari nol dan tidak menimpa aplikasi lama.
-   Semua tenant memiliki isolasi data yang ketat.
-   Harga paket dan overage yang tercantum masih usulan sampai
    disetujui.
-   Satu tenant dapat memiliki banyak MikroTik; kuotanya mengikuti paket
    dan kapasitas yang diuji.
-   Monitoring jaringan read-only secara default.
-   Data yang tidak tersedia tidak boleh direkayasa atau ditampilkan
    seolah-olah terukur.
-   Pembayaran, audit, backup, keamanan, dan tenant isolation adalah
    fondasi, bukan fitur tambahan.
