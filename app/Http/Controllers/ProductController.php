<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ProductVariantAggregator;

class ProductController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:products,name'],
            'sku' => 'nullable|string|max:100',
            'unit_price' => 'required|numeric|min:0.01',
            'quantity' => 'required|integer|min:0',
            'unit_of_measurement' => 'required|string|max:100',
            'category' => 'nullable|string|max:100',
            'image_url' => 'nullable|string',
            'variants' => 'nullable|array|min:1',
            'variants.*.sku' => ['nullable', 'string', 'max:100', Rule::unique('product_variants', 'sku'), 'distinct'],
            'variants.*.unit_label' => 'required_with:variants|string|max:100',
            'variants.*.unit_price' => 'required_with:variants|numeric|min:0',
            'variants.*.quantity' => 'required_with:variants|integer|min:0',
            'variants.*.conversion_factor' => 'nullable|numeric|min:0.0001',
            'variants.*.barcode' => 'nullable|string|max:255',
            'variants.*.is_default' => 'sometimes|boolean',
        ]);

        // Handle base64 image if provided
        if (!empty($validated['image_url']) && str_starts_with($validated['image_url'], 'data:image')) {
            $image = $validated['image_url'];

            // Extract base64 data
            preg_match('/^data:image\/(\w+);base64,/', $image, $matches);
            $imageData = substr($image, strpos($image, ',') + 1);
            $imageType = $matches[1] ?? 'png';

            // Decode base64
            $decodedImage = base64_decode($imageData);

            // Generate unique filename
            $filename = 'product_' . time() . '_' . uniqid() . '.' . $imageType;

            // Save to storage/app/public/products/
            $path = 'products/' . $filename;
            \Storage::disk('public')->put($path, $decodedImage);

            // Update validated data with file path
            $validated['image_url'] = $path;
        } else {
            $validated['image_url'] = null;
        }

        $product = Product::create($validated);

        $variantsPayload = collect($request->input('variants', []));

        if ($variantsPayload->isEmpty()) {
            $variantsPayload = collect([
                [
                    'sku' => $validated['sku'] ?? null,
                    'unit_label' => $validated['unit_of_measurement'],
                    'unit_price' => $validated['unit_price'],
                    'quantity' => $validated['quantity'],
                    'conversion_factor' => 1,
                    'barcode' => null,
                    'is_default' => true,
                ]
            ]);
        }

        $variants = [];

        foreach ($variantsPayload as $index => $variantData) {
            $variants[] = ProductVariant::create([
                'product_id' => $product->id,
                'sku' => $variantData['sku'] ?? null,
                'unit_label' => $variantData['unit_label'],
                'unit_price' => $variantData['unit_price'],
                'quantity' => $variantData['quantity'],
                'conversion_factor' => $variantData['conversion_factor'] ?? 1,
                'barcode' => $variantData['barcode'] ?? null,
                'is_default' => $variantData['is_default'] ?? $index === 0,
                'hidden' => false,
            ]);
        }

        ProductVariantAggregator::refresh($product);
        $product->load('variants');

        return response()->json([
            'message' => 'Product added successfully!',
            'product' => $product
        ], 201);
    }

    // List all products (even hidden ones)
    public function index(Request $request)
    {
        $products = Product::with('variants')->get();

        return response()->json($products);
    }

    // Show a specific product
    public function show($id)
    {
        $product = Product::with('variants')->findOrFail($id);
        return response()->json($product);
    }

    // Receive stock
    public function receive($id, Request $request)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'variant_id' => 'nullable|exists:product_variants,id',
        ]);

        $product = Product::with('variants')->findOrFail($id);

        if ($product->variants->isEmpty()) {
            $product->quantity += $validated['quantity'];
            $product->save();

            return response()->json([
                'message' => 'Product quantity increased successfully.',
                'product' => $product,
            ]);
        }

        $variant = $this->resolveVariantOrFail($product, $validated['variant_id'] ?? null);
        $variant->quantity += $validated['quantity'];
        $variant->save();

        ProductVariantAggregator::refresh($product);

        return response()->json([
            'message' => 'Variant quantity increased successfully.',
            'product' => $product->fresh('variants'),
        ]);
    }

    public function deduct($productName, Request $request)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'variant_id' => 'nullable|exists:product_variants,id',
        ]);

        $product = Product::with('variants')->where('name', $productName)->first();

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        if ($product->variants->isEmpty()) {
            $product->quantity -= $validated['quantity'];
            $product->save();

            return response()->json(['message' => 'Product quantity deducted successfully'], 200);
        }

        $variant = $this->resolveVariantOrFail($product, $validated['variant_id'] ?? null);

        if ($variant->quantity < $validated['quantity']) {
            return response()->json(['message' => 'Not enough stock to deduct from the selected variant.'], 400);
        }

        $variant->quantity -= $validated['quantity'];
        $variant->save();

        ProductVariantAggregator::refresh($product);

        return response()->json(['message' => 'Variant quantity deducted successfully'], 200);
    }

    // Hide product (mark as hidden, but keep it in the list)
    public function hideProduct($id)
    {
        $product = Product::findOrFail($id);
        $product->hidden = true;  // Mark as hidden, but don't remove from the list
        $product->save();

        return response()->json([
            'message' => 'Product marked as hidden successfully.',
            'product' => $product,
        ]);
    }

    // Unhide product
    public function unhideProduct($id)
    {
        $product = Product::findOrFail($id);
        $product->hidden = false;  // Mark as visible again
        $product->save();

        return response()->json(['message' => 'Product unhidden successfully']);
    }

    public function update(Request $request, $id)
    {
        $product = Product::with('variants')->findOrFail($id);

        $rawHasVariants = $request->input('has_variants', null);
        $hasVariants = filter_var($rawHasVariants, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($hasVariants === null) {
            $hasVariants = $product->variants->count() > 1;
        }

        $rules = [
            'name' => ['required', 'string', 'max:255', Rule::unique('products', 'name')->ignore($product->id)],
            'category' => 'nullable|string|max:100',
            'sku' => ['nullable', 'string', 'max:100'],
            'image' => 'nullable|image|max:5120',
            'remove_image' => 'nullable|boolean',
            'variants' => 'nullable|string',
        ];

        if (!$hasVariants) {
            $rules['unit_price'] = 'required|numeric|min:0';
            $rules['unit_of_measurement'] = 'required|string|max:100';
        }

        $validated = $request->validate($rules);

        $variantsPayload = [];
        if ($hasVariants) {
            $variantsPayload = $request->input('variants', '[]');
            if (is_string($variantsPayload)) {
                $variantsPayload = json_decode($variantsPayload, true) ?? [];
            }

            if (!is_array($variantsPayload)) {
                $variantsPayload = [];
            }

            foreach ($variantsPayload as $index => $variantData) {
                Validator::make(
                    $variantData,
                    [
                        'id' => ['required', 'integer', Rule::exists('product_variants', 'id')->where(fn($query) => $query->where('product_id', $product->id))],
                        'unit_label' => 'required|string|max:100',
                        'unit_price' => 'required|numeric|min:0',
                        'sku' => ['nullable', 'string', 'max:100', Rule::unique('product_variants', 'sku')->ignore($variantData['id'])],
                        'barcode' => 'nullable|string|max:255',
                        'is_default' => 'boolean',
                    ],
                    [],
                    [
                        'unit_label' => 'Variant #' . ($index + 1) . ' unit label',
                        'unit_price' => 'Variant #' . ($index + 1) . ' unit price',
                    ]
                )->validate();
            }
        }

        $product->name = $request->input('name', $product->name);
        $product->category = $request->filled('category') ? $request->input('category') : null;

        if (!$hasVariants) {
            $product->unit_price = $request->input('unit_price', $product->unit_price);
            $product->unit_of_measurement = $request->input('unit_of_measurement', $product->unit_of_measurement);
            $product->sku = $request->filled('sku') ? $request->input('sku') : null;
        }

        if ($request->boolean('remove_image')) {
            if ($product->image_url) {
                Storage::disk('public')->delete($product->image_url);
            }
            $product->image_url = null;
        }

        if ($request->hasFile('image')) {
            if ($product->image_url) {
                Storage::disk('public')->delete($product->image_url);
            }
            $product->image_url = $request->file('image')->store('products', 'public');
        }

        DB::transaction(function () use ($product, $hasVariants, $variantsPayload, $request) {
            $product->save();

            if ($hasVariants) {
                $product->variants->each(function (ProductVariant $variant) {
                    $variant->is_default = false;
                    $variant->save();
                });

                foreach ($variantsPayload as $variantData) {
                    $variant = $product->variants->firstWhere('id', $variantData['id']);
                    if (!$variant) {
                        continue;
                    }

                    $variant->unit_label = $variantData['unit_label'];
                    $variant->unit_price = $variantData['unit_price'];
                    $variant->sku = $variantData['sku'] ?? null;
                    $variant->barcode = $variantData['barcode'] ?? null;
                    $variant->is_default = !empty($variantData['is_default']);
                    $variant->save();
                }

                if (!$product->variants->firstWhere('is_default', true) && !empty($variantsPayload)) {
                    $fallbackId = $variantsPayload[0]['id'] ?? null;
                    if ($fallbackId) {
                        $fallback = $product->variants->firstWhere('id', $fallbackId);
                        if ($fallback) {
                            $fallback->is_default = true;
                            $fallback->save();
                        }
                    }
                }
            } else {
                $baseVariant = $product->variants->first();
                if ($baseVariant) {
                    if ($request->filled('unit_of_measurement')) {
                        $baseVariant->unit_label = $request->input('unit_of_measurement');
                    }
                    if ($request->filled('unit_price')) {
                        $baseVariant->unit_price = $request->input('unit_price');
                    }
                    $baseVariant->sku = $request->filled('sku') ? $request->input('sku') : $baseVariant->sku;
                    $baseVariant->is_default = true;
                    $baseVariant->save();
                }
            }

            $product->load('variants');
            ProductVariantAggregator::refresh($product);
        });

        return response()->json(['product' => $product->fresh('variants')]);
    }

    public function deducted(Request $request, $id)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'variant_id' => 'nullable|exists:product_variants,id',
        ]);

        $product = Product::with('variants')->find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        if ($product->variants->isEmpty()) {
            if ($product->quantity < $validated['quantity']) {
                return response()->json(['message' => 'Not enough stock to deduct.'], 400);
            }

            $product->quantity -= $validated['quantity'];
            $product->updated_at = now();
            $product->save();

            return response()->json(['product' => $product], 200);
        }

        $variant = $this->resolveVariantOrFail($product, $validated['variant_id'] ?? null);

        if ($variant->quantity < $validated['quantity']) {
            return response()->json(['message' => 'Not enough stock to deduct.'], 400);
        }

        $variant->quantity -= $validated['quantity'];
        $variant->save();

        ProductVariantAggregator::refresh($product);

        return response()->json(['product' => $product->fresh('variants')], 200);
    }

    private function resolveVariantOrFail(Product $product, ?int $variantId): ProductVariant
    {
        $product->loadMissing('variants');

        $variant = $variantId
            ? $product->variants->firstWhere('id', $variantId)
            : ($product->variants->firstWhere('is_default', true) ?? $product->variants->first());

        if (!$variant) {
            abort(404, 'Variant not found for this product.');
        }

        return $variant;
    }
}