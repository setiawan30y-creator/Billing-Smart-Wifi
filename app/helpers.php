<?php

/**
 * Helper global ALMARA Billing. @2026-Alifiandra
 */
if (! function_exists('rupiah')) {
    function rupiah(string|int|float|null $value): string
    {
        return \App\Support\Money::format($value);
    }
}

if (! function_exists('tgl')) {
    function tgl($date, string $format = 'd M Y'): string
    {
        return $date ? \Illuminate\Support\Carbon::parse($date)->translatedFormat($format) : '-';
    }
}

if (! function_exists('status_badge')) {
    function status_badge(string $status): string
    {
        return match ($status) {
            'active', 'paid', 'verified', 'done', 'sent', 'delivered', 'read', 'processed' => 'success',
            'trial', 'partial', 'pending', 'queued', 'running', 'provisioning', 'pending_activation', 'prospect' => 'warning',
            'suspended', 'void', 'rejected', 'failed', 'provisioning_failed', 'isolated', 'reversed', 'unmatched', 'past_due' => 'danger',
            default => 'muted',
        };
    }
}
