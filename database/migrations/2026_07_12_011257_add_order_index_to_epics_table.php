<?php

use App\Models\Epic;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epics', function (Blueprint $table) {
            $table->unsignedInteger('order_index')->default(0)->after('status');
        });

        // Preserve current display order (newest first) until manually reordered.
        $i = 0;
        Epic::orderByDesc('created_at')->each(function ($epic) use (&$i) {
            $epic->updateQuietly(['order_index' => $i++]);
        });
    }

    public function down(): void
    {
        Schema::table('epics', function (Blueprint $table) {
            $table->dropColumn('order_index');
        });
    }
};
