<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('features', function (Blueprint $table) {
            $table->unsignedInteger('execution_order')->nullable()->after('order_index');
        });
    }

    public function down(): void
    {
        Schema::table('features', function (Blueprint $table) {
            $table->dropColumn('execution_order');
        });
    }
};
