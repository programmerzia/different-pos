<?php

declare(strict_types=1);

namespace App\Actions\Purchasing;

use App\Enums\AllocatableType;
use App\Enums\PartyType;
use App\Enums\PaymentDirection;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Exceptions\Domain\InvalidStockDocument;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Database\Transaction;
use App\Support\Money\Money;
use App\Support\Numbering\DocumentNumberer;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Pays a vendor from the current branch: a `payments` row (direction out, party vendor), its
 * allocations to purchases (given, or oldest due first), purchases paid/due/payment_status, and
 * vendors.balance −= amount. An unallocated remainder is an advance (the balance goes below the dues).
 *
 * @phpstan-type VendorAllocationInput array{purchase_id: int, amount: Money}
 * @phpstan-type PayVendorInput array{vendor_id: int, amount: Money, payment_method_id: int, allocations?: list<VendorAllocationInput>|null, paid_at?: CarbonInterface|null, reference?: string|null, note?: string|null}
 */
final readonly class PayVendor
{
    public function __construct(private DocumentNumberer $numberer, private TenantContext $context) {}

    /** @param  PayVendorInput  $input */
    public function handle(User $actor, array $input): Payment
    {
        if ($input['amount']->paisa <= 0) {
            throw InvalidStockDocument::nonPositivePayment();
        }

        return Transaction::run(function () use ($actor, $input): Payment {
            $method = PaymentMethod::query()->findOrFail($input['payment_method_id']);
            $vendorId = Vendor::query()->findOrFail($input['vendor_id'])->id;

            $number = $this->numberer->next('payment', $this->context->branchId());
            $vendor = Vendor::query()->whereKey($vendorId)->lockForUpdate()->firstOrFail();

            return $this->record(
                $actor,
                $vendor,
                $number,
                $input['amount'],
                $method,
                $input['allocations'] ?? null,
                $input['paid_at'] ?? null,
                $input['reference'] ?? null,
                $input['note'] ?? null,
            );
        });
    }

    /**
     * The write half of handle(), for Actions that already allocated the payment number and hold
     * the vendor row lock (ReceivePurchase with an immediate payment).
     *
     * @param  list<VendorAllocationInput>|null  $allocations  null = oldest due purchases first
     */
    public function record(
        User $actor,
        Vendor $vendor,
        string $number,
        Money $amount,
        PaymentMethod $method,
        ?array $allocations = null,
        ?CarbonInterface $paidAt = null,
        ?string $reference = null,
        ?string $note = null,
    ): Payment {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('PayVendor::record() must be called inside Transaction::run().');
        }

        if ($amount->paisa <= 0) {
            throw InvalidStockDocument::nonPositivePayment();
        }

        $plan = $allocations === null
            ? $this->planOldestFirst($vendor, $amount)
            : $this->planGiven($vendor, $amount, $allocations);

        $payment = Payment::query()->create([
            'number' => $number,
            'direction' => PaymentDirection::Out,
            'party_type' => PartyType::Vendor,
            'party_id' => $vendor->id,
            'payment_method_id' => $method->id,
            'amount' => $amount,
            'reference' => $reference,
            'paid_at' => $paidAt ?? now(),
            'received_by' => $actor->id,
            'note' => $note,
        ]);

        foreach ($plan as [$purchase, $share]) {
            PaymentAllocation::query()->create([
                'payment_id' => $payment->id,
                'allocatable_type' => AllocatableType::Purchase,
                'allocatable_id' => $purchase->id,
                'amount' => $share,
            ]);

            $purchase->paid = $purchase->paid->plus($share);
            $purchase->due = $purchase->total->minus($purchase->paid);
            $purchase->payment_status = $purchase->due->isZero() ? PaymentStatus::Paid : PaymentStatus::Partial;
            $purchase->updated_by = $actor->id;
            $purchase->save();
        }

        $vendor->balance = $vendor->balance->minus($amount);
        $vendor->save();

        return $payment;
    }

    /** @return list<array{Purchase, Money}> */
    private function planOldestFirst(Vendor $vendor, Money $amount): array
    {
        $purchases = Purchase::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', PurchaseStatus::Received)
            ->where('due', '>', 0)
            ->orderBy('purchased_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $left = $amount;
        $plan = [];
        foreach ($purchases as $purchase) {
            if ($left->isZero()) {
                break;
            }
            $share = $purchase->due->compareTo($left) < 0 ? $purchase->due : $left;
            $plan[] = [$purchase, $share];
            $left = $left->minus($share);
        }

        return $plan;
    }

    /**
     * @param  list<VendorAllocationInput>  $allocations
     * @return list<array{Purchase, Money}>
     */
    private function planGiven(Vendor $vendor, Money $amount, array $allocations): array
    {
        $ids = array_values(array_unique(array_map(static fn (array $allocation): int => $allocation['purchase_id'], $allocations)));
        $purchases = [];
        foreach (Purchase::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get() as $row) {
            $purchases[$row->id] = $row;
        }

        $sum = Money::zero();
        $plan = [];
        $planned = [];
        foreach ($allocations as $allocation) {
            $purchase = $purchases[$allocation['purchase_id']]
                ?? throw (new ModelNotFoundException)->setModel(Purchase::class, [$allocation['purchase_id']]);
            $share = $allocation['amount'];

            if ($purchase->vendor_id !== $vendor->id) {
                throw InvalidStockDocument::wrongVendor($purchase->number);
            }
            if ($purchase->status !== PurchaseStatus::Received) {
                throw InvalidStockDocument::notReceived($purchase->number);
            }
            if ($share->paisa <= 0) {
                throw InvalidStockDocument::nonPositivePayment();
            }

            $planned[$purchase->id] = ($planned[$purchase->id] ?? Money::zero())->plus($share);
            if ($planned[$purchase->id]->compareTo($purchase->due) > 0) {
                throw InvalidStockDocument::overpaid($purchase->number);
            }

            $sum = $sum->plus($share);
            $plan[] = [$purchase, $share];
        }

        if ($sum->compareTo($amount) > 0) {
            throw InvalidStockDocument::allocationsExceedPayment();
        }

        return $plan;
    }
}
