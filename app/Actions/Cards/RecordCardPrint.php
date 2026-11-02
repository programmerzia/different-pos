<?php

declare(strict_types=1);

namespace App\Actions\Cards;

use App\Enums\CardPrintLayout;
use App\Enums\CardStatus;
use App\Exceptions\Domain\InvalidPartyData;
use App\Models\MembershipCard;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Database\Transaction;
use Illuminate\Support\Carbon;

/** Records that an active card was printed (count, time, layout). Lost or replaced cards are not printed. */
final readonly class RecordCardPrint
{
    public function handle(User $actor, MembershipCard $card, CardPrintLayout $layout): MembershipCard
    {
        return Transaction::run(function () use ($actor, $card, $layout): MembershipCard {
            $card = MembershipCard::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();
            if ($card->status !== CardStatus::Active) {
                throw InvalidPartyData::invalid('Only an active card can be printed.');
            }

            $card->print_count++;
            $card->last_printed_at = Carbon::now();
            $card->last_print_layout = $layout;
            $card->save();

            Audit::log('card.printed', $card, ['layout' => $layout->value], $actor);

            return $card;
        });
    }
}
