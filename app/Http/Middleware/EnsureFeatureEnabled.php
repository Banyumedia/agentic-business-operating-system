<?php

namespace App\Http\Middleware;

use App\Services\DynamicMenuRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    public function __construct(private readonly DynamicMenuRegistry $registry) {}

    public function handle(Request $request, Closure $next): Response
    {
        $module = $request->route('module');
        $submodule = $request->route('submodule');

        abort_unless(is_string($module) && $this->registry->hasModule($module), 404);
        abort_unless($this->registry->hasPath($module, is_string($submodule) ? $submodule : null), 404);
        abort_unless($this->registry->isModuleVisible($module), 403);

        $definition = $this->registry->routeDefinition($module, is_string($submodule) ? $submodule : null);
        abort_if($definition === null, 403);

        $request->attributes->set('screen_definition', $definition);

        return $next($request);
    }
}
