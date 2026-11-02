<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdatePreferencesRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/** PATCH /app/api/preferences: the signed-in user's own UI preferences (theme, scheme, density, locale, bn_digits). */
final class PreferencesController extends Controller
{
    public function update(UpdatePreferencesRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($user->can('updatePreferences', $user), 403);

        $preferences = array_merge($user->preferences ?? [], $request->validated());
        $user->forceFill(['preferences' => $preferences])->save();

        return response()->json(['preferences' => $preferences]);
    }
}
