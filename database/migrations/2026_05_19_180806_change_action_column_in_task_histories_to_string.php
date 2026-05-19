<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_histories', function (Blueprint $table) {
            $table->string('action')->change();
        });
    }

    public function down(): void
    {
        Schema::table('task_histories', function (Blueprint $table) {
            $table->enum('action', [
                'created',
                'updated',
                'status_changed',
                'assigned',
                'priority_changed',
                'deleted',
                'note',
            ])->change();
        });
    }
};
