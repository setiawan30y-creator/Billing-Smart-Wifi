<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderChecklist extends Model
{
    protected $fillable = ['work_order_id', 'label', 'is_done', 'done_by', 'done_at', 'sort'];

    protected function casts(): array
    {
        return ['is_done' => 'boolean', 'done_at' => 'datetime'];
    }
}
