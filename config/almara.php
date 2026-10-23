<?php

/**
 * Konfigurasi ALMARA Billing WiFi SaaS.
 * @2026-Alifiandra
 */
return [
    'version' => '1.0.0',
    'signature' => '@2026-Alifiandra',

    // Domain platform untuk subdomain tenant, mis. "almara-billing.id" → isp1.almara-billing.id
    'platform_domain' => env('ALMARA_PLATFORM_DOMAIN'),

    // PWA/service worker. Default hanya aktif di production agar tidak bentrok antarproyek di localhost.
    'pwa' => in_array(env('ALMARA_PWA'), [null, ''], true) ? env('APP_ENV') === 'production' : (bool) env('ALMARA_PWA'),

    'super_admin' => [
        'name' => env('SUPERADMIN_NAME', 'Super Admin'),
        'email' => env('SUPERADMIN_EMAIL', 'superadmin@example.com'),
        'password' => env('SUPERADMIN_PASSWORD'),
    ],

    'backup_retention_days' => (int) env('ALMARA_BACKUP_RETENTION_DAYS', 30),

    // Disk privat untuk KTP, foto rumah, bukti bayar (tidak boleh URL publik).
    'private_disk' => env('ALMARA_PRIVATE_DISK', 'local'),

    'whatsapp' => [
        // log = hanya dicatat (default aman); http = adapter HTTP generik ke gateway WA yang sudah ada.
        'driver' => env('WHATSAPP_DRIVER', 'log'),
        'http' => [
            'url' => env('WHATSAPP_HTTP_URL'),
            'token' => env('WHATSAPP_HTTP_TOKEN'),
            'number_field' => env('WHATSAPP_HTTP_NUMBER_FIELD', 'number'),
            'message_field' => env('WHATSAPP_HTTP_MESSAGE_FIELD', 'message'),
            'timeout' => 15,
        ],
        'rate_per_minute' => (int) env('WHATSAPP_RATE_PER_MINUTE', 20),
    ],

    'payment_gateway' => [
        // Adapter generik HMAC-SHA256. Sesuaikan setelah dokumentasi API gateway diverifikasi.
        'provider' => env('PAYMENT_GATEWAY_PROVIDER', 'generic'),
        'webhook_secret' => env('PAYMENT_GATEWAY_WEBHOOK_SECRET'),
        'signature_header' => env('PAYMENT_GATEWAY_SIGNATURE_HEADER', 'X-Signature'),
    ],
];
