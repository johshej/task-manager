<?php

namespace App\Enums;

enum FeatureStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Done = 'done';
    case Archived = 'archived';

    public function label(): string
    {
        return match($this) {
            self::Planned => 'Planned',
            self::Active => 'Active',
            self::Done => 'Done',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Planned => 'zinc',
            self::Active => 'sky',
            self::Done => 'green',
            self::Archived => 'zinc',
        };
    }
}
