<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Pencatat audit: pelaku, tenant, waktu, tindakan, alasan, nilai sebelum/sesudah.
 * @2026-Alifiandra
 */
class AuditLogger
{
    protected const REDACT = ['password', 'remember_token', 'nik', 'token', 'token_hash', 'secret', 'api_key'];

    public static function log(string $action, ?Model $subject = null, ?array $before = null, ?array $after = null, ?string $reason = null, ?int $tenantId = null): AuditLog
    {
        $request = request();

        return AuditLog::create([
            'tenant_id' => $tenantId ?? ($subject?->getAttribute('tenant_id')) ?? app(TenantContext::class)->id(),
            'user_id' => Auth::guard('web')->id(),
            'action' => $action,
            'auditable_type' => $subject ? $subject->getMorphClass() : null,
            'auditable_id' => $subject?->getKey(),
            'before' => $before ? self::redact($before) : null,
            'after' => $after ? self::redact($after) : null,
            'reason' => $reason,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 250) : null,
        ]);
    }

    /** Catat perubahan model (getOriginal vs getChanges) setelah save. */
    public static function changes(string $action, Model $model, array $original, ?string $reason = null): ?AuditLog
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);
        if (! $changes) {
            return null;
        }

        return self::log($action, $model, array_intersect_key($original, $changes), $changes, $reason);
    }

    protected static function redact(array $data): array
    {
        foreach ($data as $k => $v) {
            if (in_array($k, self::REDACT, true)) {
                $data[$k] = '[disembunyikan]';
            } elseif (is_array($v)) {
                $data[$k] = self::redact($v);
            }
        }

        return $data;
    }
}
