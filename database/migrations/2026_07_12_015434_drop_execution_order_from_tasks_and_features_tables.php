<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('execution_order');
        });

        Schema::table('features', function (Blueprint $table) {
            $table->dropColumn('execution_order');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('execution_order')->nullable()->after('order_index');
        });

        Schema::table('features', function (Blueprint $table) {
            $table->unsignedInteger('execution_order')->nullable()->after('order_index');
        });
    }
};
