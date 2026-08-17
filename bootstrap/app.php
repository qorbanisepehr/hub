<?php

use App\Domains\Authorization\Middleware\CheckPermission;
use App\Domains\Authorization\Middleware\CheckRole;
use App\Http\Middleware\VerifyGrantAccess;
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
    })->create();
