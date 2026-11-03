<?php

namespace App\Enums;

enum TestResultStatus: string
{
    case Untested = 'untested';
    case Passed = 'passed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Untested => 'Untested',
            self::Passed => 'Passed',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Untested => 'zinc',
            self::Passed => 'green',
            self::Failed => 'red',
        };
    }
}
