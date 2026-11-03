<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\MessageChannel;
use App\Enums\SmsBroadcastStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An ad-hoc SMS or WhatsApp broadcast from the messaging hub's composer: who it targets, the text and the estimate at send
 * time. Its `messages` rows (related_type = sms_broadcast) are created by FanOutSmsBroadcast.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $user_id
 * @property MessageChannel $channel
 * @property array{target: string, customer?: string|null, group_id?: int|null} $audience
 * @property string|null $template
 * @property string $body
 * @property int $recipient_count
 * @property Money $cost_paisa
 * @property string $idempotency_key
 * @property SmsBroadcastStatus $status
 * @property int $queued_count
 * @property int $skipped_count
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class SmsBroadcast extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id', 'channel', 'audience', 'template', 'body', 'recipient_count', 'cost_paisa', 'idempotency_key', 'status',
        'queued_count', 'skipped_count', 'completed_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'audience' => 'array',
            'cost_paisa' => MoneyCast::class,
            'status' => SmsBroadcastStatus::class,
            'recipient_count' => 'integer',
            'queued_count' => 'integer',
            'skipped_count' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
}
