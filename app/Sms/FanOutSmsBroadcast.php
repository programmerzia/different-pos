<?php

declare(strict_types=1);

namespace App\Sms;

use App\Enums\SmsBroadcastStatus;
use App\Jobs\Middleware\WithTenantContext;
use App\Models\Customer;
use App\Models\Message;
use App\Models\SmsBroadcast;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Turns one SmsBroadcast into its per-recipient `messages` rows and SendSms jobs, CHUNK customers per
 * transaction. Safe to retry: a customer that already has a message for this broadcast is skipped, so a
 * crash part-way resumes where it stopped instead of sending twice. The body's {name}, {customer},
 * {shop}, {due}, {balance} and {number} are filled per customer at fan-out time.
 */
final class FanOutSmsBroadcast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const int CHUNK = 200;

    public int $tries = 3;

    public function __construct(public readonly int $broadcastId) {}

    /** @return list<object> */
    public function middleware(): array
    {
        return [new WithTenantContext];
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(BroadcastAudience $audience): void
    {
        $broadcast = SmsBroadcast::query()->findOrFail($this->broadcastId);
        if ($broadcast->status === SmsBroadcastStatus::Completed) {
            return;
        }
        $broadcast->forceFill(['status' => SmsBroadcastStatus::Sending])->save();

        $related = $broadcast->getMorphClass();
        $recipients = $audience->recipients($broadcast->audience)
            ->whereNotExists(static fn ($q) => $q->selectRaw('1')->from('messages')
                ->whereColumn('messages.customer_id', 'customers.id')
                ->where('messages.related_type', $related)
                ->where('messages.related_id', $broadcast->id));

        $recipients->chunkById(self::CHUNK, function ($customers) use ($broadcast, $audience): void {
            Transaction::run(function () use ($customers, $broadcast, $audience): void {
                /** @var Customer $customer */
                foreach ($customers as $customer) {
                    $text = Templates::render($broadcast->body, $audience->values($customer));
                    if (SendSms::enqueue(BroadcastAudience::EVENT, $customer->phone, $text, $customer, $broadcast, $broadcast->channel) instanceof Message) {
                        $broadcast->queued_count++;
                    } else {
                        $broadcast->skipped_count++;
                    }
                }
                $broadcast->save();
            });
        }, 'customers.id', 'id');

        Transaction::run(static function () use ($broadcast): void {
            $broadcast->forceFill(['status' => SmsBroadcastStatus::Completed, 'completed_at' => Carbon::now()])->save();
            Audit::log('sms.broadcast_sent', $broadcast, [
                'target' => $broadcast->audience['target'],
                'channel' => $broadcast->channel->value,
                'queued' => $broadcast->queued_count,
                'skipped' => $broadcast->skipped_count,
            ]);
        });
    }
}
