<?php

use App\Models\Task;
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
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('execution_order')->nullable()->after('order_index');
            $table->boolean('tdd')->nullable()->after('execution_order');
            $table->text('ai_mode')->nullable()->after('tdd');
            $table->string('environment', 100)->nullable()->after('ai_mode');
        });

        // Initialize execution_order for existing tasks (sequential by created_at)
        $i = 0;
        Task::orderBy('created_at')->each(function ($task) use (&$i) {
            $task->updateQuietly(['execution_order' => $i++]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['execution_order', 'tdd', 'ai_mode', 'environment']);
        });
    }
};
