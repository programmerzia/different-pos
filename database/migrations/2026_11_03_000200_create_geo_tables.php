<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bangladesh geo reference (05_Schema §3a): global, read-only, no tenant_id, filled by `geo:import`.
 * `aliases` keeps the older romanisations (Chittagong, Comilla, Jessore …) so search and the legacy
 * address matcher find them. The four customer geo columns get their foreign keys here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_divisions', function (Blueprint $table): void {
            $table->smallIncrements('id');
            $table->string('code', 10)->unique();
            $table->string('name_en', 60);
            $table->string('name_bn', 60);
            $table->string('aliases', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('geo_districts', function (Blueprint $table): void {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('division_id');
            $table->string('code', 10)->unique();
            $table->string('name_en', 60);
            $table->string('name_bn', 60);
            $table->string('aliases', 255)->nullable();
            $table->timestamps();
            $table->foreign('division_id')->references('id')->on('geo_divisions')->restrictOnDelete();
            $table->index('division_id');
        });

        Schema::create('geo_upazilas', function (Blueprint $table): void {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('district_id');
            $table->string('code', 12)->unique();
            $table->string('type', 16); // upazila | metro_thana | city_corporation
            $table->string('name_en', 80);
            $table->string('name_bn', 80);
            $table->string('city_name_en', 80)->nullable();
            $table->string('aliases', 255)->nullable();
            $table->timestamps();
            $table->foreign('district_id')->references('id')->on('geo_districts')->restrictOnDelete();
            $table->index(['district_id', 'type']);
            $table->fullText(['name_en', 'name_bn']);
        });

        Schema::create('geo_unions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedSmallInteger('upazila_id');
            $table->string('code', 14)->unique();
            $table->string('type', 16); // union | ward | pourashava
            $table->string('name_en', 80);
            $table->string('name_bn', 80);
            $table->timestamps();
            $table->foreign('upazila_id')->references('id')->on('geo_upazilas')->restrictOnDelete();
            $table->index('upazila_id');
            $table->fullText(['name_en', 'name_bn']);
        });

        Schema::create('geo_post_codes', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedSmallInteger('upazila_id')->nullable();
            $table->unsignedSmallInteger('district_id');
            $table->string('post_office_en', 80);
            $table->string('post_office_bn', 80)->nullable();
            $table->char('post_code', 4);
            $table->timestamps();
            $table->foreign('upazila_id')->references('id')->on('geo_upazilas')->restrictOnDelete();
            $table->foreign('district_id')->references('id')->on('geo_districts')->restrictOnDelete();
            $table->index('post_code');
            $table->index('upazila_id');
            $table->unique(['district_id', 'post_office_en', 'post_code']);
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->foreign('geo_division_id')->references('id')->on('geo_divisions')->restrictOnDelete();
            $table->foreign('geo_district_id')->references('id')->on('geo_districts')->restrictOnDelete();
            $table->foreign('geo_upazila_id')->references('id')->on('geo_upazilas')->restrictOnDelete();
            $table->foreign('geo_union_id')->references('id')->on('geo_unions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropForeign(['geo_division_id']);
            $table->dropForeign(['geo_district_id']);
            $table->dropForeign(['geo_upazila_id']);
            $table->dropForeign(['geo_union_id']);
        });
        Schema::dropIfExists('geo_post_codes');
        Schema::dropIfExists('geo_unions');
        Schema::dropIfExists('geo_upazilas');
        Schema::dropIfExists('geo_districts');
        Schema::dropIfExists('geo_divisions');
    }
};
