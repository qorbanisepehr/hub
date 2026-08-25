<?php

namespace App\Http\Middleware;

use App\Contracts\Authorization;
use App\Contracts\OtpVerifiable;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentUsage;
use App\Enums\GrantPurpose;
use App\Enums\OtpContext;
use App\Services\GrantResolver;
use App\Services\OtpService;
use App\Services\SessionGrantStore;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorize document serving for OTP-grant entities (CV, questionnaire).
 *
 * Serving must work for browser-native requests (<img>, embed) that cannot
 * send headers, so three credential channels are accepted in order:
 *
 *  1. An authenticated user holding the entity's view permission (HR side).
 *  2. An X-Access-Token header whose grant covers "view" (XHR clients).
 *  3. A grant token bound to the session when the applicant verified their
 *     OTP — same-origin <img>/embed requests carry that session cookie.
 *
 * Anything else gets a 401; an authenticated but unauthorized user gets the
 * standard 403 from the authorization service.
 */
class VerifyServeGrant
{
    public function __construct(
        private readonly GrantResolver $grantResolver,
        private readonly OtpService $otpService,
        private readonly Authorization $authorization,
        private readonly SessionGrantStore $sessionGrants,
    ) {}

    public function handle(Request $request, Closure $next, string $entity): Response
    {
        if (! $config = $this->grantResolver->config($entity)) {
            return response()->json(['message' => __('grants.unknown_entity')], 422);
        }

        $owner = $this->resolveOwner((string) $request->route('uuid'), $config['model']);

        if ($request->user()) {
            $this->authorization->authorize($request->user(), $config['view_permission'], $owner);

            return $next($request);
        }

        $token = (string) $request->headers->get('X-Access-Token', '');

        if ($token === '') {
            $token = $this->sessionGrants->token($owner->getOtpIdentifier()) ?? '';
        }

        if (! $this->otpService->redeemGrant(
            $owner,
            $config['channel'],
            OtpContext::AccessProtected,
            $token,
            GrantPurpose::View,
        )) {
            return response()->json(['message' => __('grants.access_denied')], 401);
        }

        return $next($request);
    }

    /**
     * The entity owning the served document's most recent active usage. A
     * uuid pointing at a missing/unattached document or one owned by another
     * entity type is indistinguishable from a missing resource (404).
     *
     * @param  class-string<OtpVerifiable>&class-string<Model>  $modelClass
     */
    private function resolveOwner(string $documentUuid, string $modelClass): OtpVerifiable&Model
    {
        $document = Document::query()
            ->where('uuid', $documentUuid)
            ->whereHas('usages')
            ->firstOrFail();

        /** @var DocumentUsage|null $usage */
        $usage = $document->usages()->latest('id')->first();

        if ($usage === null || $usage->entity_type !== $modelClass) {
            abort(404);
        }

        return $modelClass::query()->findOrFail($usage->entity_id);
    }
}
