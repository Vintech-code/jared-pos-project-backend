<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('damaged_products', function (Blueprint $table) {
            if (!Schema::hasColumn('damaged_products', 'action_taken')) {
                $table->string('action_taken')->nullable()->after('reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('damaged_products', function (Blueprint $table) {
            if (Schema::hasColumn('damaged_products', 'action_taken')) {
                $table->dropColumn('action_taken');
            }
        });
    }
};
