<?php

namespace App\Http\Controllers;

use App\Models\DamagedProduct;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ProductVariantAggregator;
use Exception;
use Illuminate\Http\Request;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

class DamagedProductController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'product_name' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
            'action_taken' => 'nullable|string|max:255',
            'date' => 'required|date',
            'logged_at' => 'nullable|date',
            'unit_of_measurement' => 'required|string|max:50',
            'variant_id' => 'nullable|integer',
        ]);

        if (empty($validated['logged_at'])) {
            $validated['logged_at'] = now();
        }

        $damagedProduct = DamagedProduct::create($validated);

        // Create a notification
        Notification::create([
            'type' => 'damaged_product_reported',
            'message' => "Damaged product reported: {$request->product_name} by {$request->customer_name}",
            'read' => false,
            'product_id' => null,
        ]);

        return response()->json([
            'message' => 'Damaged product recorded successfully!',
            'damagedProduct' => $damagedProduct
        ], 201);
    }

    public function stats()
    {
        $total = DamagedProduct::sum('quantity');
        $recent = DamagedProduct::orderBy('date', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'total_damaged' => $total,
            'recent_damages' => $recent
        ]);
    }

    public function index()
    {
        $damagedProducts = DamagedProduct::orderBy('created_at', 'desc')->get();
        return response()->json($damagedProducts);
    }

    public function show($id)
    {
        $damagedProduct = DamagedProduct::findOrFail($id);
        return response()->json($damagedProduct);
    }

    public function update(Request $request, $id)
    {
        $damagedProduct = DamagedProduct::findOrFail($id);

        $validated = $request->validate([
            'customer_name' => 'sometimes|string|max:255',
            'product_name' => 'sometimes|string|max:255',
            'quantity' => 'sometimes|integer|min:1',
            'reason' => 'sometimes|string|max:255',
            'action_taken' => 'nullable|string|max:255',
            'date' => 'sometimes|date',
            'logged_at' => 'nullable|date',
            'unit_of_measurement' => 'sometimes|string|max:50',
            'refunded' => 'sometimes|boolean',
            'refunded_at' => 'nullable|date',
            'variant_id' => 'nullable|integer',
        ]);

        $damagedProduct->update($validated);

        return response()->json([
            'message' => 'Damaged product updated successfully',
            'damagedProduct' => $damagedProduct,
        ]);
    }

    public function destroy($id)
    {
        $damagedProduct = DamagedProduct::findOrFail($id);
        $damagedProduct->delete();

        return response()->json([
            'message' => 'Damaged product deleted successfully',
        ]);
    }

    /**
     * Process refund for a damaged product
     */
    public function refund($id)
    {
        try {
            $damagedProduct = DamagedProduct::findOrFail($id);

            // Check if already refunded
            if ($damagedProduct->refunded) {
                return response()->json([
                    'message' => 'This product has already been refunded.'
                ], 400);
            }

            // Mark as refunded
            $damagedProduct->update([
                'refunded' => true,
                'refunded_at' => now(),
            ]);

            // Create notification
            Notification::create([
                'type' => 'product_refunded',
                'message' => "Refunded {$damagedProduct->quantity} {$damagedProduct->unit_of_measurement} of {$damagedProduct->product_name} to {$damagedProduct->customer_name}",
                'read' => false,
                'product_id' => null,
            ]);

            return response()->json([
                'message' => 'Refund processed successfully',
                'damagedProduct' => $damagedProduct
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to process refund',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deduct quantity from inventory when damage is refunded
     */
    public function deductFromInventory(Request $request)
    {
        $validated = $request->validate([
            'product_name' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'variant_id' => 'nullable|integer|exists:product_variants,id',
        ]);

        try {
            DB::beginTransaction();

            $product = null;

            // When variant_id is present, always deduct from that exact variant
            if (!empty($validated['variant_id'])) {
                $variant = ProductVariant::with('product')->find($validated['variant_id']);

                if (!$variant || !$variant->product) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Variant not found for the provided product.'
                    ], 404);
                }

                if ($variant->quantity < $validated['quantity']) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Insufficient quantity in the selected variant',
                        'available' => $variant->quantity,
                        'requested' => $validated['quantity'],
                    ], 400);
                }

                $variant->quantity -= $validated['quantity'];
                $variant->save();

                $product = $variant->product;
                ProductVariantAggregator::refresh($product);
            } else {
                // Fallback: deduct from base product quantity (non-variant products)
                $product = Product::where('name', $validated['product_name'])->first();

                if (!$product) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Product not found in inventory'
                    ], 404);
                }

                // If the product has variants but no variant_id was supplied, deduct from the default variant
                if ($product->variants()->exists()) {
                    $defaultVariant = $product->variants()->where('is_default', true)->first() ?? $product->variants()->first();

                    if (!$defaultVariant) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'No available variants to deduct from.'
                        ], 404);
                    }

                    if ($defaultVariant->quantity < $validated['quantity']) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Insufficient quantity in the default variant',
                            'available' => $defaultVariant->quantity,
                            'requested' => $validated['quantity'],
                        ], 400);
                    }

                    $defaultVariant->quantity -= $validated['quantity'];
                    $defaultVariant->save();

                    ProductVariantAggregator::refresh($product->fresh());
                } else {
                    if ($product->quantity < $validated['quantity']) {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'Insufficient quantity in inventory',
                            'available' => $product->quantity,
                            'requested' => $validated['quantity']
                        ], 400);
                    }

                    $product->quantity -= $validated['quantity'];
                    $product->save();
                }
            }

            Notification::create([
                'type' => 'inventory_deducted',
                'message' => "Deducted {$validated['quantity']} units of {$validated['product_name']} from inventory due to damage",
                'read' => false,
                'product_id' => $product?->id,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Inventory updated successfully',
                'product' => $product?->fresh('variants'),
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update inventory',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}