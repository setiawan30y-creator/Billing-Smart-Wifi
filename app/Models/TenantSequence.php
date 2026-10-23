<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantSequence extends Model
{
    public $timestamps = false;

    protected $fillable = ['tenant_id', 'key', 'value'];
}
