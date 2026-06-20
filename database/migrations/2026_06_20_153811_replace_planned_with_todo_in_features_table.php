<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
            DB::statement("
                CREATE TABLE features_new (
                    id TEXT NOT NULL,
                    epic_id TEXT NOT NULL,
                    name TEXT NOT NULL,
                    description TEXT,
                    status TEXT CHECK(\"status\" IN ('todo', 'active', 'done', 'archived')) NOT NULL DEFAULT 'todo',
                    order_index INTEGER UNSIGNED NOT NULL DEFAULT 0,
                    execution_order INTEGER UNSIGNED,
                    tdd INTEGER,
                    ai_mode TEXT,
                    environment TEXT,
                    created_at TEXT,
                    updated_at TEXT,
                    PRIMARY KEY (id),
                    FOREIGN KEY (epic_id) REFERENCES epics(id) ON DELETE CASCADE
                )
            ");
            DB::statement("
                INSERT INTO features_new
                SELECT id, epic_id, name, description,
                    CASE WHEN status = 'planned' THEN 'todo' ELSE status END,
                    order_index, execution_order, tdd, ai_mode, environment, created_at, updated_at
                FROM features
            ");
            DB::statement('DROP TABLE features');
            DB::statement('ALTER TABLE features_new RENAME TO features');
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::table('features')->where('status', 'planned')->update(['status' => 'todo']);
            DB::statement("ALTER TABLE features MODIFY COLUMN status ENUM('todo', 'active', 'done', 'archived') NOT NULL DEFAULT 'todo'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
            DB::statement("
                CREATE TABLE features_old (
                    id TEXT NOT NULL,
                    epic_id TEXT NOT NULL,
                    name TEXT NOT NULL,
                    description TEXT,
                    status TEXT CHECK(\"status\" IN ('planned', 'active', 'done', 'archived')) NOT NULL DEFAULT 'planned',
                    order_index INTEGER UNSIGNED NOT NULL DEFAULT 0,
                    execution_order INTEGER UNSIGNED,
                    tdd INTEGER,
                    ai_mode TEXT,
                    environment TEXT,
                    created_at TEXT,
                    updated_at TEXT,
                    PRIMARY KEY (id),
                    FOREIGN KEY (epic_id) REFERENCES epics(id) ON DELETE CASCADE
                )
            ");
            DB::statement("
                INSERT INTO features_old
                SELECT id, epic_id, name, description,
                    CASE WHEN status = 'todo' THEN 'planned' ELSE status END,
                    order_index, execution_order, tdd, ai_mode, environment, created_at, updated_at
                FROM features
            ");
            DB::statement('DROP TABLE features');
            DB::statement('ALTER TABLE features_old RENAME TO features');
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement("ALTER TABLE features MODIFY COLUMN status ENUM('planned', 'todo', 'active', 'done', 'archived') NOT NULL DEFAULT 'planned'");
            DB::table('features')->where('status', 'todo')->update(['status' => 'planned']);
        }
    }
};
