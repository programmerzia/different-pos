<?php

declare(strict_types=1);

namespace App\Enums;

/** queued: recorded, fan-out job pending; sending: the job is creating the messages; completed: every recipient has its message row. */
enum SmsBroadcastStatus: string
{
    case Queued = 'queued';
    case Sending = 'sending';
    case Completed = 'completed';
}
