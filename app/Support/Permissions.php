<?php

namespace App\Support;

/**
 * Katalog permission & role sistem. Ditegakkan di backend (middleware/Gate).
 * @2026-Alifiandra
 */
class Permissions
{
    /** name => [group, label, scope] */
    public const ALL = [
        // Platform (Super Admin)
        'platform.dashboard' => ['platform', 'Dashboard platform', 'platform'],
        'tenants.view' => ['tenants', 'Lihat ISP', 'platform'],
        'tenants.create' => ['tenants', 'Tambah ISP', 'platform'],
        'tenants.update' => ['tenants', 'Ubah ISP', 'platform'],
        'tenants.suspend' => ['tenants', 'Suspend / aktifkan ISP', 'platform'],
        'saas_plans.manage' => ['saas', 'Kelola paket SaaS', 'platform'],
        'saas_subscriptions.manage' => ['saas', 'Kelola langganan & tagihan SaaS', 'platform'],
        'platform.audit_logs.view' => ['platform', 'Lihat audit platform', 'platform'],

        // Tenant (ISP)
        'dashboard.view' => ['dashboard', 'Lihat dashboard', 'tenant'],
        'customers.view' => ['customers', 'Lihat pelanggan', 'tenant'],
        'customers.create' => ['customers', 'Tambah pelanggan', 'tenant'],
        'customers.update' => ['customers', 'Ubah pelanggan', 'tenant'],
        'customers.sensitive.view' => ['customers', 'Lihat NIK/KTP pelanggan', 'tenant'],
        'packages.manage' => ['packages', 'Kelola paket layanan', 'tenant'],
        'services.manage' => ['services', 'Kelola layanan pelanggan', 'tenant'],
        'invoices.view' => ['invoices', 'Lihat invoice', 'tenant'],
        'invoices.create' => ['invoices', 'Buat invoice', 'tenant'],
        'invoices.void' => ['invoices', 'Batalkan (void) invoice', 'tenant'],
        'payments.view' => ['payments', 'Lihat pembayaran', 'tenant'],
        'payments.create' => ['payments', 'Catat pembayaran', 'tenant'],
        'payments.verify' => ['payments', 'Verifikasi pembayaran', 'tenant'],
        'payments.reverse' => ['payments', 'Reversal pembayaran', 'tenant'],
        'reports.view' => ['reports', 'Lihat laporan', 'tenant'],
        'monitoring.devices.view' => ['monitoring', 'Lihat perangkat jaringan', 'tenant'],
        'monitoring.devices.manage' => ['monitoring', 'Kelola perangkat jaringan', 'tenant'],
        'network.odp.view' => ['network', 'Lihat ODC/ODP & peta jaringan', 'tenant'],
        'network.odp.manage' => ['network', 'Kelola ODC/ODP & port', 'tenant'],
        'notifications.manage' => ['notifications', 'Kelola template WhatsApp', 'tenant'],
        'notifications.test_send' => ['notifications', 'Kirim tes WhatsApp', 'tenant'],
        'users.manage' => ['users', 'Kelola pengguna tenant', 'tenant'],
        'settings.manage' => ['settings', 'Kelola pengaturan tenant', 'tenant'],
        'audit_logs.view' => ['audit', 'Lihat audit log', 'tenant'],
    ];

    /** slug => [name, scope, permissions ('*' = semua di scope)] */
    public const ROLES = [
        'super_admin' => ['Super Admin ALMARA', 'platform', ['*']],
        'isp_admin' => ['Admin ISP', 'tenant', ['*']],
        'noc' => ['NOC / Network Engineer', 'tenant', [
            'dashboard.view', 'customers.view', 'monitoring.devices.view', 'monitoring.devices.manage',
            'network.odp.view', 'network.odp.manage',
        ]],
        'billing' => ['Staf Billing / Keuangan', 'tenant', [
            'dashboard.view', 'customers.view', 'customers.create', 'customers.update', 'services.manage',
            'invoices.view', 'invoices.create', 'invoices.void', 'payments.view', 'payments.create',
            'payments.verify', 'payments.reverse', 'reports.view', 'network.odp.view',
        ]],
        'technician' => ['Teknisi', 'tenant', [
            'dashboard.view', 'customers.view', 'monitoring.devices.view', 'network.odp.view', 'network.odp.manage',
        ]],
        'collector' => ['Petugas Kolektor / Agen', 'tenant', [
            'dashboard.view', 'customers.view', 'invoices.view', 'payments.view', 'payments.create',
        ]],
        'auditor' => ['Auditor (baca saja)', 'tenant', [
            'dashboard.view', 'customers.view', 'invoices.view', 'payments.view', 'reports.view', 'audit_logs.view',
        ]],
    ];

    public static function forScope(string $scope): array
    {
        return array_keys(array_filter(self::ALL, fn ($p) => $p[2] === $scope));
    }

    public static function tenantRoleSlugs(): array
    {
        return array_keys(array_filter(self::ROLES, fn ($r) => $r[1] === 'tenant'));
    }
}
