<?php

declare(strict_types=1);

use App\Casts\MoneyCast;
use App\Casts\QuantityCast;
use App\Exceptions\Domain\InvalidMoney;
use App\Support\Money\Money;
use App\Support\Quantity\Quantity;
use Illuminate\Database\Eloquent\Model;

/**
 * @property Money|null $total
 * @property Quantity|null $qty
 */
final class CastingTestModel extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['total' => MoneyCast::class, 'qty' => QuantityCast::class];
    }
}

function castingModel(): CastingTestModel
{
    return new CastingTestModel;
}

it('keeps allocation parts summing to the total for many random splits', function (): void {
    mt_srand(20261031);
    for ($i = 0; $i < 2000; $i++) {
        $total = mt_rand(-1_000_000, 1_000_000);
        $weights = array_map(fn (): int => mt_rand(0, 50), range(1, mt_rand(1, 8)));
        if (array_sum($weights) === 0) {
            $weights[0] = 1;
        }
        $parts = Money::ofPaisa($total)->allocate($weights);
        expect(array_sum(array_map(fn (Money $m): int => $m->paisa, $parts)))->toBe($total);
    }
});

it('does arithmetic without mutating', function (): void {
    $a = Money::ofPaisa(500);
    $b = $a->plus(Money::ofPaisa(250))->minus(Money::ofPaisa(50));

    expect($a->paisa)->toBe(500)
        ->and($b->paisa)->toBe(700)
        ->and($b->negated()->abs()->equals($b))->toBeTrue()
        ->and($a->compareTo($b))->toBe(-1)
        ->and(Money::zero()->isZero())->toBeTrue()
        ->and(Money::ofPaisa(-1)->isNegative())->toBeTrue();
});

it('rejects amounts beyond the shared safe range', function (): void {
    expect(fn () => Money::ofPaisa(Money::MAX_PAISA + 1))->toThrow(InvalidMoney::class)
        ->and(Money::ofPaisa(Money::MAX_PAISA)->paisa)->toBe(Money::MAX_PAISA);
});

it('serialises as amount + formatted', function (): void {
    expect(json_encode(Money::ofPaisa(123450), JSON_UNESCAPED_UNICODE))->toBe('{"amount":123450,"formatted":"৳1,234.50"}')
        ->and(json_encode(Quantity::parse('1.5')))->toBe('"1.500"');
});

it('casts money and quantity columns', function (): void {
    $model = castingModel();
    $model->setRawAttributes(['total' => '123450', 'qty' => '1.250']);

    expect($model->total?->paisa)->toBe(123450)
        ->and($model->qty?->milli)->toBe(1250);

    $model->total = Money::ofPaisa(99);
    $model->qty = Quantity::ofUnits(3);

    expect($model->getAttributes())->toMatchArray(['total' => 99, 'qty' => '3.000']);
});

it('keeps nulls null', function (): void {
    $model = castingModel();
    $model->setRawAttributes(['total' => null, 'qty' => null]);
    $model->total = null;

    expect($model->total)->toBeNull()->and($model->qty)->toBeNull();
});

it('refuses floats and strings on write', function (mixed $value): void {
    $model = castingModel();

    expect(fn () => $model->setAttribute('total', $value))->toThrow(InvalidArgumentException::class);
})->with([12.5, '12.50', 1250]);

it('refuses a non-integer money column on read', function (): void {
    $model = castingModel();
    $model->setRawAttributes(['total' => '12.50']);

    expect(fn () => $model->total)->toThrow(InvalidArgumentException::class);
});

it('refuses a plain number as a quantity', function (): void {
    $model = castingModel();

    expect(fn () => $model->setAttribute('qty', 2))->toThrow(InvalidArgumentException::class);
});
