<?php

namespace App\Enums;

enum FeatureStatus: string
{
    case Todo = 'todo';
    case Active = 'active';
    case Done = 'done';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To Do',
            self::Active => 'Active',
            self::Done => 'Done',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Todo => 'zinc',
            self::Active => 'sky',
            self::Done => 'green',
            self::Archived => 'zinc',
        };
    }
}
