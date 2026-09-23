<?php

namespace App\Domains\Reporting\Controllers;

use App\Domains\Reporting\ReportRequest;
use App\Domains\Reporting\Reports\UnifiedInbox\InboxQuery;
use App\Domains\Reporting\Reports\UnifiedInbox\UnifiedInboxReport;
use App\Domains\Reporting\Requests\UnifiedInboxReportRequest;
use App\Domains\Reporting\Resources\UnifiedInboxResource;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/reports/unified-inbox (V2 §63). Authorization happens BEFORE
 * any external source execution (V2 §28): the `permission:` middleware
 * gates the capability; unlike attendance, the inbox's window filter is
 * client-owned (it is a personal work list, not another person's data),
 * so no identity-derived scope narrowing is applied here.
 */
class UnifiedInboxReportController
{
    public function __construct(
        private readonly UnifiedInboxReport $report,
    ) {}

    public function __invoke(UnifiedInboxReportRequest $request): UnifiedInboxResource|JsonResponse
    {
        $actor = $request->user();

        if ($actor === null) {
            return response()->json(['message' => __('messages.permission_denied')], 401);
        }

        $result = $this->report->run(new ReportRequest(
            query: new InboxQuery(
                from: $request->inboxFrom(),
                to: $request->inboxTo(),
                limit: $request->inboxLimit(),
                sources: $request->inboxSources(),
                types: $request->inboxTypes(),
                statuses: $request->inboxStatuses(),
            ),
            actor: $actor,
        ));

        return new UnifiedInboxResource($result);
    }
}
