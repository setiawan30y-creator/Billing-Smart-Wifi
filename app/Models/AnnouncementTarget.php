<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnouncementTarget extends Model
{
    public $timestamps = false;

    protected $fillable = ['service_announcement_id', 'target_type', 'target_id'];
}
