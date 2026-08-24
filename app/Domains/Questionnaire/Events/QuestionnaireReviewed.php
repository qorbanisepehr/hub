<?php

namespace App\Domains\Questionnaire\Events;

use App\Events\BaseAuditEvent;
use Illuminate\Database\Eloquent\Model;

class QuestionnaireReviewed extends BaseAuditEvent
{
    public function __construct(
        private readonly Model $questionnaire,
    ) {}

    public function eventName(): string
    {
        return 'questionnaire.reviewed';
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
        return "Questionnaire {$this->questionnaire->getKey()} reviewed";
    }
}
