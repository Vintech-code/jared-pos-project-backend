<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Notification;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ProductVariantAggregator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::with('products')->get();
        return response()->json($customers);
    }

    public function purchase(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'customer' => 'nullable|array',
            'customer.name' => 'required_without:customer_id|string|max:255',
            'customer.phone' => 'nullable|string|max:15',
            'purchase_date' => 'nullable|date',
            'amount_paid' => 'required|numeric|min:0',
            'products' => 'required|array|min:1',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.variant_id' => 'required|exists:product_variants,id',
            'products.*.product_name' => 'required|string',
            'products.*.category' => 'required|string',
            'products.*.unit' => 'required|string',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.purchase_date' => 'nullable|date',
        ]);

        $reference = 'PUR-' . now()->format('YmdHis') . '-' . substr(uniqid('', true), -6);

        return DB::transaction(function () use ($validated, $reference) {
            $customerId = $validated['customer_id'] ?? null;

            $customer = $customerId
                ? Customer::findOrFail($customerId)
                : Customer::create([
                    'name' => $validated['customer']['name'],
                    'phone' => $validated['customer']['phone'] ?? null,
                    'purchase_date' => $validated['purchase_date'] ?? now(),
                ]);

            $products = collect($validated['products']);
            $variantIds = $products->pluck('variant_id')->unique()->all();
            $variants = ProductVariant::whereIn('id', $variantIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $aggregatorProductIds = [];
            $customerProductsPayload = [];

            foreach ($products as $product) {
                /** @var ProductVariant|null $variant */
                $variant = $variants->get($product['variant_id']);
                if (!$variant) {
                    abort(404, 'Variant not found for product.');
                }

                if ($variant->quantity < $product['quantity']) {
                    abort(422, 'Not enough stock for ' . $product['product_name']);
                }

                $variant->quantity -= $product['quantity'];
                $variant->save();

                $aggregatorProductIds[] = $variant->product_id;

                $customerProductsPayload[] = [
                    'product_name' => $product['product_name'],
                    'category' => $product['category'],
                    'unit' => $product['unit'],
                    'quantity' => $product['quantity'],
                    'purchase_date' => $product['purchase_date'] ?? $validated['purchase_date'] ?? now(),
                ];
            }

            if (!empty($customerProductsPayload)) {
                $customer->products()->createMany($customerProductsPayload);
            }

            // Refresh product aggregates once per product to reduce overhead
            $productIds = array_unique($aggregatorProductIds);
            if (!empty($productIds)) {
                $productsNeedingRefresh = Product::whereIn('id', $productIds)->get();
                foreach ($productsNeedingRefresh as $product) {
                    ProductVariantAggregator::refresh($product);
                }
            }

            Notification::create([
                'type' => 'customer_purchase',
                'message' => sprintf('Purchase %s processed for %s (%d items).', $reference, $customer->name, count($customerProductsPayload)),
                'read' => false,
            ]);

            return response()->json([
                'reference' => $reference,
                'customer' => $customer->fresh('products'),
                'items' => count($customerProductsPayload),
            ], 201);
        });
    }

    public function store(Request $request)
    {
        // Log the incoming request for debugging
        Log::info('Incoming customer request', $request->all());

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'nullable|string|max:15',
                'purchase_date' => 'required|date',
                'products' => 'required|array',
                'products.*.product_name' => 'required|string',
                'products.*.category' => 'required|string',
                'products.*.unit' => 'required|string',
                'products.*.quantity' => 'required|integer|min:1',
                'products.*.purchase_date' => 'nullable|date',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $customerData = collect($validated)->only(['name', 'phone', 'purchase_date'])->toArray();
            $customer = Customer::create($customerData);

            // Create related purchased products
            foreach ($validated['products'] as $product) {
                $customer->products()->create($product);
            }

            // Create notification
            Notification::create([
                'type' => 'customer_added',
                'message' => "New customer '{$customer->name}' added.",
                'read' => false,
            ]);

            DB::commit();

            // Return the created customer with products
            return response()->json($customer->load('products'), 201);

        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error('Error storing customer: ' . $ex->getMessage());
            return response()->json(['message' => 'Server error occurred'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        // Validate incoming data
        $validated = $request->validate([
            'products' => 'required|array',
            'products.*.product_name' => 'required|string',
            'products.*.category' => 'required|string',
            'products.*.unit' => 'required|string',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.purchase_date' => 'nullable|date',
        ]);

        // Remove all previous products (if you want to replace) or just add new ones
        // $customer->products()->delete(); // Uncomment if you want to replace all

        // If you want to append, just add new products:
        foreach ($validated['products'] as $product) {
            $customer->products()->create($product);
        }

        $customer->load('products');
        return response()->json($customer);
    }

    public function show($id)
    {
        $customer = Customer::with('products')->findOrFail($id);
        return response()->json($customer);
    }


}
