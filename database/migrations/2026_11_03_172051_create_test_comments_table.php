<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('test_matrix_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('test_scenario_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('task_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('test_comment_test_context', function (Blueprint $table) {
            $table->foreignUuid('test_comment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('test_context_id')->constrained()->cascadeOnDelete();

            $table->primary(['test_comment_id', 'test_context_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_comment_test_context');
        Schema::dropIfExists('test_comments');
    }
};
