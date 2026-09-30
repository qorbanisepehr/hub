<?php

namespace App\Domains\Ai\Enums;

/**
 * The laravel/ai driver a provider row speaks through, not the provider's
 * brand: an OpenAI-compatible gateway and OpenAI itself are the same driver
 * with a different `base_url`, which is what makes a BYOK row portable.
 *
 * There is no `fake` case on purpose. A fake driver would answer from a stub
 * gateway and prove nothing about the wiring, while Ollama answers for real
 * on a developer's own machine with no credential at all — so it is both the
 * zero-config path and the honest one. Determinism is a test concern and
 * belongs to the SDK's `Agent::fake()` facility, not to the registry.
 */
enum AiDriver: string
{
    case Ollama = 'ollama';
    case OpenAiCompatible = 'openai-compatible';
    case OpenAI = 'openai';
    case Anthropic = 'anthropic';
    case Gemini = 'gemini';
    case OpenRouter = 'openrouter';
}
