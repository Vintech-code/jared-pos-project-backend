<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\ProductVariant;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'unit_price',
        'quantity',
        'unit_of_measurement',
        'category',
        'hidden',
        'image_url',
    ];

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true);
    }
}
