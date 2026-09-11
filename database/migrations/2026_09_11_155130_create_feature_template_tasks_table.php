<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_template_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('feature_template_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('priority')->default(5);
            $table->boolean('tdd')->nullable();
            $table->text('ai_mode')->nullable();
            $table->string('environment')->nullable();
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_template_tasks');
    }
};
