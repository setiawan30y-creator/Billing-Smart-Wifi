<?php

/**
 * ALMARA Billing WiFi SaaS — Routes
 * @2026-Alifiandra
 */

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Isp;
use App\Http\Controllers\Portal;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $user = auth()->user();
    if (! $user) {
        return redirect()->route('login');
    }

    return redirect()->route($user->isSuperAdmin() ? 'admin.dashboard' : 'app.dashboard');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/aktivasi/{token}', [ActivationController::class, 'show'])->name('activation.show');
    Route::post('/aktivasi/{token}', [ActivationController::class, 'store'])->middleware('throttle:10,1')->name('activation.store');

    Route::get('/lupa-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/lupa-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::post('/webhooks/payment/{provider}', PaymentWebhookController::class)
    ->where('provider', '[a-z0-9\-]+')->middleware('throttle:120,1')->name('webhooks.payment');

Route::middleware('auth')->group(function () {
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
});

/*
|--------------------------------------------------------------------------
| Super Admin ALMARA
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'superadmin'])->group(function () {
    Route::get('/', Admin\DashboardController::class)->middleware('can:platform.dashboard')->name('dashboard');

    Route::middleware('can:tenants.view')->group(function () {
        Route::get('isp', [Admin\TenantController::class, 'index'])->name('tenants.index');
        Route::get('isp/tambah', [Admin\TenantController::class, 'create'])->middleware('can:tenants.create')->name('tenants.create');
        Route::post('isp', [Admin\TenantController::class, 'store'])->middleware('can:tenants.create')->name('tenants.store');
        Route::get('isp/{tenant}', [Admin\TenantController::class, 'show'])->name('tenants.show');
        Route::get('isp/{tenant}/edit', [Admin\TenantController::class, 'edit'])->middleware('can:tenants.update')->name('tenants.edit');
        Route::put('isp/{tenant}', [Admin\TenantController::class, 'update'])->middleware('can:tenants.update')->name('tenants.update');
        Route::post('isp/{tenant}/status', [Admin\TenantController::class, 'status'])->middleware('can:tenants.suspend')->name('tenants.status');
        Route::post('isp/{tenant}/provisioning/retry', [Admin\TenantController::class, 'retryProvisioning'])->middleware('can:tenants.update')->name('tenants.retry');
        Route::post('isp/{tenant}/aktivasi-admin', [Admin\TenantController::class, 'activationLink'])->middleware('can:tenants.update')->name('tenants.activation');
        Route::post('isp/{tenant}/langganan', [Admin\TenantController::class, 'changePlan'])->middleware('can:saas_subscriptions.manage')->name('tenants.plan');
        Route::post('isp/{tenant}/domain', [Admin\TenantController::class, 'addDomain'])->middleware('can:tenants.update')->name('tenants.domains.store');
        Route::post('isp/{tenant}/domain/{domain}/verifikasi', [Admin\TenantController::class, 'verifyDomain'])->middleware('can:tenants.update')->name('tenants.domains.verify');
    });

    Route::middleware('can:saas_plans.manage')->group(function () {
        Route::get('paket-saas', [Admin\SaasPlanController::class, 'index'])->name('plans.index');
        Route::get('paket-saas/tambah', [Admin\SaasPlanController::class, 'create'])->name('plans.create');
        Route::post('paket-saas', [Admin\SaasPlanController::class, 'store'])->name('plans.store');
        Route::get('paket-saas/{plan}/edit', [Admin\SaasPlanController::class, 'edit'])->name('plans.edit');
        Route::put('paket-saas/{plan}', [Admin\SaasPlanController::class, 'update'])->name('plans.update');
    });

    Route::middleware('can:saas_subscriptions.manage')->group(function () {
        Route::get('tagihan-saas', [Admin\SaasInvoiceController::class, 'index'])->name('saas-invoices.index');
        Route::post('tagihan-saas', [Admin\SaasInvoiceController::class, 'generate'])->name('saas-invoices.generate');
        Route::get('tagihan-saas/{invoice}', [Admin\SaasInvoiceController::class, 'show'])->name('saas-invoices.show');
        Route::post('tagihan-saas/{invoice}/bayar', [Admin\SaasInvoiceController::class, 'pay'])->name('saas-invoices.pay');
        Route::post('pembayaran-saas/{payment}/reversal', [Admin\SaasInvoiceController::class, 'reverse'])->name('saas-payments.reverse');
    });

    Route::get('audit', [Admin\AuditLogController::class, 'index'])->middleware('can:platform.audit_logs.view')->name('audit.index');
    Route::get('kesehatan', Admin\HealthController::class)->middleware('can:platform.health.view')->name('health');

    Route::middleware('can:platform.backups.restore')->group(function () {
        Route::get('backup', [Admin\BackupController::class, 'index'])->name('backups.index');
        Route::post('backup', [Admin\BackupController::class, 'run'])->name('backups.run');
        Route::post('backup/{artifact}/uji', [Admin\BackupController::class, 'test'])->name('backups.test');
        Route::post('backup/{artifact}/restore', [Admin\BackupController::class, 'restore'])->middleware('throttle:5,1')->name('backups.restore');
    });
});

/*
|--------------------------------------------------------------------------
| Area ISP (tenant)
|--------------------------------------------------------------------------
*/
Route::prefix('app')->name('app.')->middleware(['auth', 'tenant'])->group(function () {
    Route::get('/', Isp\DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');

    // Paket layanan internet
    Route::middleware('can:packages.manage')->group(function () {
        Route::get('paket', [Isp\ServicePackageController::class, 'index'])->name('packages.index');
        Route::get('paket/tambah', [Isp\ServicePackageController::class, 'create'])->name('packages.create');
        Route::post('paket', [Isp\ServicePackageController::class, 'store'])->name('packages.store');
        Route::get('paket/{package}/edit', [Isp\ServicePackageController::class, 'edit'])->name('packages.edit');
        Route::put('paket/{package}', [Isp\ServicePackageController::class, 'update'])->name('packages.update');
    });

    // Pelanggan
    Route::get('pelanggan', [Isp\CustomerController::class, 'index'])->middleware('can:customers.view')->name('customers.index');
    Route::get('pelanggan/tambah', [Isp\CustomerController::class, 'create'])->middleware('can:customers.create')->name('customers.create');
    Route::post('pelanggan', [Isp\CustomerController::class, 'store'])->middleware('can:customers.create')->name('customers.store');
    Route::middleware('can:customers.import')->group(function () {
        Route::get('import', [Isp\ImportController::class, 'index'])->name('imports.index');
        Route::get('import/template', [Isp\ImportController::class, 'template'])->name('imports.template');
        Route::post('import', [Isp\ImportController::class, 'store'])->middleware('throttle:20,1')->name('imports.store');
        Route::get('import/{import}', [Isp\ImportController::class, 'show'])->name('imports.show');
        Route::post('import/{import}/eksekusi', [Isp\ImportController::class, 'execute'])->name('imports.execute');
        Route::post('import/{import}/batal', [Isp\ImportController::class, 'cancel'])->name('imports.cancel');
        Route::get('import/{import}/kesalahan', [Isp\ImportController::class, 'errors'])->name('imports.errors');
    });
    Route::get('pelanggan/{customer}', [Isp\CustomerController::class, 'show'])->middleware('can:customers.view')->name('customers.show');
    Route::get('pelanggan/{customer}/edit', [Isp\CustomerController::class, 'edit'])->middleware('can:customers.update')->name('customers.edit');
    Route::put('pelanggan/{customer}', [Isp\CustomerController::class, 'update'])->middleware('can:customers.update')->name('customers.update');
    Route::get('pelanggan/{customer}/berkas/{type}', [Isp\CustomerController::class, 'file'])->whereIn('type', ['ktp', 'rumah'])->middleware('can:customers.view')->name('customers.file');
    Route::post('pelanggan/{customer}/portal', [Isp\CustomerController::class, 'portalLink'])->middleware('can:customers.portal.manage')->name('customers.portal');
    Route::post('pelanggan/{customer}/portal/toggle', [Isp\CustomerController::class, 'portalToggle'])->middleware('can:customers.portal.manage')->name('customers.portal.toggle');

    // Tiket bantuan
    Route::get('tiket', [Isp\TicketController::class, 'index'])->middleware('can:tickets.view')->name('tickets.index');
    Route::middleware('can:tickets.manage')->group(function () {
        Route::get('tiket/buat', [Isp\TicketController::class, 'create'])->name('tickets.create');
        Route::post('tiket', [Isp\TicketController::class, 'store'])->name('tickets.store');
        Route::post('tiket/{ticket}/balas', [Isp\TicketController::class, 'reply'])->name('tickets.reply');
        Route::put('tiket/{ticket}', [Isp\TicketController::class, 'update'])->name('tickets.update');
    });
    Route::get('tiket/{ticket}', [Isp\TicketController::class, 'show'])->middleware('can:tickets.view')->name('tickets.show');
    Route::get('tiket/{ticket}/lampiran/{message}', [Isp\TicketController::class, 'attachment'])->middleware('can:tickets.view')->name('tickets.attachment');

    Route::post('pelanggan/{customer}/layanan', [Isp\CustomerServiceController::class, 'store'])->middleware('can:services.manage')->name('services.store');
    Route::put('layanan/{service}', [Isp\CustomerServiceController::class, 'update'])->middleware('can:services.manage')->name('services.update');

    // Invoice
    Route::get('invoice', [Isp\InvoiceController::class, 'index'])->middleware('can:invoices.view')->name('invoices.index');
    Route::get('invoice/buat', [Isp\InvoiceController::class, 'create'])->middleware('can:invoices.create')->name('invoices.create');
    Route::post('invoice', [Isp\InvoiceController::class, 'store'])->middleware('can:invoices.create')->name('invoices.store');
    Route::post('invoice/generate', [Isp\InvoiceController::class, 'generate'])->middleware('can:invoices.create')->name('invoices.generate');
    Route::get('invoice/{invoice}', [Isp\InvoiceController::class, 'show'])->middleware('can:invoices.view')->name('invoices.show');
    Route::get('invoice/{invoice}/cetak', [Isp\InvoiceController::class, 'print'])->middleware('can:invoices.view')->name('invoices.print');
    Route::post('invoice/{invoice}/void', [Isp\InvoiceController::class, 'void'])->middleware('can:invoices.void')->name('invoices.void');

    // Pembayaran
    Route::get('pembayaran', [Isp\PaymentController::class, 'index'])->middleware('can:payments.view')->name('payments.index');
    Route::get('pembayaran/catat', [Isp\PaymentController::class, 'create'])->middleware('can:payments.create')->name('payments.create');
    Route::post('pembayaran', [Isp\PaymentController::class, 'store'])->middleware('can:payments.create')->name('payments.store');
    Route::get('pembayaran/{payment}', [Isp\PaymentController::class, 'show'])->middleware('can:payments.view')->name('payments.show');
    Route::get('pembayaran/{payment}/bukti', [Isp\PaymentController::class, 'proof'])->middleware('can:payments.view')->name('payments.proof');
    Route::get('pembayaran/{payment}/kuitansi', [Isp\PaymentController::class, 'receipt'])->middleware('can:payments.view')->name('payments.receipt');
    Route::post('pembayaran/{payment}/verifikasi', [Isp\PaymentController::class, 'verify'])->middleware('can:payments.verify')->name('payments.verify');
    Route::post('pembayaran/{payment}/tolak', [Isp\PaymentController::class, 'reject'])->middleware('can:payments.verify')->name('payments.reject');
    Route::post('pembayaran/{payment}/reversal', [Isp\PaymentController::class, 'reverse'])->middleware('can:payments.reverse')->name('payments.reverse');

    // Perangkat jaringan (registry)
    Route::get('perangkat', [Isp\NetworkDeviceController::class, 'index'])->middleware('can:monitoring.devices.view')->name('devices.index');
    Route::middleware('can:monitoring.devices.manage')->group(function () {
        Route::get('perangkat/tambah', [Isp\NetworkDeviceController::class, 'create'])->name('devices.create');
        Route::post('perangkat', [Isp\NetworkDeviceController::class, 'store'])->name('devices.store');
        Route::get('perangkat/{device}/edit', [Isp\NetworkDeviceController::class, 'edit'])->name('devices.edit');
        Route::put('perangkat/{device}', [Isp\NetworkDeviceController::class, 'update'])->name('devices.update');
        Route::delete('perangkat/{device}', [Isp\NetworkDeviceController::class, 'destroy'])->name('devices.destroy');
    });

    // Work order teknisi & kalender
    Route::get('work-order', [Isp\WorkOrderController::class, 'index'])->middleware('can:work_orders.view')->name('work-orders.index');
    Route::get('jadwal', [Isp\ScheduleController::class, 'index'])->middleware('can:work_orders.view')->name('schedule.index');
    Route::post('jadwal/ketersediaan', [Isp\ScheduleController::class, 'availability'])->middleware('can:work_orders.manage')->name('schedule.availability');
    Route::middleware('can:work_orders.manage')->group(function () {
        Route::get('work-order/buat', [Isp\WorkOrderController::class, 'create'])->name('work-orders.create');
        Route::post('work-order', [Isp\WorkOrderController::class, 'store'])->name('work-orders.store');
        Route::get('work-order/{workOrder}/edit', [Isp\WorkOrderController::class, 'edit'])->name('work-orders.edit');
        Route::put('work-order/{workOrder}', [Isp\WorkOrderController::class, 'update'])->name('work-orders.update');
    });
    Route::middleware('can:work_orders.view')->group(function () {
        Route::get('work-order/{workOrder}', [Isp\WorkOrderController::class, 'show'])->name('work-orders.show');
        Route::post('work-order/{workOrder}/status', [Isp\WorkOrderController::class, 'status'])->name('work-orders.status');
        Route::post('work-order/{workOrder}/checklist', [Isp\WorkOrderController::class, 'addChecklist'])->name('work-orders.checklist.add');
        Route::post('work-order/{workOrder}/checklist/{item}', [Isp\WorkOrderController::class, 'checklist'])->name('work-orders.checklist');
        Route::post('work-order/{workOrder}/foto', [Isp\WorkOrderController::class, 'media'])->name('work-orders.media');
        Route::get('work-order/{workOrder}/foto/{media}', [Isp\WorkOrderController::class, 'mediaFile'])->name('work-orders.media.file');
        Route::post('work-order/{workOrder}/material', [Isp\WorkOrderController::class, 'material'])->name('work-orders.material');
        Route::post('work-order/{workOrder}/material/{material}/kembali', [Isp\WorkOrderController::class, 'returnMaterial'])->name('work-orders.material.return');
    });

    // Inventaris
    Route::middleware('can:inventory.view')->group(function () {
        Route::get('inventaris', [Isp\InventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventaris/mutasi', [Isp\InventoryController::class, 'movements'])->name('inventory.movements');
        Route::get('inventaris/lokasi', [Isp\InventoryController::class, 'locations'])->name('inventory.locations');
    });
    Route::middleware('can:inventory.manage')->group(function () {
        Route::get('inventaris/tambah', [Isp\InventoryController::class, 'create'])->name('inventory.create');
        Route::post('inventaris', [Isp\InventoryController::class, 'store'])->name('inventory.store');
        Route::get('inventaris/{item}/edit', [Isp\InventoryController::class, 'edit'])->name('inventory.edit');
        Route::put('inventaris/{item}', [Isp\InventoryController::class, 'update'])->name('inventory.update');
        Route::post('inventaris/{item}/mutasi', [Isp\InventoryController::class, 'movement'])->name('inventory.movement');
        Route::post('inventaris/serial/{serial}/rusak', [Isp\InventoryController::class, 'serialFaulty'])->name('inventory.serial.faulty');
        Route::post('inventaris/lokasi', [Isp\InventoryController::class, 'storeLocation'])->name('inventory.locations.store');
    });
    Route::get('inventaris/{item}', [Isp\InventoryController::class, 'show'])->middleware('can:inventory.view')->name('inventory.show');

    // Monitoring jaringan (read-only)
    Route::middleware('can:monitoring.view')->group(function () {
        Route::get('monitoring', [Isp\MonitoringController::class, 'index'])->name('monitoring.index');
        Route::get('monitoring/alarm', [Isp\MonitoringController::class, 'alerts'])->name('monitoring.alerts');
    });
    Route::middleware('can:monitoring.settings.manage')->group(function () {
        Route::get('monitoring/target/tambah', [Isp\MonitoringController::class, 'create'])->name('monitoring.create');
        Route::post('monitoring/target', [Isp\MonitoringController::class, 'store'])->name('monitoring.store');
        Route::get('monitoring/target/{target}/edit', [Isp\MonitoringController::class, 'edit'])->name('monitoring.edit');
        Route::put('monitoring/target/{target}', [Isp\MonitoringController::class, 'update'])->name('monitoring.update');
        Route::delete('monitoring/target/{target}', [Isp\MonitoringController::class, 'destroy'])->name('monitoring.destroy');
        Route::post('monitoring/target/{target}/cek', [Isp\MonitoringController::class, 'checkNow'])->middleware('throttle:30,1')->name('monitoring.check');
        Route::post('monitoring/pengaturan', [Isp\MonitoringController::class, 'settings'])->name('monitoring.settings');
    });
    Route::get('monitoring/target/{target}', [Isp\MonitoringController::class, 'show'])->middleware('can:monitoring.view')->name('monitoring.show');
    Route::post('monitoring/alarm/{alert}/ack', [Isp\MonitoringController::class, 'acknowledge'])->middleware('can:monitoring.alerts.acknowledge')->name('monitoring.ack');

    // Gangguan massal & pengumuman
    Route::middleware('can:incidents.manage')->group(function () {
        Route::get('gangguan', [Isp\IncidentController::class, 'index'])->name('incidents.index');
        Route::get('gangguan/buat', [Isp\IncidentController::class, 'create'])->name('incidents.create');
        Route::post('gangguan', [Isp\IncidentController::class, 'store'])->name('incidents.store');
        Route::get('gangguan/{incident}', [Isp\IncidentController::class, 'show'])->name('incidents.show');
        Route::post('gangguan/{incident}/update', [Isp\IncidentController::class, 'update'])->name('incidents.update');
        Route::post('gangguan/{incident}/cakupan', [Isp\IncidentController::class, 'affected'])->name('incidents.affected');
    });
    Route::middleware('can:announcements.send')->group(function () {
        Route::get('pengumuman', [Isp\AnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('pengumuman/buat', [Isp\AnnouncementController::class, 'create'])->name('announcements.create');
        Route::post('pengumuman', [Isp\AnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('pengumuman/{announcement}', [Isp\AnnouncementController::class, 'show'])->name('announcements.show');
        Route::post('pengumuman/{announcement}/kirim', [Isp\AnnouncementController::class, 'send'])->middleware('throttle:10,1')->name('announcements.send');
    });

    // Komisi kolektor
    Route::get('komisi', [Isp\CommissionController::class, 'index'])->middleware('can:commissions.view')->name('commissions.index');
    Route::middleware('can:commissions.manage')->group(function () {
        Route::post('komisi/aturan', [Isp\CommissionController::class, 'storeRule'])->name('commissions.rules.store');
        Route::post('komisi/aturan/{rule}/toggle', [Isp\CommissionController::class, 'toggleRule'])->name('commissions.rules.toggle');
        Route::get('komisi/penugasan', [Isp\CommissionController::class, 'assignments'])->name('commissions.assignments');
        Route::post('komisi/penugasan', [Isp\CommissionController::class, 'saveAssignments'])->name('commissions.assignments.save');
        Route::post('komisi/bayar', [Isp\CommissionController::class, 'settle'])->name('commissions.settle');
    });

    // Rekonsiliasi pembayaran
    Route::middleware('can:reconciliation.manage')->group(function () {
        Route::get('rekonsiliasi', [Isp\ReconciliationController::class, 'index'])->name('reconciliation.index');
        Route::get('rekonsiliasi/template', [Isp\ReconciliationController::class, 'template'])->name('reconciliation.template');
        Route::post('rekonsiliasi', [Isp\ReconciliationController::class, 'store'])->name('reconciliation.store');
        Route::get('rekonsiliasi/{batch}', [Isp\ReconciliationController::class, 'show'])->name('reconciliation.show');
        Route::post('rekonsiliasi/{batch}/item/{item}', [Isp\ReconciliationController::class, 'resolve'])->name('reconciliation.resolve');
        Route::post('rekonsiliasi/{batch}/verifikasi', [Isp\ReconciliationController::class, 'verify'])->name('reconciliation.verify');
        Route::post('rekonsiliasi/{batch}/selesai', [Isp\ReconciliationController::class, 'complete'])->name('reconciliation.complete');
    });

    // Audit keuangan
    Route::get('keuangan', [Isp\FinanceController::class, 'index'])->middleware('can:reports.view')->name('finance.index');
    Route::post('keuangan/penyesuaian', [Isp\FinanceController::class, 'storeAdjustment'])->middleware('can:finance.adjustments.create')->name('finance.adjustments.store');
    Route::post('keuangan/penyesuaian/{adjustment}', [Isp\FinanceController::class, 'review'])->middleware('can:finance.adjustments.approve')->name('finance.adjustments.review');
    Route::post('keuangan/periode', [Isp\FinanceController::class, 'closePeriod'])->middleware('can:finance.periods.close')->name('finance.periods.close');
    Route::post('keuangan/periode/{period}/buka', [Isp\FinanceController::class, 'reopenPeriod'])->middleware('can:finance.periods.close')->name('finance.periods.reopen');

    // Backup tenant
    Route::middleware('can:backups.manage')->group(function () {
        Route::get('backup', [Isp\BackupController::class, 'index'])->name('backups.index');
        Route::post('backup', [Isp\BackupController::class, 'store'])->middleware('throttle:5,1')->name('backups.store');
        Route::get('backup/{artifact}/unduh', [Isp\BackupController::class, 'download'])->name('backups.download');
        Route::post('backup/{artifact}/uji', [Isp\BackupController::class, 'test'])->middleware('throttle:5,1')->name('backups.test');
    });

    // Topologi FTTH: ODC & titik ODP
    Route::get('odp', [Isp\OdpController::class, 'index'])->middleware('can:network.odp.view')->name('odps.index');
    Route::get('odc', [Isp\OdcController::class, 'index'])->middleware('can:network.odp.view')->name('odcs.index');
    Route::middleware('can:network.odp.manage')->group(function () {
        Route::get('odp/tambah', [Isp\OdpController::class, 'create'])->name('odps.create');
        Route::post('odp', [Isp\OdpController::class, 'store'])->name('odps.store');
        Route::get('odp/{odp}/edit', [Isp\OdpController::class, 'edit'])->name('odps.edit');
        Route::put('odp/{odp}', [Isp\OdpController::class, 'update'])->name('odps.update');
        Route::delete('odp/{odp}', [Isp\OdpController::class, 'destroy'])->name('odps.destroy');
        Route::post('odp-port/{port}/pasang', [Isp\OdpController::class, 'assign'])->name('odps.ports.assign');
        Route::post('odp-port/{port}/lepas', [Isp\OdpController::class, 'release'])->name('odps.ports.release');
        Route::post('odp-port/{port}/status', [Isp\OdpController::class, 'portStatus'])->name('odps.ports.status');
        Route::get('odc/tambah', [Isp\OdcController::class, 'create'])->name('odcs.create');
        Route::post('odc', [Isp\OdcController::class, 'store'])->name('odcs.store');
        Route::get('odc/{odc}/edit', [Isp\OdcController::class, 'edit'])->name('odcs.edit');
        Route::put('odc/{odc}', [Isp\OdcController::class, 'update'])->name('odcs.update');
    });
    Route::get('odp/{odp}', [Isp\OdpController::class, 'show'])->middleware('can:network.odp.view')->name('odps.show');

    // Pengaturan → WhatsApp → Template Pesan
    Route::middleware('can:notifications.manage')->group(function () {
        Route::get('pengaturan/whatsapp', [Isp\NotificationTemplateController::class, 'index'])->name('templates.index');
        Route::get('pengaturan/whatsapp/{template}/edit', [Isp\NotificationTemplateController::class, 'edit'])->name('templates.edit');
        Route::put('pengaturan/whatsapp/{template}', [Isp\NotificationTemplateController::class, 'update'])->name('templates.update');
        Route::post('pengaturan/whatsapp/preview', [Isp\NotificationTemplateController::class, 'preview'])->name('templates.preview');
        Route::get('pengaturan/whatsapp/log', [Isp\NotificationTemplateController::class, 'logs'])->name('templates.logs');
    });
    Route::post('pengaturan/whatsapp/{template}/tes', [Isp\NotificationTemplateController::class, 'testSend'])->middleware('can:notifications.test_send')->name('templates.test');

    // Pengaturan umum & pengguna
    Route::get('pengaturan', [Isp\SettingsController::class, 'edit'])->middleware('can:settings.manage')->name('settings.edit');
    Route::put('pengaturan', [Isp\SettingsController::class, 'update'])->middleware('can:settings.manage')->name('settings.update');

    Route::middleware('can:users.manage')->group(function () {
        Route::get('pengguna', [Isp\UserController::class, 'index'])->name('users.index');
        Route::get('pengguna/tambah', [Isp\UserController::class, 'create'])->name('users.create');
        Route::post('pengguna', [Isp\UserController::class, 'store'])->name('users.store');
        Route::get('pengguna/{user}/edit', [Isp\UserController::class, 'edit'])->name('users.edit');
        Route::put('pengguna/{user}', [Isp\UserController::class, 'update'])->name('users.update');
        Route::post('pengguna/{user}/aktivasi', [Isp\UserController::class, 'activationLink'])->name('users.activation');
    });

    Route::get('audit', [Isp\AuditLogController::class, 'index'])->middleware('can:audit_logs.view')->name('audit.index');
});

/*
|--------------------------------------------------------------------------
| Portal pelanggan (PWA) — /portal/{kode ISP}
|--------------------------------------------------------------------------
*/
Route::prefix('portal/{tenantCode}')->name('portal.')->where(['tenantCode' => '[A-Za-z0-9\-]+'])->middleware('portal.tenant')->group(function () {
    Route::middleware('guest:customer')->group(function () {
        Route::get('login', [Portal\AuthController::class, 'show'])->name('login');
        Route::post('login', [Portal\AuthController::class, 'login'])->middleware('throttle:20,1');
        Route::get('aktivasi/{token}', [Portal\AuthController::class, 'activation'])->name('activation');
        Route::post('aktivasi/{token}', [Portal\AuthController::class, 'activate'])->middleware('throttle:10,1')->name('activate');
    });

    Route::middleware('auth:customer')->group(function () {
        Route::post('logout', [Portal\AuthController::class, 'logout'])->name('logout');
        Route::get('/', Portal\HomeController::class)->name('home');
        Route::get('tagihan', [Portal\BillingController::class, 'invoices'])->name('invoices');
        Route::get('tagihan/{invoice}', [Portal\BillingController::class, 'invoice'])->whereNumber('invoice')->name('invoices.show');
        Route::get('tagihan/{invoice}/bayar', [Portal\BillingController::class, 'pay'])->whereNumber('invoice')->name('invoices.pay');
        Route::post('tagihan/{invoice}/bayar', [Portal\BillingController::class, 'submitProof'])->whereNumber('invoice')->middleware('throttle:10,1')->name('invoices.proof');
        Route::get('pembayaran', [Portal\BillingController::class, 'payments'])->name('payments');
        Route::get('pembayaran/{payment}/kuitansi', [Portal\BillingController::class, 'receipt'])->whereNumber('payment')->name('payments.receipt');
        Route::get('tiket', [Portal\TicketController::class, 'index'])->name('tickets.index');
        Route::get('tiket/baru', [Portal\TicketController::class, 'create'])->name('tickets.create');
        Route::post('tiket', [Portal\TicketController::class, 'store'])->middleware('throttle:10,1')->name('tickets.store');
        Route::get('tiket/{ticket}', [Portal\TicketController::class, 'show'])->whereNumber('ticket')->name('tickets.show');
        Route::post('tiket/{ticket}/balas', [Portal\TicketController::class, 'reply'])->whereNumber('ticket')->middleware('throttle:20,1')->name('tickets.reply');
        Route::get('tiket/{ticket}/lampiran/{message}', [Portal\TicketController::class, 'attachment'])->whereNumber(['ticket', 'message'])->name('tickets.attachment');
        Route::get('profil', [Portal\ProfileController::class, 'edit'])->name('profile');
        Route::put('profil/password', [Portal\ProfileController::class, 'password'])->name('profile.password');
    });
});
