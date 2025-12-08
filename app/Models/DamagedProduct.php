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
        'logged_at',
        'unit_of_measurement',
        'refunded',
        'refunded_at',
        'variant_id',
    ];

    protected $casts = [
        'refunded' => 'boolean',
        'refunded_at' => 'datetime',
        'date' => 'date',
        'logged_at' => 'datetime',
    ];
}