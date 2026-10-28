<?php

namespace App\Domains\Questionnaire\Events;

use App\Events\BaseAuditEvent;
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

    /** Stable ID for listeners (never the full model graph — see §77–§78). */
    public function questionnaireId(): string
    {
        return (string) $this->questionnaire->getKey();
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
