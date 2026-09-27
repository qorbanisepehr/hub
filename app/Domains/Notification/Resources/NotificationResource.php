<?php

namespace App\Domains\Notification\Resources;

use App\Domains\Notification\Support\NotificationType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

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
        $type = NotificationType::canonical($this->type);

        return [
            'id' => $this->id,
            'type' => $type,
            'title' => NotificationType::translated($type, 'title'),
            'body' => NotificationType::translated($type, 'body'),
            'data' => $this->data,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
