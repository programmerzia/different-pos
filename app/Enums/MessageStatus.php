<?php

declare(strict_types=1);

namespace App\Enums;

enum MessageStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    /** WhatsApp click-to-chat: the text was handed to the user's own WhatsApp (wa.me link); delivery is not known. */
    case Handoff = 'handoff';
}
