<?php

declare(strict_types=1);

namespace App\Actions\Cards;

use App\Enums\CardStatus;
use App\Exceptions\Domain\InvalidPartyData;
use App\Models\MembershipCard;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;
use Illuminate\Support\Carbon;

/** Marks an active card lost; scanning it at the POS is blocked from now on. */
final readonly class ReportCardLost
{
    public function handle(User $actor, MembershipCard $card, ?string $reason = null): MembershipCard
    {
        return Transaction::run(function () use ($actor, $card, $reason): MembershipCard {
            $card = MembershipCard::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();
            if ($card->status !== CardStatus::Active) {
                throw InvalidPartyData::invalid('Only an active card can be reported lost.');
            }

            $card->status = CardStatus::Lost;
            $card->blocked_at = Carbon::now();
            $card->blocked_by = $actor->id;
            $card->block_reason = $reason === null || trim($reason) === '' ? 'lost' : mb_substr(trim($reason), 0, 255);
            $card->save();

            Audit::log('card.lost', $card, [], $actor);

            return $card;
        });
    }
}
