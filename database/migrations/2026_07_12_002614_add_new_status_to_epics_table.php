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
                CREATE TABLE epics_new (
                    "id" varchar NOT NULL,
                    "name" varchar NOT NULL,
                    "description" text,
                    "status" varchar CHECK ("status" IN (
                        \'new\', \'active\', \'paused\', \'archived\'
                    )) NOT NULL DEFAULT \'new\',
                    "created_at" datetime,
                    "updated_at" datetime,
                    "repository_url" varchar,
                    "tdd" tinyint(1),
                    "ai_mode" text,
                    "environment" varchar,
                    PRIMARY KEY ("id")
                )
            ');
            DB::statement('INSERT INTO epics_new SELECT * FROM epics');
            DB::statement('DROP TABLE epics');
            DB::statement('ALTER TABLE epics_new RENAME TO epics');
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement("ALTER TABLE epics MODIFY COLUMN status ENUM(
                'new', 'active', 'paused', 'archived'
            ) NOT NULL DEFAULT 'new'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
            DB::statement("UPDATE epics SET status = 'active' WHERE status = 'new'");
            DB::statement('
                CREATE TABLE epics_new (
                    "id" varchar NOT NULL,
                    "name" varchar NOT NULL,
                    "description" text,
                    "status" varchar CHECK ("status" IN (
                        \'active\', \'paused\', \'archived\'
                    )) NOT NULL DEFAULT \'active\',
                    "created_at" datetime,
                    "updated_at" datetime,
                    "repository_url" varchar,
                    "tdd" tinyint(1),
                    "ai_mode" text,
                    "environment" varchar,
                    PRIMARY KEY ("id")
                )
            ');
            DB::statement('INSERT INTO epics_new SELECT * FROM epics');
            DB::statement('DROP TABLE epics');
            DB::statement('ALTER TABLE epics_new RENAME TO epics');
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement("UPDATE epics SET status = 'active' WHERE status = 'new'");
            DB::statement("ALTER TABLE epics MODIFY COLUMN status ENUM(
                'active', 'paused', 'archived'
            ) NOT NULL DEFAULT 'active'");
        }
    }
};
