<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketMessage extends Model
{
    protected $fillable = ['ticket_id', 'author_type', 'author_id', 'body', 'is_internal', 'attachment_path'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function authorName(): string
    {
        return match ($this->author_type) {
            'user' => User::find($this->author_id)?->name ?? 'Staf',
            'customer' => 'Pelanggan',
            default => 'Sistem',
        };
    }
}
