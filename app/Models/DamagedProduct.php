<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DamagedProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_name',
        'product_name',
        'quantity',
        'reason',
        'action_taken',
        'date',
        'unit_of_measurement',
        'refunded',
        'refunded_at',
    ];

    protected $casts = [
        'refunded' => 'boolean',
        'refunded_at' => 'datetime',
        'date' => 'date',
    ];
}