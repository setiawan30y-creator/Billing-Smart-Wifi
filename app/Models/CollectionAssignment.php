<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CollectionAssignment extends Model
{
    use BelongsToTenant;

    protected $table = 'collection_assignments';

    protected $fillable = ['user_id', 'customer_id'];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
