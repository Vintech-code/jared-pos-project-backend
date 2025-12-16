<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ProductVariantAggregator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductVariantController extends Controller
{
    public function store(Product $product, Request $request)
    {
        $validated = $request->validate([
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('product_variants', 'sku')],
            'unit_label' => 'required|string|max:100',
            'cost_price' => 'required|numeric|min:0',
            'unit_price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'conversion_factor' => 'nullable|numeric|min:0.0001',
            'barcode' => 'nullable|string|max:255',
            'is_default' => 'sometimes|boolean',
        ]);

        if (!empty($validated['is_default']) && $validated['is_default']) {
            $product->variants()->update(['is_default' => false]);
        }

        $variant = $product->variants()->create([
            'sku' => $validated['sku'] ?? null,
            'unit_label' => $validated['unit_label'],
            'cost_price' => $validated['cost_price'],
            'unit_price' => $validated['unit_price'],
            'quantity' => $validated['quantity'],
            'conversion_factor' => $validated['conversion_factor'] ?? 1,
            'barcode' => $validated['barcode'] ?? null,
            'is_default' => $validated['is_default'] ?? $product->variants()->doesntExist(),
        ]);

        ProductVariantAggregator::refresh($product);

        return response()->json([
            'message' => 'Variant added successfully.',
            'variant' => $variant,
            'product' => $product->fresh('variants'),
        ], 201);
    }

    public function update(Product $product, ProductVariant $variant, Request $request)
    {
        $this->guardVariant($product, $variant);

        $validated = $request->validate([
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('product_variants', 'sku')->ignore($variant->id)],
            'unit_label' => 'sometimes|string|max:100',
            'cost_price' => 'sometimes|numeric|min:0',
            'unit_price' => 'sometimes|numeric|min:0',
            'quantity' => 'sometimes|integer|min:0',
            'conversion_factor' => 'nullable|numeric|min:0.0001',
            'barcode' => 'nullable|string|max:255',
            'is_default' => 'sometimes|boolean',
        ]);

        $variant->fill($validated);

        if (array_key_exists('is_default', $validated) && $validated['is_default']) {
            $product->variants()->update(['is_default' => false]);
            $variant->is_default = true;
        }

        $variant->save();

        ProductVariantAggregator::refresh($product);

        return response()->json([
            'message' => 'Variant updated successfully.',
            'variant' => $variant,
            'product' => $product->fresh('variants'),
        ]);
    }

    public function destroy(Product $product, ProductVariant $variant)
    {
        $this->guardVariant($product, $variant);

        if ($product->variants()->count() === 1) {
            return response()->json(['message' => 'Cannot delete the last variant of a product.'], 422);
        }

        $variant->delete();

        ProductVariantAggregator::refresh($product);

        return response()->json([
            'message' => 'Variant removed successfully.',
            'product' => $product->fresh('variants'),
        ]);
    }

    public function receive(Product $product, ProductVariant $variant, Request $request)
    {
        $this->guardVariant($product, $variant);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $variant->quantity += $validated['quantity'];
        $variant->save();

        ProductVariantAggregator::refresh($product);

        return response()->json([
            'message' => 'Variant quantity increased successfully.',
            'variant' => $variant,
            'product' => $product->fresh('variants'),
        ]);
    }

    public function deduct(Product $product, ProductVariant $variant, Request $request)
    {
        $this->guardVariant($product, $variant);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        if ($variant->quantity < $validated['quantity']) {
            return response()->json(['message' => 'Not enough stock to deduct.'], 400);
        }

        $variant->quantity -= $validated['quantity'];
        $variant->save();

        ProductVariantAggregator::refresh($product);

        return response()->json([
            'message' => 'Variant quantity deducted successfully.',
            'variant' => $variant,
            'product' => $product->fresh('variants'),
        ]);
    }

    public function toggleHidden(Product $product, ProductVariant $variant)
    {
        $this->guardVariant($product, $variant);

        $variant->hidden = !$variant->hidden;
        $variant->save();

        return response()->json([
            'message' => 'Variant visibility updated.',
            'variant' => $variant,
        ]);
    }

    public function setDefault(Product $product, ProductVariant $variant)
    {
        $this->guardVariant($product, $variant);

        $product->variants()->update(['is_default' => false]);
        $variant->is_default = true;
        $variant->save();

        ProductVariantAggregator::refresh($product);

        return response()->json([
            'message' => 'Variant marked as default.',
            'product' => $product->fresh('variants'),
        ]);
    }

    private function guardVariant(Product $product, ProductVariant $variant): void
    {
        if ($variant->product_id !== $product->id) {
            abort(404, 'Variant does not belong to this product.');
        }
    }
}
