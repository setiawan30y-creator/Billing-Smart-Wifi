<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaasInvoiceItem extends Model
{
    protected $fillable = ['type', 'description', 'quantity', 'unit_price', 'amount'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'amount' => 'decimal:2'];
    }
}
