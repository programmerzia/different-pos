<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Enums\CardStatus;
use App\Exceptions\Domain\InvalidPartyData;
use App\Models\Customer;
use App\Models\MembershipCard;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;
use App\Support\Money\Money;
use Illuminate\Support\Carbon;

/**
 * Merges a duplicate customer into a survivor. The survivor keeps its customer_no and absorbs the
 * loser's sales, payments, returns and messages; balances are summed. The loser stays as a
 * soft-deleted shell (merged_into_id set, phones cleared so they can be reused, its number kept so
 * it is never reused) and its active card is revoked.
 */
final readonly class MergeCustomers
{
    public function handle(User $actor, Customer $survivor, Customer $loser): Customer
    {
        return Transaction::run(function () use ($actor, $survivor, $loser): Customer {
            if ($survivor->id === $loser->id) {
                throw InvalidPartyData::invalid('A customer cannot be merged into itself.');
            }

            // Lock in id order so two concurrent merges cannot deadlock.
            $locked = Customer::query()->whereIn('id', [$survivor->id, $loser->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $survivor = $locked->get($survivor->id) ?? throw InvalidPartyData::invalid('Customer not found.');
            $loser = $locked->get($loser->id) ?? throw InvalidPartyData::invalid('Customer not found.');

            if ($survivor->is_walk_in || $loser->is_walk_in) {
                throw InvalidPartyData::invalid('The walk-in customer cannot be merged.');
            }
            if ($survivor->merged_into_id !== null || $loser->merged_into_id !== null) {
                throw InvalidPartyData::invalid('A merged customer cannot take part in another merge.');
            }

            Sale::query()->where('customer_id', $loser->id)->update(['customer_id' => $survivor->id]);
            SaleReturn::query()->where('customer_id', $loser->id)->update(['customer_id' => $survivor->id]);
            Payment::query()->where('party_type', 'customer')->where('party_id', $loser->id)->update(['party_id' => $survivor->id]);
            Message::query()->where('customer_id', $loser->id)->update(['customer_id' => $survivor->id]);

            foreach (MembershipCard::query()->where('customer_id', $loser->id)->where('status', CardStatus::Active)->get() as $card) {
                $card->status = CardStatus::Revoked;
                $card->blocked_at = Carbon::now();
                $card->blocked_by = $actor->id;
                $card->block_reason = 'merged';
                $card->save();
            }

            $transferred = $loser->balance;
            $survivor->balance = $survivor->balance->plus($transferred);
            if ($loser->last_purchase_at !== null && ($survivor->last_purchase_at === null || $loser->last_purchase_at->gt($survivor->last_purchase_at))) {
                $survivor->last_purchase_at = $loser->last_purchase_at;
            }
            $survivor->updated_by = $actor->id;
            $survivor->save();

            $loser->balance = Money::zero();
            $loser->phone = null;
            $loser->alt_phone = null;
            $loser->is_active = false;
            $loser->merged_into_id = $survivor->id;
            $loser->updated_by = $actor->id;
            $loser->save();
            $loser->delete();

            Audit::log('customer.merged', $survivor, ['merged_customer_id' => $loser->id, 'balance_transferred' => $transferred->paisa], $actor);

            return $survivor;
        });
    }
}
