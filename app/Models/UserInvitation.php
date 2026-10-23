<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Token aktivasi: singkat, sekali pakai, hanya hash yang disimpan.
 */
class UserInvitation extends Model
{
    protected $fillable = ['user_id', 'token_hash', 'expires_at', 'created_by'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array{0: UserInvitation, 1: string} [invitation, plain token] */
    public static function issue(User $user, ?int $createdBy = null, int $hours = 48): array
    {
        static::where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);

        $token = Str::random(48);
        $inv = static::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHours($hours),
            'created_by' => $createdBy,
        ]);

        return [$inv, $token];
    }

    public static function findValid(string $token): ?self
    {
        return static::where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }
}
