<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epic_template_features', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('epic_template_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('feature_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epic_template_features');
    }
};
