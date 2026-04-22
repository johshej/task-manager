<?php

namespace App\Enums;

enum HistoryAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case StatusChanged = 'status_changed';
    case Assigned = 'assigned';
    case PriorityChanged = 'priority_changed';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match($this) {
            self::Created => 'Created',
            self::Updated => 'Updated',
            self::StatusChanged => 'Status changed',
            self::Assigned => 'Assignee changed',
            self::PriorityChanged => 'Priority changed',
            self::Deleted => 'Deleted',
        };
    }
}
