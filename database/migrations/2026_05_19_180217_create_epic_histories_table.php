<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epic_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('epic_id')->index();
            $table->foreignUuid('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('changed_by_token_id')->nullable()->constrained('personal_access_tokens')->nullOnDelete();
            $table->enum('actor_type', ['user', 'ai']);
            $table->string('actor_name')->nullable();
            $table->string('action');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epic_histories');
    }
};
