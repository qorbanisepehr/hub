<?php

namespace App\Domains\Tagging;

use App\Domains\BoardMeeting\Models\BoardMeeting;

/**
 * Single source of truth for taggable route-type ↔ model-class mapping and
 * the extra permission retagging one entity requires (P5, review 2026-09-27).
 * Tagging never borrows document vocabulary (was DocumentRouteType) and
 * TagController resolves through this one map instead of re-declaring pairs.
 *
 * The extra permission exists so `tag.manage` alone cannot retag arbitrary
 * entities: the actor also needs write access on the taggable itself.
 * Entities not listed here keep the legacy behaviour (tag.manage only) —
 * add them explicitly when they gain attach support.
 */
final class TaggableType
{
    /** @var array<string, array{class: class-string, update_permission: string}> */
    private const MAP = [
        'board-meeting' => [
            'class' => BoardMeeting::class,
            'update_permission' => 'board-meeting.update',
        ],
    ];

    /**
     * Resolve a route-type slug to its taggable class. Returns null for
     * unknown slugs — callers decide how to fail.
     */
    public static function classFor(string $routeType): ?string
    {
        return self::MAP[$routeType]['class'] ?? null;
    }

    /**
     * The entity-level permission retagging this type additionally requires,
     * null when the type opts out (not currently listed).
     */
    public static function updatePermissionFor(string $routeType): ?string
    {
        return self::MAP[$routeType]['update_permission'] ?? null;
    }
}
