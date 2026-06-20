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
    case MergedToStaging = 'merged_to_staging';
    case DeployedToStaging = 'deployed_to_staging';
    case MergedToMaster = 'merged_to_master';
    case DeployedToMaster = 'deployed_to_master';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To Do',
            self::Doing => 'In Progress',
            self::Blocked => 'Blocked',
            self::BuildingAutomatedTests => 'Building Tests',
            self::RunningAutomatedTests => 'Running Tests',
            self::Done => 'Done',
            self::MergedToStaging => 'Merged to Staging',
            self::DeployedToStaging => 'Deployed to Staging',
            self::MergedToMaster => 'Merged to Master',
            self::DeployedToMaster => 'Deployed to Master',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Todo => 'zinc',
            self::Doing => 'blue',
            self::Blocked => 'red',
            self::BuildingAutomatedTests => 'amber',
            self::RunningAutomatedTests => 'purple',
            self::Done => 'green',
            self::MergedToStaging => 'cyan',
            self::DeployedToStaging => 'teal',
            self::MergedToMaster => 'indigo',
            self::DeployedToMaster => 'lime',
        };
    }
}
