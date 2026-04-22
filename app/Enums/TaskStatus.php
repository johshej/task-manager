<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case Doing = 'doing';
    case Blocked = 'blocked';
    case BuildingAutomatedTests = 'building_automated_tests';
    case RunningAutomatedTests = 'running_automated_tests';
    case Done = 'done';

    public function label(): string
    {
        return match($this) {
            self::Todo => 'To Do',
            self::Doing => 'In Progress',
            self::Blocked => 'Blocked',
            self::BuildingAutomatedTests => 'Building Tests',
            self::RunningAutomatedTests => 'Running Tests',
            self::Done => 'Done',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Todo => 'zinc',
            self::Doing => 'blue',
            self::Blocked => 'red',
            self::BuildingAutomatedTests => 'amber',
            self::RunningAutomatedTests => 'purple',
            self::Done => 'green',
        };
    }
}
