<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/** The Bangladesh geo reference: `geo:import` over database/data/geo (idempotent). */
final class GeoSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('geo:import', [], $this->command?->getOutput());
    }
}
