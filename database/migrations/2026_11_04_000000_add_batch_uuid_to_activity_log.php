<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** spatie/laravel-activitylog 4 writes this column on every row (PHP 8.3 baseline, 04_TRD). */
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->uuid('batch_uuid')->nullable()->after('properties');
        });
    }
};
