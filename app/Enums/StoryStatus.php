<?php

namespace App\Enums;

enum StoryStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Failed = 'failed';
}
