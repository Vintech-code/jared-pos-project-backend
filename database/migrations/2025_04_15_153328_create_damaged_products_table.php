<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damaged_products', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name');
            $table->string('product_name');
            $table->integer('quantity');
            $table->string('reason');
            $table->string('action_taken')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->date('date');
            $table->timestamp('logged_at')->nullable();
            $table->string('unit_of_measurement');
            $table->boolean('refunded')->default(false);
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            // Optional: Add indexes if needed
            // $table->index('variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damaged_products');
    }
};
