<?php

declare(strict_types=1);

namespace App\Actions\Cards;

use App\Enums\CardStatus;
use App\Models\Customer;
use App\Models\MembershipCard;
use App\Models\User;
use App\Support\Database\Transaction;

/**
 * The customer's active membership card, issued on demand when there is none (every customer is a
 * member; the card studio prints by customer). A lost, replaced or revoked card is never reused:
 * the new one gets the next issue_no. Throws InvalidPartyData when no card can be issued.
 */
final readonly class EnsureActiveCard
{
    public function __construct(private IssueCard $issue) {}

    public function handle(User $actor, Customer $customer): MembershipCard
    {
        return Transaction::run(function () use ($actor, $customer): MembershipCard {
            // Same lock IssueCard takes: two prints of the same customer never issue twice.
            $locked = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $active = MembershipCard::query()->where('customer_id', $locked->id)->where('status', CardStatus::Active)->first();

            return $active ?? $this->issue->handle($actor, $locked);
        });
    }
}
