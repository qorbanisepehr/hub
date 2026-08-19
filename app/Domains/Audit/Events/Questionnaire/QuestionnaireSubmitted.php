<?php

namespace App\Domains\Audit\Events\Questionnaire;

use App\Domains\Audit\Events\BaseAuditEvent;
use Illuminate\Database\Eloquent\Model;

class QuestionnaireSubmitted extends BaseAuditEvent
{
    public function __construct(
        private readonly Model $questionnaire,
    ) {}

    public function eventName(): string
    {
        return 'questionnaire.submitted';
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
        return "Questionnaire {$this->questionnaire->getKey()} submitted";
    }
}
