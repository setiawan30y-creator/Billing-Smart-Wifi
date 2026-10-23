<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = ['tenant_id', 'slug', 'name', 'scope', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /** Role yang boleh dipakai tenant: role sistem scope tenant. */
    public function scopeAssignableForTenant($query)
    {
        return $query->whereNull('tenant_id')->where('scope', 'tenant');
    }
}
