<?php

namespace App\Enums;

enum ActorType: string
{
    case User = 'user';
    case Ai = 'ai';

    public function label(): string
    {
        return match($this) {
            self::User => 'User updated',
            self::Ai => 'AI updated',
        };
    }
}
