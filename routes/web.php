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
