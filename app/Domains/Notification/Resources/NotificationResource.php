<?php

namespace App\Domains\Notification\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Stable notification contract for the client (architecture §22).
 *
 * `type` is the canonical dot-notation business occurrence
 * ("questionnaire.submitted"), derived from the stored notification class
 * name. `title`/`body` are presentation strings resolved from
 * `notifications.{type}.title/body` translation keys (architecture §26); the
 * frontend never reconstructs meaning from raw IDs (§25).
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $type = $this->canonicalType();

        return [
            'id' => $this->id,
            'type' => $type,
            'title' => $this->translated($type, 'title'),
            'body' => $this->translated($type, 'body'),
            'data' => $this->data,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Map the stored notification class name to a canonical type:
     * QuestionnaireSubmittedNotification -> questionnaire.submitted,
     * EmployeeApprovalRequestedNotification -> employee.approval_requested.
     *
     * The spec's naming (§23/§97) is `{domain}.{action…}` where the
     * action part keeps underscores (approval_requested), so the class
     * name is snaked and split after the first word group.
     */
    private function canonicalType(): string
    {
        $class = class_basename($this->type);

        if (str_ends_with($class, 'Notification')) {
            $class = substr($class, 0, -strlen('Notification'));
        }

        $snake = Str::snake($class);

        return Str::contains($snake, '_')
            ? Str::before($snake, '_').'.'.Str::after($snake, '_')
            : $snake;
    }

    private function translated(string $type, string $part): ?string
    {
        $key = 'notifications.'.str_replace('.', '_', $type).'.'.$part;

        return Lang::has($key) ? __($key) : null;
    }
}
