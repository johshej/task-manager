<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automated_tests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('test_scenario_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('file_path')->nullable();
            $table->string('kind', 50)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automated_tests');
    }
};
