<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_contexts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('test_matrix_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();

            $table->unique(['test_matrix_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_contexts');
    }
};
