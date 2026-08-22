<?php

namespace App\Domains\Questionnaire\Events;

use App\Events\BaseAuditEvent;
use Illuminate\Database\Eloquent\Model;

class QuestionnaireRejected extends BaseAuditEvent
{
    public function __construct(
        private readonly Model $questionnaire,
        private readonly ?string $reason = null,
    ) {}

    public function eventName(): string
    {
        return 'questionnaire.rejected';
    }

    public function category(): string
    {
        return 'questionnaire';
    }

    public function subject(): ?array
    {
        return [
            'type' => 'questionnaire',
            'id' => $this->questionnaire->getKey(),
        ];
    }

    public function description(): ?string
    {
        return "Questionnaire {$this->questionnaire->getKey()} rejected";
    }

    public function changes(): ?array
    {
        return $this->reason ? ['reason' => $this->reason] : null;
    }
}
