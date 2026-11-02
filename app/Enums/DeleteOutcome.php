<?php

declare(strict_types=1);

namespace App\Enums;

/** What a Delete action did: removed the row, or only deactivated it because something references it. */
enum DeleteOutcome: string
{
    case Deleted = 'deleted';
    case Deactivated = 'deactivated';
}
