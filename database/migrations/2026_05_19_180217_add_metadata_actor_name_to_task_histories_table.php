<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_histories', function (Blueprint $table) {
            $table->string('actor_name')->nullable()->after('actor_type');
            $table->json('metadata')->nullable()->after('new_values');
        });
    }

    public function down(): void
    {
        Schema::table('task_histories', function (Blueprint $table) {
            $table->dropColumn(['actor_name', 'metadata']);
        });
    }
};
