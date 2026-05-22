<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claude_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('epic_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('feature_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('task_id')->nullable()->constrained()->nullOnDelete();
            $table->string('daemon_url');
            $table->string('project_path');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claude_sessions');
    }
};
