<?php

namespace App\Enums;

enum FeatureStatus: string
{
    case Todo = 'todo';
    case Active = 'active';
    case Done = 'done';
    case Archived = 'archived';
    case MergedToStaging = 'merged_to_staging';
    case DeployedToStaging = 'deployed_to_staging';
    case MergedToMaster = 'merged_to_master';
    case DeployedToMaster = 'deployed_to_master';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To Do',
            self::Active => 'Active',
            self::Done => 'Done',
            self::Archived => 'Archived',
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
            self::Active => 'sky',
            self::Done => 'green',
            self::Archived => 'zinc',
            self::MergedToStaging => 'cyan',
            self::DeployedToStaging => 'teal',
            self::MergedToMaster => 'indigo',
            self::DeployedToMaster => 'lime',
        };
    }
}
