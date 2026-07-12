<?php

namespace App\Enums;

enum EpicStatus: string
{
    case New = 'new';
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'sky',
            self::Active => 'lime',
            self::Paused => 'amber',
            self::Archived => 'zinc',
        };
    }
}
