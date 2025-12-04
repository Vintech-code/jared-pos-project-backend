<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('sku')->nullable()->unique();
            $table->string('unit_label');
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('conversion_factor', 10, 4)->default(1);
            $table->string('barcode')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('hidden')->default(false);
            $table->timestamps();
        });

        // Seed the variants table with existing product data for backwards compatibility.
        $products = DB::table('products')->get();

        foreach ($products as $product) {
            DB::table('product_variants')->insert([
                'product_id' => $product->id,
                'sku' => $product->sku,
                'unit_label' => $product->unit_of_measurement,
                'unit_price' => $product->unit_price,
                'quantity' => $product->quantity,
                'conversion_factor' => 1,
                'barcode' => null,
                'is_default' => true,
                'hidden' => (bool) $product->hidden,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
