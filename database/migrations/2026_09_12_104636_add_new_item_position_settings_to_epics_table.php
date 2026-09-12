<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('epics', function (Blueprint $table) {
            $table->boolean('new_features_to_top')->default(false)->after('environment');
            $table->boolean('new_tasks_to_top')->default(false)->after('new_features_to_top');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('epics', function (Blueprint $table) {
            $table->dropColumn(['new_features_to_top', 'new_tasks_to_top']);
        });
    }
};
