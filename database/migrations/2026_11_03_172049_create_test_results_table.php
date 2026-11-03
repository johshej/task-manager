<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('test_scenario_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('test_context_id')->constrained()->cascadeOnDelete();
            $table->boolean('relevant')->default(true);
            $table->string('status')->default('untested');
            $table->string('expectation')->nullable();
            $table->timestamp('tested_at')->nullable();
            $table->timestamps();

            $table->unique(['test_scenario_id', 'test_context_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_results');
    }
};
