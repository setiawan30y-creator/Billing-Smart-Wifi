<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

/**
 * Akun portal pelanggan. Tidak memakai global tenant scope (dibutuhkan untuk login),
 * tetapi login selalu dibatasi pada tenant dari URL portal.
 */
class CustomerAccount extends Authenticatable
{
    protected $fillable = ['tenant_id', 'customer_id', 'is_active'];

    protected $hidden = ['password', 'remember_token', 'activation_token_hash'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean', 'last_login_at' => 'datetime', 'activation_expires_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Customer tanpa global scope (relasi dipakai di konteks portal). */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withoutGlobalScopes();
    }

    /** Buat token aktivasi sekali pakai (72 jam). Mengembalikan token asli. */
    public function issueActivationToken(int $hours = 72): string
    {
        $token = Str::random(48);
        $this->forceFill(['activation_token_hash' => hash('sha256', $token), 'activation_expires_at' => now()->addHours($hours)])->save();

        return $token;
    }
}
