<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An SMS in the send log.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $customer_id
 * @property string $phone
 * @property string $body
 * @property string $driver
 * @property MessageStatus $status
 * @property string|null $provider_ref
 * @property string|null $error
 * @property Carbon|null $sent_at
 * @property string $event
 * @property string|null $related_type
 * @property int|null $related_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Message extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id', 'phone', 'body', 'driver', 'status', 'provider_ref', 'error', 'sent_at', 'event',
        'related_type', 'related_id',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    protected function casts(): array
    {
        return [
            'status' => MessageStatus::class,
            'sent_at' => 'datetime',
        ];
    }
}
