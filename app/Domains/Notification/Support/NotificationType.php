<?php

namespace App\Domains\Notification\Support;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Canonical notification type mapping (architecture §23/§97).
 *
 * Maps a notification class name to its canonical dot-notation business
 * occurrence ("QuestionnaireSubmittedNotification" -> "questionnaire.submitted")
 * and resolves presentation strings from `notifications.{type}.title/body`
 * translation keys (§26). Shared by the API resource and broadcast payloads
 * so every surface speaks the same type/title/body contract.
 */
final class NotificationType
{
    /**
     * Map the stored notification class name to a canonical type:
     * QuestionnaireSubmittedNotification -> questionnaire.submitted,
     * EmployeeApprovalRequestedNotification -> employee.approval_requested.
     *
     * The spec's naming (§23/§97) is `{domain}.{action…}` where the
     * action part keeps underscores (approval_requested), so the class
     * name is snaked and split after the first word group.
     */
    public static function canonical(string $class): string
    {
        $name = class_basename($class);

        if (str_ends_with($name, 'Notification')) {
            $name = substr($name, 0, -strlen('Notification'));
        }

        $snake = Str::snake($name);

        return Str::contains($snake, '_')
            ? Str::before($snake, '_').'.'.Str::after($snake, '_')
            : $snake;
    }

    /**
     * Resolve a presentation segment (title/body) for the given canonical type.
     */
    public static function translated(string $type, string $part): ?string
    {
        $key = 'notifications.'.str_replace('.', '_', $type).'.'.$part;

        return Lang::has($key) ? __($key) : null;
    }
}
