<?php

namespace App\Enums;

enum EpicStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';

    public function label(): string
    {
        return match($this) {
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Active => 'lime',
            self::Paused => 'amber',
            self::Archived => 'zinc',
        };
    }
}
