<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('feature_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', [
                'todo',
                'doing',
                'blocked',
                'building_automated_tests',
                'running_automated_tests',
                'done',
            ])->default('todo');
            $table->unsignedInteger('priority')->default(0);
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
