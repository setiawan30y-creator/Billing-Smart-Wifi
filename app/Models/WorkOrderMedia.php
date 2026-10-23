<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderMedia extends Model
{
    protected $table = 'work_order_media';

    protected $fillable = ['work_order_id', 'path', 'kind', 'caption', 'uploaded_by'];
}
