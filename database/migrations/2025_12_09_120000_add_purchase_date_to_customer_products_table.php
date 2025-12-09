<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_products', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_products', 'purchase_date')) {
                $table->date('purchase_date')->nullable()->after('quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_products', function (Blueprint $table) {
            if (Schema::hasColumn('customer_products', 'purchase_date')) {
                $table->dropColumn('purchase_date');
            }
        });
    }
};
