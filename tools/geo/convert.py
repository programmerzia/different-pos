#!/usr/bin/env python3 -I
"""
Converts the open Bangladesh geo datasets into database/data/geo/*.json (the shape `php artisan geo:import` reads).

Sources (see docs/adr/0008-geo-reference-data.md for the licences):
  nuhil/bangladesh-geocode (MIT): divisions.json, districts.json, upazilas.json, unions.json (phpMyAdmin JSON export)
  ifahimreza/bangladesh-geojson (MIT for the administrative reference files): src/data/bd-postcodes.json

Usage: python3 -I tools/geo/convert.py <download-dir> <output-dir>
The download dir is untrusted input: only json.load touches it. Metro thanas come from tools/geo/metro_thanas.json (curated).
"""
import json
import re
import sys
from pathlib import Path

# Current official romanisations (2018 gazette) with the older spellings kept as search aliases.
DIVISION_NAMES = {
    'Chattagram': ('Chattogram', ['Chittagong']),
    'Barisal': ('Barishal', ['Barisal']),
}
DISTRICT_NAMES = {
    'Comilla': ('Cumilla', ['Comilla']),
    'Coxsbazar': ("Cox's Bazar", ['Coxs Bazar', 'Cox Bazar']),
    'Barisal': ('Barishal', ['Barisal']),
    'Jhalakathi': ('Jhalokati', ['Jhalakathi']),
    'Chattogram': ('Chattogram', ['Chittagong']),
    'Bogura': ('Bogura', ['Bogra']),
    'Jashore': ('Jashore', ['Jessore']),
    'Chapainawabganj': ('Chapainawabganj', ['Chapai Nawabganj', 'Nawabganj']),
    'Khagrachhari': ('Khagrachhari', ['Khagrachari']),
    'Moulvibazar': ('Moulvibazar', ['Maulvibazar']),
    'Sirajganj': ('Sirajganj', ['Sirajgonj']),
    'Netrokona': ('Netrokona', ['Netrakona']),
}
# ifahimreza district spellings -> nuhil spellings (for the post-code join).
POSTCODE_DISTRICT_ALIASES = {
    'Barishal': 'Barisal', "Cox's Bazar": 'Coxsbazar', 'Cumilla': 'Comilla', 'Jhalokati': 'Jhalakathi',
    'Khagrachari': 'Khagrachhari', 'Maulvibazar': 'Moulvibazar', 'Nawabganj': 'Chapainawabganj',
    'Chapinawabganj': 'Chapainawabganj', 'Sirajgonj': 'Sirajganj',
}


def table(path: Path) -> list[dict]:
    data = json.loads(path.read_text(encoding='utf-8'))
    for part in data:
        if isinstance(part, dict) and part.get('type') == 'table':
            return part['data']
    raise SystemExit(f'{path}: no table export found')


def norm(name: str) -> str:
    return re.sub(r'[^a-z0-9]+', '', name.lower().replace('sadar', '').replace('upazila', '').replace('thana', ''))


