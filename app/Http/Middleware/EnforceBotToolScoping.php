<?php

namespace App\Http\Middleware;

use App\Models\HermesProfile;
use App\Services\Hermes\HermesProfileProvisioner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceBotToolScoping
{
    public function __construct(
        private readonly HermesProfileProvisioner $provisioner
    ) {}

    /**
     * Map HTTP routes to canonical tool names for enforcement.
     */
    private const ROUTE_TOOL_MAP = [
        'api.bot.tenant.context.show' => 'read_context',
        'api.bot.tenant.context.opt-in' => 'update_settings',
        'api.bot.tenant.capabilities' => 'read_context',
        'api.bot.tenant.settings' => 'update_settings',
        'api.bot.tenant.contacts' => 'create_transaction',
        'api.bot.tenant.deals' => 'create_transaction',
        'api.bot.tenant.destructive-action' => 'destructive_action',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var HermesProfile|null $profile */
        $profile = $request->attributes->get('bot_profile');

        if (! $profile) {
            return response()->json(['error' => 'Bot profile context missing'], 401);
        }

        $routeName = $request->route()?->getName();
        $toolName = self::ROUTE_TOOL_MAP[$routeName] ?? $this->inferToolFromRequest($request);

        // Fail-closed enforcement
        if (! $this->provisioner->isToolAllowed($profile->type, $toolName)) {
            return response()->json([
                'error' => "Aksi '{$toolName}' tidak diizinkan untuk profil bot tipe '{$profile->type}'.",
            ], 403);
        }

        return $next($request);
    }

    private function inferToolFromRequest(Request $request): string
    {
        $path = $request->path();
        if (str_contains($path, 'destructive-action')) {
            return 'destructive_action';
        }
        if (str_contains($path, 'settings') || str_contains($path, 'opt-in')) {
            return 'update_settings';
        }
        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('DELETE')) {
            return 'create_transaction';
        }

        return 'read_context';
    }
}
