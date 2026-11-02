<?php

declare(strict_types=1);

namespace App\Actions\Cards;

use App\Enums\CardStatus;
use App\Exceptions\Domain\InvalidPartyData;
use App\Models\Customer;
use App\Models\MembershipCard;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;
use Illuminate\Support\Carbon;

/**
 * Replaces a lost or damaged card with a new active one (issue_no + 1). A lost card stays "lost";
 * a damaged (still active) card becomes "replaced". Either way the old code stops working.
 */
final readonly class ReissueCard
{
    public function __construct(private IssueCard $issue) {}

    public function handle(User $actor, MembershipCard $card, ?string $reason = null): MembershipCard
    {
        return Transaction::run(function () use ($actor, $card, $reason): MembershipCard {
            $customer = Customer::query()->whereKey($card->customer_id)->lockForUpdate()->firstOrFail();
            $card = MembershipCard::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();

            if (! in_array($card->status, [CardStatus::Active, CardStatus::Lost], true)) {
                throw InvalidPartyData::invalid('Only an active (damaged) or lost card can be reissued.');
            }
            if ($card->status === CardStatus::Lost
                && MembershipCard::query()->where('customer_id', $customer->id)->where('status', CardStatus::Active)->exists()) {
                throw InvalidPartyData::invalid('The customer already has a newer active card.');
            }

            if ($card->status === CardStatus::Active) {
                $card->status = CardStatus::Replaced;
                $card->blocked_at = Carbon::now();
                $card->blocked_by = $actor->id;
                $card->block_reason = $reason === null || trim($reason) === '' ? 'damaged' : mb_substr(trim($reason), 0, 255);
                $card->save();
            }

            $new = $this->issue->handle($actor, $customer);
            Audit::log('card.reissued', $new, ['replaces_card_id' => $card->id], $actor);

            return $new;
        });
    }
}
