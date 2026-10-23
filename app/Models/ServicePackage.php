<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePackage extends Model
{
    use BelongsToTenant;

    protected $fillable = ['code', 'name', 'price', 'download_mbps', 'upload_mbps', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function services(): HasMany
    {
        return $this->hasMany(CustomerService::class);
    }

    public function speedLabel(): string
    {
        if (! $this->download_mbps) {
            return '-';
        }

        return $this->download_mbps.'/'.($this->upload_mbps ?: $this->download_mbps).' Mbps';
    }
}
