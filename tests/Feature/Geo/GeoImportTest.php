<?php

declare(strict_types=1);

use App\Models\Geo\GeoDistrict;
use App\Models\Geo\GeoDivision;
use App\Models\Geo\GeoPostCode;
use App\Models\Geo\GeoUnion;
use App\Models\Geo\GeoUpazila;
use App\Support\Geo\GeoCatalog;
use Illuminate\Support\Facades\DB;

/*
| `geo:import` over the small fixture set in tests/fixtures/geo (3 divisions, 5 districts, their
| upazilas incl. a few Dhaka / Chattogram metro thanas, 31 unions, 200 post codes) and the GeoCatalog
| read side (search chains, parent resolution, free-text matching).
*/

const GEO_FIXTURE = __DIR__.'/../../fixtures/geo';

function importGeo(): void
{
    test()->artisan('geo:import', ['--path' => GEO_FIXTURE])->assertSuccessful();
}

it('imports the fixture set with stable ids and hierarchical codes', function (): void {
    importGeo();

    expect(GeoDivision::query()->count())->toBe(3)
        ->and(GeoDistrict::query()->count())->toBe(5)
        ->and(GeoUpazila::query()->count())->toBe(48)
        ->and(GeoUnion::query()->count())->toBe(31)
        ->and(GeoPostCode::query()->count())->toBe(200);

    $dhaka = GeoDistrict::query()->where('name_en', 'Dhaka')->firstOrFail();
    expect($dhaka->division->name_en)->toBe('Dhaka')->and($dhaka->code)->toBe('06-47')->and($dhaka->name_bn)->toBe('ঢাকা');
    $mirpur = GeoUpazila::query()->where('name_en', 'Mirpur')->where('district_id', $dhaka->id)->firstOrFail();
    expect($mirpur->type)->toBe(GeoUpazila::TYPE_METRO_THANA)->and($mirpur->city_name_en)->toBe('Dhaka North City Corporation')->and($mirpur->id)->toBeGreaterThan(1000);
    expect(GeoDistrict::query()->where('name_en', 'Chattogram')->firstOrFail()->aliases)->toBe('Chittagong');
});

it('is idempotent and picks up changed names on a re-run', function (): void {
    importGeo();
    $before = ['d' => GeoDivision::query()->count(), 'u' => GeoUpazila::query()->count(), 'p' => GeoPostCode::query()->count()];
    DB::table('geo_upazilas')->where('name_en', 'Mirpur')->update(['name_en' => 'Renamed']);

    importGeo();

    expect(GeoDivision::query()->count())->toBe($before['d'])
        ->and(GeoUpazila::query()->count())->toBe($before['u'])
        ->and(GeoPostCode::query()->count())->toBe($before['p'])
        ->and(GeoUpazila::query()->where('name_en', 'Renamed')->exists())->toBeFalse()
        ->and(GeoUpazila::query()->where('name_en', 'Mirpur')->exists())->toBeTrue();
});

it('fails on a missing folder or a broken file and writes nothing', function (): void {
    $this->artisan('geo:import', ['--path' => '/nonexistent/geo'])->assertFailed();

    $dir = sys_get_temp_dir().'/geo-broken-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/divisions.json', '{"not": "a list"}');
    $this->artisan('geo:import', ['--path' => $dir])->assertFailed();
    expect(GeoDivision::query()->count())->toBe(0);
    array_map(unlink(...), glob($dir.'/*') ?: []);
    rmdir($dir);
});

it('searches across levels with the parent chain, in English, Bangla and old spellings', function (): void {
    importGeo();
    $catalog = app(GeoCatalog::class);

    $hits = $catalog->search('Mirpur');
    expect($hits[0]['level'])->toBe('upazilas')->and($hits[0]['name_en'])->toBe('Mirpur')
        ->and($hits[0]['chain']['district']['name_en'])->toBe('Dhaka')->and($hits[0]['chain']['division']['name_en'])->toBe('Dhaka');

    $bn = $catalog->search('কালাই');
    expect($bn[0]['name_en'])->toBe('Kalai')->and($bn[0]['chain']['district']['name_en'])->toBe('Joypurhat');

    $old = $catalog->search('Chittagong', 'districts');
    expect($old)->toHaveCount(1)->and($old[0]['name_en'])->toBe('Chattogram');

    $union = $catalog->search('', 'unions');
    expect($union)->toBe([]);
    $scoped = $catalog->search('a', 'upazilas', GeoDistrict::query()->where('name_en', 'Joypurhat')->value('id'));
    expect(collect($scoped)->pluck('chain.district.name_en')->unique()->all())->toBe(['Joypurhat']);

    $post = $catalog->search('59', 'post_codes');
    expect($post[0]['post_code'])->toStartWith('59')->and($post[0]['chain']['district'])->not->toBeNull();
});

it('resolves parents from the deepest child and rejects a broken chain', function (): void {
    importGeo();
    $catalog = app(GeoCatalog::class);
    $kalai = GeoUpazila::query()->where('name_en', 'Kalai')->firstOrFail();
    $union = GeoUnion::query()->where('upazila_id', $kalai->id)->firstOrFail();
    $dhaka = GeoDistrict::query()->where('name_en', 'Dhaka')->firstOrFail();

    expect($catalog->resolve(null, null, null, $union->id))->toBe([
        'geo_division_id' => $kalai->district->division_id, 'geo_district_id' => $kalai->district_id, 'geo_upazila_id' => $kalai->id, 'geo_union_id' => $union->id,
    ]);
    expect($catalog->resolve(null, $dhaka->id, $kalai->id, null))->toBeNull()
        ->and($catalog->resolve(999, null, null, null))->toBeNull()
        ->and($catalog->resolve(null, null, null, null))->toBe(['geo_division_id' => null, 'geo_district_id' => null, 'geo_upazila_id' => null, 'geo_union_id' => null]);

    expect($catalog->label($kalai->district_id, $kalai->id))->toBe('Kalai, Joypurhat')
        ->and($catalog->label($kalai->district_id, $kalai->id, 'bn'))->toBe($kalai->name_bn.', '.$kalai->district->name_bn)
        ->and($catalog->label($dhaka->id, null))->toBe('Dhaka');
});

it('matches free-text addresses to a district and upazila, or not at all', function (): void {
    importGeo();
    $catalog = app(GeoCatalog::class);
    $dhaka = GeoDistrict::query()->where('name_en', 'Dhaka')->firstOrFail();
    $mirpur = GeoUpazila::query()->where('name_en', 'Mirpur')->firstOrFail();

    expect($catalog->matchAddress('House 12, Road 3, Mirpur, Dhaka'))->toMatchArray(['geo_district_id' => $dhaka->id, 'geo_upazila_id' => $mirpur->id])
        ->and($catalog->matchAddress('Kalai Bazar'))->toMatchArray(['geo_upazila_id' => GeoUpazila::query()->where('name_en', 'Kalai')->value('id')])
        ->and($catalog->matchAddress('Agrabad, Chittagong'))->toMatchArray(['geo_district_id' => GeoDistrict::query()->where('name_en', 'Chattogram')->value('id'), 'geo_upazila_id' => null])
        // Kotwali exists in Dhaka and Chattogram: ambiguous without a district.
        ->and($catalog->matchAddress('Kotwali'))->toBeNull()
        ->and($catalog->matchAddress('Kotwali, Dhaka'))->toMatchArray(['geo_district_id' => $dhaka->id])
        ->and($catalog->matchAddress('Somewhere else entirely'))->toBeNull()
        ->and($catalog->matchAddress(''))->toBeNull()
        ->and($catalog->matchAddress(null))->toBeNull();
});
