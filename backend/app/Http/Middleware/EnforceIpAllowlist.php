<?php

namespace App\Http\Middleware;

use App\Models\Webhook;
use App\Models\WorkspaceSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the workspace IP allowlist for incoming webhook requests.
 *
 * If the workspace has `allowed_ip_ranges` configured, the caller's IP
 * must match at least one CIDR range. Requests from unlisted IPs receive
 * a 403 — no indication of what allowlist is in effect.
 *
 * When `allowed_ip_ranges` is null or empty, all IPs are permitted.
 *
 * Designed for the webhook receiver route which carries the webhook UUID
 * as a route parameter named `uuid`.
 */
class EnforceIpAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        $uuid = $request->route('uuid');

        if (! $uuid) {
            return $next($request);
        }

        $webhook = Webhook::query()
            ->select('id')
            ->with([
                'workflow:id,workspace_id',
                'workflow.workspace:id',
                'workflow.workspace.setting:id,workspace_id,allowed_ip_ranges',
            ])
            ->where('uuid', $uuid)
            ->first();

        if (! $webhook) {
            return $next($request);
        }

        $setting = $webhook->workflow?->workspace?->setting;

        if (! $setting instanceof WorkspaceSetting) {
            return $next($request);
        }

        $ranges = $setting->allowed_ip_ranges;

        if (empty($ranges)) {
            return $next($request);
        }

        $callerIp = $request->ip();

        foreach ($ranges as $cidr) {
            if ($this->ipMatchesCidr($callerIp, $cidr)) {
                return $next($request);
            }
        }

        return response()->json(['error' => 'Forbidden.'], 403);
    }

    /**
     * Check whether an IP address falls within a CIDR range.
     *
     * Supports both IPv4 (e.g. 192.168.1.0/24) and bare IPs (e.g. 10.0.0.1).
     */
    private function ipMatchesCidr(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $bits] = explode('/', $cidr, 2);

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $mask = $bits === '0' ? 0 : (~0 << (32 - (int) $bits));

        return (ip2long($ip) & $mask) === (ip2long($subnet) & $mask);
    }
}
