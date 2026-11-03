<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Geo\GeoDivision;
use App\Support\Geo\GeoCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * GET /app/api/geo/search?q=&level=&parent= and GET /app/api/geo/{level}?parent=: the Bangladesh geo
 * reference for the LocationFields pickers. Global, read-only data, readable by every authenticated
 * tenant user. Responses carry an ETag made of the data version and the query, so a repeat request
 * answers 304 and browsers may keep the body for 30 days (private: the API sits behind the session).
 */
final class GeoController extends Controller
{
    private const int MAX_AGE = 30 * 24 * 3600;

    public function __construct(private readonly GeoCatalog $catalog) {}

    public function search(Request $request): JsonResponse|Response
    {
        Gate::authorize('viewAny', GeoDivision::class);

        $q = (string) $request->query('q', '');
        $level = $request->query('level');
        $level = is_string($level) && GeoCatalog::isLevel($level) ? $level : null;
        $parent = $this->parent($request);

        return $this->cached($request, 'search:'.$q.':'.($level ?? '*').':'.($parent ?? '*'), fn (): array => [
            'results' => $this->catalog->search($q, $level, $parent),
        ]);
    }

    public function level(Request $request, string $level): JsonResponse|Response
    {
        Gate::authorize('viewAny', GeoDivision::class);
        abort_unless(GeoCatalog::isLevel($level), 404);
        $parent = $this->parent($request);

        return $this->cached($request, $level.':'.($parent ?? '*'), fn (): array => [
            $level => $this->catalog->children($level, $parent),
        ]);
    }

    private function parent(Request $request): ?int
    {
        $parent = $request->query('parent');

        return is_string($parent) && preg_match('/^[0-9]{1,9}$/', $parent) === 1 ? (int) $parent : null;
    }

    /** @param callable(): array<string, mixed> $body */
    private function cached(Request $request, string $key, callable $body): JsonResponse|Response
    {
        $etag = '"geo-'.$this->catalog->version().'-'.substr(md5($key), 0, 16).'"';
        $headers = ['ETag' => $etag, 'Cache-Control' => 'private, max-age='.self::MAX_AGE];
        if (in_array($etag, array_map(trim(...), explode(',', (string) $request->headers->get('If-None-Match', ''))), true)) {
            return response('', 304, $headers);
        }

        return response()->json(['version' => $this->catalog->version(), ...$body()], 200, $headers);
    }
}
