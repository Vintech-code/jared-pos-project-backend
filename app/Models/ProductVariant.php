<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'unit_label',
        'cost_price',
        'unit_price',
        'quantity',
        'conversion_factor',
        'barcode',
        'is_default',
        'hidden',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'conversion_factor' => 'float',
        'is_default' => 'boolean',
        'hidden' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
