<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * User tidak memakai global tenant scope (dibutuhkan untuk login),
 * sehingga setiap query user di area tenant WAJIB memakai scope forCurrentTenant().
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password', 'is_active', 'theme_preference'];

    protected $hidden = ['password', 'remember_token'];

    protected ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function scopeForCurrentTenant($query)
    {
        return $query->where('tenant_id', app(\App\Support\TenantContext::class)->id() ?? 0);
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin && $this->tenant_id === null;
    }

    public function permissionNames(): array
    {
        if ($this->permissionCache !== null) {
            return $this->permissionCache;
        }

        $roles = $this->roles()->with('permissions')->get()
            // Role tenant hanya berlaku bila milik tenant user / role sistem dengan scope yang cocok.
            ->filter(fn (Role $r) => $r->tenant_id === null || $r->tenant_id === $this->tenant_id)
            ->filter(fn (Role $r) => $this->isSuperAdmin() ? $r->scope === 'platform' : $r->scope === 'tenant');

        return $this->permissionCache = $roles->flatMap(fn ($r) => $r->permissions->pluck('name'))->unique()->values()->all();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissionNames(), true);
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    public function flushPermissionCache(): void
    {
        $this->permissionCache = null;
        $this->unsetRelation('roles');
    }
}
