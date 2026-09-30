<?php

namespace App\Domains\Ai\Enums;

/**
 * The laravel/ai driver a provider row speaks through, not the provider's
 * brand: an OpenAI-compatible gateway and OpenAI itself are the same driver
 * with a different `base_url`, which is what makes a BYOK row portable.
 */
enum AiDriver: string
{
    case Fake = 'fake';
    case OpenAiCompatible = 'openai-compatible';
    case Ollama = 'ollama';
    case OpenAI = 'openai';
    case Anthropic = 'anthropic';
    case Gemini = 'gemini';
    case OpenRouter = 'openrouter';
}
