<?php

use App\Domains\Authorization\Middleware\CheckPermission;
use App\Domains\Authorization\Middleware\CheckRole;
use App\Domains\Document\Exceptions\DocumentZipLimitExceeded;
use App\Http\Middleware\VerifyGrantAccess;
use App\Http\Middleware\VerifyServeGrant;
use App\Integrations\Farzin\FarzinUnavailableException;
use App\Integrations\Rahkaran\Exceptions\RahkaranException;
use App\Support\Exports\BundleTooLarge;
use App\Support\Exports\DocumentRowLimitExceeded;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(function ($request) {
            return '/';
        });
        $middleware->alias([
            'permission' => CheckPermission::class,
            'role' => CheckRole::class,
            'grant.access' => VerifyGrantAccess::class,
            'grant.serve' => VerifyServeGrant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => true,
        );

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $e->getPrevious() instanceof ModelNotFoundException) {
                return;
            }

            return response()->json([
                'message' => __('messages.not_found'),
            ], 404);
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            return response()->json([
                'message' => __('messages.permission_denied'),
            ], 403);
        });

        // PDF/Word renders in memory, so the kernel caps tabular documents
        // by row count; over the cap the caller must narrow filters or
        // switch to xlsx/csv (the queued path is pending the production
        // queue-driver decision).
        $exceptions->render(function (DocumentRowLimitExceeded $e, Request $request) {
            return response()->json([
                'message' => __('exports.row_limit_exceeded'),
            ], 422);
        });

        // The zip bundle is built on a temporary file before it streams, so
        // the kernel caps it by document count; over the cap the caller must
        // narrow the selection.
        $exceptions->render(function (DocumentZipLimitExceeded $e, Request $request) {
            return response()->json([
                'message' => __('document.zip_limit_exceeded'),
            ], 422);
        });

        // The kernel's own size refusal. 422 with the actual and the limit in
        // the message rather than a bare "too large": the caller can only fix
        // this by asking for less, and "which limit, and by how much" is what
        // tells them what to narrow.
        $exceptions->render(function (BundleTooLarge $e, Request $request) {
            return response()->json([
                'message' => $e->unit === 'bytes'
                    ? __('exports.bundle_too_large_bytes', ['size' => $e->actual, 'limit' => $e->limit])
                    : __('exports.bundle_too_large_entries', ['count' => $e->actual, 'limit' => $e->limit]),
                'actual' => $e->actual,
                'limit' => $e->limit,
                'unit' => $e->unit,
            ], 422);
        });

        // External-source failures never leak vendor details (V2 §37): the
        // integration converts them to its exception, the handler to HTTP.
        $exceptions->render(function (RahkaranException $e, Request $request) {
            report($e);

            return response()->json([
                'message' => __('messages.external_system_unavailable'),
            ], 503);
        });

        // Farzin sync failures stay opaque (V2 §37): the integration
        // converts connection errors to its exception, the handler to 503.
        $exceptions->render(function (FarzinUnavailableException $e, Request $request) {
            report($e);

            return response()->json([
                'message' => __('messages.external_system_unavailable'),
            ], 503);
        });
    })->create();
