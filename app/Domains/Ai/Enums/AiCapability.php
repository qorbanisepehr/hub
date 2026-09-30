<?php

namespace App\Domains\Ai\Enums;

/**
 * What a model row can be asked to do. Stored on `ai_models.capabilities`
 * so an agent binding can be checked against the model it points at
 * instead of being discovered on the first paid request.
 */
enum AiCapability: string
{
    case Text = 'text';
    case StructuredOutput = 'structured-output';
    case Embeddings = 'embeddings';
    case Vision = 'vision';
    case Transcription = 'transcription';
}
