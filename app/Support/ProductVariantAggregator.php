<?php

namespace App\Support;

use App\Models\Product;

class ProductVariantAggregator
{
    public static function refresh(Product $product): void
    {
        $product->loadMissing('variants');

        if ($product->variants->isEmpty()) {
            return;
        }

        $product->quantity = $product->variants->sum('quantity');

        $defaultVariant = $product->variants->firstWhere('is_default', true)
            ?? $product->variants->first();

        if ($defaultVariant) {
            $product->unit_price = $defaultVariant->unit_price;
            $product->unit_of_measurement = $defaultVariant->unit_label;
            if ($defaultVariant->sku) {
                $product->sku = $defaultVariant->sku;
            }
        }

        $product->save();
    }
}
