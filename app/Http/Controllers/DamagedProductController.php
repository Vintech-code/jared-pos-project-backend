<?php

namespace App\Http\Controllers;

use App\Models\DamagedProduct;
use App\Models\Product;
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
            'unit_of_measurement' => 'required|string|max:50',
        ]);

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
            'unit_of_measurement' => 'sometimes|string|max:50',
            'refunded' => 'sometimes|boolean',
            'refunded_at' => 'nullable|date',
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
        ]);

        try {
            DB::beginTransaction();

            // Find the product in inventory by name
            $product = Product::where('name', $validated['product_name'])->first();

            if (!$product) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Product not found in inventory'
                ], 404);
            }

            // Check if enough quantity available
            if ($product->quantity < $validated['quantity']) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Insufficient quantity in inventory',
                    'available' => $product->quantity,
                    'requested' => $validated['quantity']
                ], 400);
            }

            // Deduct quantity
            $product->quantity -= $validated['quantity'];
            $product->save();

            // Create notification
            Notification::create([
                'type' => 'inventory_deducted',
                'message' => "Deducted {$validated['quantity']} units of {$product->name} from inventory due to damage refund",
                'read' => false,
                'product_id' => $product->id,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Inventory updated successfully',
                'product' => $product
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update inventory',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}