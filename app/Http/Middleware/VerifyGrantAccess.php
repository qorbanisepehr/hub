<?php

namespace App\Http\Middleware;

use App\Enums\GrantPurpose;
use App\Enums\OtpContext;
use App\Services\GrantResolver;
use App\Services\OtpService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyGrantAccess
{
    public function __construct(
        private GrantResolver $grantResolver,
        private OtpService $otpService,
    ) {}

    /**
     * Enforce a grant on a protected route.
     *
     * Signature: `grant.access:{entity},{purpose}` where {entity} is a key in
     * config/grants.php and {purpose} one of view|edit.
     */
    public function handle(Request $request, Closure $next, string $entity, string $purpose): Response
    {
        $config = $this->grantResolver->config($entity);

        if (! $config) {
            return response()->json(['message' => __('grants.unknown_entity')], 422);
        }

        $purpose = GrantPurpose::tryFrom($purpose);

        if (! $purpose) {
            return response()->json(['message' => __('grants.invalid_purpose')], 422);
        }

        $uuid = (string) $request->route('uuid');

        $resource = $this->grantResolver->resolve($entity, $uuid);

        $token = (string) $request->header('X-Access-Token', '');

        if (! $this->otpService->redeemGrant($resource, $config['channel'], OtpContext::AccessProtected, $token, $purpose)) {
            return response()->json(['message' => __('grants.access_denied')], 401);
        }

        $request->attributes->set('granted_entity', $entity);
        $request->attributes->set('granted_resource', $resource);

        return $next($request);
    }
}
