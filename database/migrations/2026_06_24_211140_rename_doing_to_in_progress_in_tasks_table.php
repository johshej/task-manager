<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
            DB::statement('
                CREATE TABLE tasks_new (
                    "id" varchar NOT NULL,
                    "feature_id" varchar NOT NULL,
                    "title" varchar NOT NULL,
                    "description" text,
                    "status" varchar CHECK ("status" IN (
                        \'todo\', \'in_progress\', \'blocked\',
                        \'building_automated_tests\', \'running_automated_tests\', \'done\',
                        \'merged_to_staging\', \'deployed_to_staging\',
                        \'merged_to_master\', \'deployed_to_master\'
                    )) NOT NULL DEFAULT \'todo\',
                    "priority" integer NOT NULL DEFAULT \'0\',
                    "assigned_to" varchar,
                    "order_index" integer NOT NULL DEFAULT \'0\',
                    "created_at" datetime,
                    "updated_at" datetime,
                    "execution_order" integer,
                    "tdd" tinyint(1),
                    "ai_mode" text,
                    "environment" varchar,
                    FOREIGN KEY ("feature_id") REFERENCES "features"("id") ON DELETE CASCADE,
                    FOREIGN KEY ("assigned_to") REFERENCES "users"("id") ON DELETE SET NULL,
                    PRIMARY KEY ("id")
                )
            ');
            DB::statement("INSERT INTO tasks_new SELECT id, feature_id, title, description,
                CASE WHEN status = 'doing' THEN 'in_progress' ELSE status END,
                priority, assigned_to, order_index, created_at, updated_at,
                execution_order, tdd, ai_mode, environment
                FROM tasks");
            DB::statement('DROP TABLE tasks');
            DB::statement('ALTER TABLE tasks_new RENAME TO tasks');
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement("UPDATE tasks SET status = 'in_progress' WHERE status = 'doing'");
            DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM(
                'todo', 'in_progress', 'blocked',
                'building_automated_tests', 'running_automated_tests', 'done',
                'merged_to_staging', 'deployed_to_staging',
                'merged_to_master', 'deployed_to_master'
            ) NOT NULL DEFAULT 'todo'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
            DB::statement('
                CREATE TABLE tasks_new (
                    "id" varchar NOT NULL,
                    "feature_id" varchar NOT NULL,
                    "title" varchar NOT NULL,
                    "description" text,
                    "status" varchar CHECK ("status" IN (
                        \'todo\', \'doing\', \'blocked\',
                        \'building_automated_tests\', \'running_automated_tests\', \'done\',
                        \'merged_to_staging\', \'deployed_to_staging\',
                        \'merged_to_master\', \'deployed_to_master\'
                    )) NOT NULL DEFAULT \'todo\',
                    "priority" integer NOT NULL DEFAULT \'0\',
                    "assigned_to" varchar,
                    "order_index" integer NOT NULL DEFAULT \'0\',
                    "created_at" datetime,
                    "updated_at" datetime,
                    "execution_order" integer,
                    "tdd" tinyint(1),
                    "ai_mode" text,
                    "environment" varchar,
                    FOREIGN KEY ("feature_id") REFERENCES "features"("id") ON DELETE CASCADE,
                    FOREIGN KEY ("assigned_to") REFERENCES "users"("id") ON DELETE SET NULL,
                    PRIMARY KEY ("id")
                )
            ');
            DB::statement("INSERT INTO tasks_new SELECT id, feature_id, title, description,
                CASE WHEN status = 'in_progress' THEN 'doing' ELSE status END,
                priority, assigned_to, order_index, created_at, updated_at,
                execution_order, tdd, ai_mode, environment
                FROM tasks");
            DB::statement('DROP TABLE tasks');
            DB::statement('ALTER TABLE tasks_new RENAME TO tasks');
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement("UPDATE tasks SET status = 'doing' WHERE status = 'in_progress'");
            DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM(
                'todo', 'doing', 'blocked',
                'building_automated_tests', 'running_automated_tests', 'done',
                'merged_to_staging', 'deployed_to_staging',
                'merged_to_master', 'deployed_to_master'
            ) NOT NULL DEFAULT 'todo'");
        }
    }
};
