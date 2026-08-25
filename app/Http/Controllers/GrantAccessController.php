<?php

namespace App\Http\Controllers;

use App\Enums\GrantPurpose;
use App\Enums\OtpContext;
use App\Http\Requests\VerifyAccessOtpRequest;
use App\Http\Responses\OtpResponder;
use App\Services\GrantResolver;
use App\Services\OtpService;
use App\Services\SessionGrantStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generic grant issuance for any entity in config/grants.php.
 *
 * Routes are `{entity}/{uuid}/request-access` and
 * `{entity}/{uuid}/verify-access-otp`; the entity is validated against the
 * registry before resolving the model by uuid.
 */
class GrantAccessController extends Controller
{
    use OtpResponder;

    public function __construct(
        private GrantResolver $grantResolver,
        private OtpService $otpService,
        private SessionGrantStore $sessionGrants,
    ) {}

    public function requestAccess(string $entity, string $uuid): JsonResponse
    {
        if (! $config = $this->grantResolver->config($entity)) {
            return response()->json(['message' => __('grants.unknown_entity')], 422);
        }

        $resource = $this->grantResolver->resolve($entity, $uuid);
        $channel = $config['channel'];

        $status = $this->otpService->sendWithCooldown($resource, $channel, OtpContext::AccessProtected);

        return $this->respondToSend($resource, $channel, OtpContext::AccessProtected, $status);
    }

    public function verifyAccessOtp(VerifyAccessOtpRequest $request, string $entity, string $uuid): JsonResponse
    {
        if (! $config = $this->grantResolver->config($entity)) {
            return response()->json(['message' => __('grants.unknown_entity')], 422);
        }

        $resource = $this->grantResolver->resolve($entity, $uuid);
        $channel = $config['channel'];
        $purpose = $request->enum('purpose', GrantPurpose::class) ?? GrantPurpose::View;

        $status = $this->otpService->attemptVerification($resource, $channel, OtpContext::AccessProtected, $request->validated('otp'));

        if ($response = $this->respondToVerification($resource, $channel, OtpContext::AccessProtected, $status)) {
            return $response;
        }

        $token = $this->otpService->issueGrant($resource, $channel, OtpContext::AccessProtected, $purpose);

        // Bind the grant to the session so header-less browser requests
        // (<img>, embed) can serve documents via their cookie.
        $this->sessionGrants->remember($resource->getOtpIdentifier(), $token);

        return response()->json([
            'access_token' => $token,
            'expires_in' => $this->otpService->getGrantExpiresIn($purpose),
            'message' => __('grants.access_granted'),
        ]);
    }

    public function exists(string $entity, string $uuid): Response
    {
        if (! $this->grantResolver->config($entity)) {
            return response()->json(['message' => __('grants.unknown_entity')], 422);
        }

        $this->grantResolver->resolve($entity, $uuid);

        return response()->noContent();
    }
}
