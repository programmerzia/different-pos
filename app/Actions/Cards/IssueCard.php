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

/**
 * Issues a membership card. One active card per customer. card_code = "MC" + customer_no (8) +
 * issue_no (2 digits, increments per customer, 99 at most), e.g. MC1000001701.
 */
final readonly class IssueCard
{
    private const int MAX_ISSUES = 99;

    public function handle(User $actor, Customer $customer): MembershipCard
    {
        return Transaction::run(function () use ($actor, $customer): MembershipCard {
            $customer = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            if ($customer->customer_no === null) {
                throw InvalidPartyData::invalid('The customer has no customer number yet.');
            }
            if ($customer->is_walk_in || ! $customer->is_active || $customer->merged_into_id !== null) {
                throw InvalidPartyData::invalid('A card can only be issued to an active, non-walk-in customer.');
            }
            if (MembershipCard::query()->where('customer_id', $customer->id)->where('status', CardStatus::Active)->exists()) {
                throw InvalidPartyData::invalid('The customer already has an active card.');
            }

            $issueNo = (int) MembershipCard::query()->where('customer_id', $customer->id)->max('issue_no') + 1;
            if ($issueNo > self::MAX_ISSUES) {
                throw InvalidPartyData::invalid('The card issue limit for this customer is reached.');
            }

            $card = new MembershipCard([
                'customer_id' => $customer->id,
                'issue_no' => $issueNo,
                'card_code' => 'MC'.str_pad((string) $customer->customer_no, 8, '0', STR_PAD_LEFT).str_pad((string) $issueNo, 2, '0', STR_PAD_LEFT),
                'status' => CardStatus::Active,
                'issued_at' => now(),
                'issued_by' => $actor->id,
            ]);
            $card->save();
            $card->refresh();

            Audit::log('card.issued', $card, ['issue_no' => $issueNo], $actor);

            return $card;
        });
    }
}
