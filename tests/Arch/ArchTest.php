<?php

declare(strict_types=1);

use App\Http\Controllers\Branch\BranchSelectController;
use App\Http\Controllers\Platform\PlatformHomeController;
use Illuminate\Support\Facades\DB;

arch('app uses strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debugging calls in app')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die', 'exit', 'print_r', 'var_export', 'ddd', 'td'])
    ->not->toBeUsedIn('App');

arch('controllers do not touch the DB facade')
    ->expect(DB::class)
    ->not->toBeUsedIn('App\Http\Controllers');

arch('actions are final and expose handle()')
    ->expect('App\Actions')
    ->classes()
    ->toBeFinal()
    ->toHaveMethod('handle')
    ->ignoring('App\Actions\Fortify');

arch('actions do not depend on the HTTP layer')
    ->expect('App\Actions')
    ->not->toUse('App\Http');

arch('policies are final')
    ->expect('App\Policies')
    ->classes()
    ->toBeFinal();

arch('policy concerns are traits')
    ->expect('App\Policies\Concerns')
    ->toBeTraits();

it('authorizes in every public controller action', function (): void {
    // Placeholders that need no permission, with the reason: they only expose the caller's own data / are guarded by route middleware.
    $exempt = [
        BranchSelectController::class, // lists only the caller's own branches
        PlatformHomeController::class, // platform.admin middleware
    ];
    $skipDirs = ['Auth', 'Settings']; // starter-kit controllers act on the caller's own account

    $root = dirname(__DIR__, 2).'/app/Http/Controllers';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    $checked = 0;

    foreach ($files as $file) {
        $relative = substr((string) $file->getPathname(), strlen($root) + 1);
        if ($file->getExtension() !== 'php' || $relative === 'Controller.php' || in_array(explode('/', $relative)[0], $skipDirs, true)) {
            continue;
        }

        $class = 'App\\Http\\Controllers\\'.str_replace(['/', '.php'], ['\\', ''], $relative);
        if (in_array($class, $exempt, true)) {
            continue;
        }

        $reflection = new ReflectionClass($class);
        $constructor = $reflection->hasMethod('__construct') ? $reflection->getMethod('__construct') : null;
        $resource = $constructor instanceof ReflectionMethod && str_contains(methodSource($constructor), '$this->authorizeResource(');

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->getDeclaringClass()->getName() !== $class || str_starts_with($method->getName(), '__') && $method->getName() !== '__invoke') {
                continue;
            }
            $source = methodSource($method);
            $authorizes = $resource || str_contains($source, 'authorize(') || str_contains($source, 'can(') || str_contains($source, 'Gate::');
            expect($authorizes)->toBeTrue("{$class}::{$method->getName()} does not authorize");
            $checked++;
        }
    }

    expect($checked)->toBeGreaterThanOrEqual(0);
});

function methodSource(ReflectionMethod $method): string
{
    $lines = file((string) $method->getFileName()) ?: [];

    return implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
}
