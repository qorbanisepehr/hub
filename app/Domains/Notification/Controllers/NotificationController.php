<?php

namespace App\Domains\Notification\Controllers;

use App\Domains\Notification\Resources\NotificationResource;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * User-scoped notification API.
 *
 * Every endpoint reads through the authenticated user's notification
 * relationship — a notification ID alone never grants access to another
 * recipient's record (see architecture §17, §118–§119).
 */
final class NotificationController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->user()->notifications();

        // Boolean unread filter (boolean-table pattern: has + FILTER_VALIDATE_BOOLEAN).
        if ($request->has('unread')) {
            if (filter_var($request->input('unread'), FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNull('read_at');
            } else {
                $query->whereNotNull('read_at');
            }
        }

        return NotificationResource::collection(
            $query->latest()->paginate(ListQuery::perPage($request)),
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'count' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $notification = $request->user()->notifications()->whereKey($notification)->firstOrFail();

        // Idempotent: repeated calls on an already-read notification are a no-op.
        $notification->markAsRead();

        return response()->json([
            'data' => new NotificationResource($notification),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        // DB-level update; never loads unread notifications into PHP.
        $updated = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'data' => [
                'updated' => $updated,
            ],
        ]);
    }
}
