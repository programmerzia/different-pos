# ADR 0008: Bangladesh geo reference data (sources, licences, shape)

Date: 2026-11-04. Status: accepted.

## Context
Customers carry a structured location (division → district → upazila/thana → optional union, post code; 05_Schema §3a). The reference data must be open, cover all 8 divisions / 64 districts / all upazilas in English and Bangla, include the metro thanas of Dhaka and Chattogram (where most customers live), and ship with the repo so `geo:import` works offline and reproducibly.

## Decisions
1. **Administrative levels** come from [nuhil/bangladesh-geocode](https://github.com/nuhil/bangladesh-geocode) (MIT, © 2014 Nuhil Mehdy): 8 divisions, 64 districts, 494 upazilas, 4,540 unions, each with `name` + `bn_name`. Its README states the names were taken from bangladesh.gov.bd, Wikipedia and Google Maps. The MIT text is reproduced below.
2. **Post codes** come from [ifahimreza/bangladesh-geojson](https://github.com/ifahimreza/bangladesh-geojson), file `src/data/bd-postcodes.json` (1,349 post offices with 4-digit codes). The repository licenses its *administrative reference files* (`bd-divisions/districts/upazilas/postcodes.json`, `dhaka-city.json`) under **MIT**; only its OpenStreetMap-derived layers (`bd-bazars`, `bd-major-routes`, ODbL) and the geoBoundaries boundaries (CC BY 4.0) carry other licences, and we do not use those files. Post offices are joined to our districts by name (12 district spellings aliased) and to upazilas by normalised name; 779 of 1,349 match an upazila, the rest keep the district only (the API falls back to the district's offices for an upazila without its own).
3. **Metro thanas** are curated by hand in `tools/geo/metro_thanas.json` (50 Dhaka Metropolitan Police thanas with their city corporation, 15 Chattogram Metropolitan Police thanas; EN + BN, old spellings as aliases). They are stored as `geo_upazilas` rows of `type = metro_thana` with ids from 1001 (the dataset's upazilas keep ids 1–494). "City corporation" rows are not created: a metro thana already carries `city_name_en`.
4. **Spellings.** Names use the 2018 gazette romanisations (Chattogram, Cumilla, Barishal, Bogura, Jashore, Jhalokati …); the older spellings (Chittagong, Comilla, Barisal, Bogra, Jessore …) are kept in the `aliases` column (added to 05_Schema §3a) so search and the legacy address matcher find them.
5. **Codes and ids are stable.** `code` is hierarchical: division `06`, district `06-47`, upazila `06-47-325` / metro thana `06-47-M01`, union `06-47-325-1234`. `geo:import` upserts by `code` (post codes by district + office + code) and writes the ids from the JSON, so every environment has the same ids and a re-run after a data fix changes only what changed.
6. **Pipeline.** Raw files are downloaded into an empty scratch folder (never into the repo), converted by `tools/geo/convert.py` (kept outside the download folder, runs with `python3 -I`, only `json.load` touches the input) into `database/data/geo/{divisions,districts,upazilas,unions,post_codes}.json`, and loaded by `php artisan geo:import` (the `GeoSeeder` calls it). Tests use a 3-division subset in `tests/fixtures/geo/`.
7. **Serving.** Global tables, no tenant_id, read-only. `GeoCatalog` caches per data version (default cache store; Redis when configured) and `/app/api/geo/*` answers with an ETag of version + query (`Cache-Control: private, max-age=2592000`, 304 on `If-None-Match`). Any active tenant user may read (`GeoDivisionPolicy`).

## Attribution
- bangladesh-geocode: "The MIT License (MIT). Copyright (c) 2014 Nuhil Mehdy. Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction … THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND."
- bangladesh-geojson (administrative reference data): "MIT License. Copyright (c) 2026 Fahim Reza and contributors." Same terms.
Both notices must stay with any redistribution of `database/data/geo/*.json`.

## Consequences
- 05_Schema §3a gains `aliases` on divisions/districts/upazilas and `geo_post_codes` UNIQUE(district_id, post_office_en, post_code); the four customer geo columns now have foreign keys.
- Verified data quality is "as published": a few upazila names differ from BBS (e.g. dataset `Karnafuli`), union coverage is the 2014 list. Corrections go into the JSON (then `geo:import`), never into the database by hand.
