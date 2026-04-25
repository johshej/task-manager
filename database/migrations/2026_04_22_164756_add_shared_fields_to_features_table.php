<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('features', function (Blueprint $table) {
            $table->boolean('tdd')->nullable()->after('order_index');
            $table->text('ai_mode')->nullable()->after('tdd');
            $table->string('environment', 100)->nullable()->after('ai_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('features', function (Blueprint $table) {
            $table->dropColumn(['tdd', 'ai_mode', 'environment']);
        });
    }
};