def main(src: Path, out: Path) -> None:
    out.mkdir(parents=True, exist_ok=True)

    divisions = []
    div_code = {}
    for row in table(src / 'divisions.json'):
        name, aliases = DIVISION_NAMES.get(row['name'], (row['name'], []))
        code = f"{int(row['id']):02d}"
        div_code[row['id']] = code
        divisions.append({'id': int(row['id']), 'code': code, 'name_en': name, 'name_bn': row['bn_name'], 'aliases': aliases})

    districts = []
    dist_code = {}
    dist_by_nuhil_name = {}
    for row in table(src / 'districts.json'):
        name, aliases = DISTRICT_NAMES.get(row['name'], (row['name'], []))
        code = f"{div_code[row['division_id']]}-{int(row['id']):02d}"
        dist_code[row['id']] = code
        dist_by_nuhil_name[row['name']] = code
        districts.append({'id': int(row['id']), 'code': code, 'division_code': div_code[row['division_id']], 'name_en': name, 'name_bn': row['bn_name'], 'aliases': aliases})

    upazilas = []
    upz_code = {}
    upz_lookup: dict[tuple[str, str], str] = {}
    for row in table(src / 'upazilas.json'):
        code = f"{dist_code[row['district_id']]}-{int(row['id']):03d}"
        upz_code[row['id']] = code
        upz_lookup[(dist_code[row['district_id']], norm(row['name']))] = code
        upazilas.append({'id': int(row['id']), 'code': code, 'district_code': dist_code[row['district_id']], 'type': 'upazila', 'name_en': row['name'], 'name_bn': row['bn_name'], 'city_name_en': None, 'aliases': []})

    metro = json.loads((Path(__file__).parent / 'metro_thanas.json').read_text(encoding='utf-8'))
    next_id = 1001
    for city in metro:
        district_code = dist_by_nuhil_name[city['district']]
        for i, thana in enumerate(city['thanas'], start=1):
            code = f"{district_code}-M{i:02d}"
            upz_lookup.setdefault((district_code, norm(thana['name_en'])), code)
            for alias in thana.get('aliases', []):
                upz_lookup.setdefault((district_code, norm(alias)), code)
            upazilas.append({'id': next_id, 'code': code, 'district_code': district_code, 'type': 'metro_thana', 'name_en': thana['name_en'], 'name_bn': thana['name_bn'], 'city_name_en': thana['city'], 'aliases': thana.get('aliases', [])})
            next_id += 1

    unions = []
    for row in table(src / 'unions.json'):
        upz = upz_code[row['upazilla_id']]
        unions.append({'id': int(row['id']), 'code': f"{upz}-{int(row['id']):04d}", 'upazila_code': upz, 'type': 'union', 'name_en': row['name'], 'name_bn': row['bn_name']})

    pc_src = json.loads((src / 'if-bd-postcodes.json').read_text(encoding='utf-8'))['postcodes']
    pc_districts = json.loads((src / 'if-bd-districts.json').read_text(encoding='utf-8'))
    pc_districts = pc_districts['districts'] if isinstance(pc_districts, dict) else pc_districts
    pc_dist_name = {d['id']: d['name'] for d in pc_districts}
    post_codes = []
    unmatched_upazila = 0
    seen = set()
    for i, row in enumerate(pc_src, start=1):
        name = row.get('district') or pc_dist_name[row['district_id']]
        nuhil_name = POSTCODE_DISTRICT_ALIASES.get(name, name)
        if nuhil_name not in dist_by_nuhil_name:
            raise SystemExit(f'post code district not found: {name}')
        district_code = dist_by_nuhil_name[nuhil_name]
        upazila_code = upz_lookup.get((district_code, norm(row['upazila'])))
        if upazila_code is None:
            unmatched_upazila += 1
        code = str(row['postCode']).strip()
        if not re.fullmatch(r'[0-9]{4}', code):
            raise SystemExit(f'bad post code: {row}')
        key = (district_code, row['postOffice'].strip(), code)
        if key in seen:
            continue
        seen.add(key)
        post_codes.append({'id': len(post_codes) + 1, 'district_code': district_code, 'upazila_code': upazila_code, 'post_office_en': row['postOffice'].strip(), 'post_office_bn': None, 'post_code': code})

    for name, rows in [('divisions', divisions), ('districts', districts), ('upazilas', upazilas), ('unions', unions), ('post_codes', post_codes)]:
        (out / f'{name}.json').write_text(json.dumps(rows, ensure_ascii=False, indent=0) + '\n', encoding='utf-8')
        print(f'{name}: {len(rows)}')
    print(f'post codes without an upazila match: {unmatched_upazila}')
    assert len(divisions) == 8 and len(districts) == 64 and len([u for u in upazilas if u['type'] == 'upazila']) >= 490


if __name__ == '__main__':
    main(Path(sys.argv[1]), Path(sys.argv[2]))
