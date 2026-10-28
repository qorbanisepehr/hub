<?php

namespace App\Domains\Ai\Value;

/**
 * A model paired with the provider that serves it, for the flat cross-provider
 * read.
 *
 * The pair is the smallest thing that can answer "which models can I pick for
 * this run": a model on its own cannot say which gateway it belongs to, and
 * that is the only question this list exists to answer. Named rather than
 * returned as a two-element array so the fields have somewhere to come from —
 * a `[$model, $provider]` shape works until someone has to remember which half
 * is which in a sort closure.
 *
 * It is not stored and not a document. It is a projection assembled at read
 * time, which is the honest description of what the flat list is: every active
 * model's entry across every active provider, flattened.
 */
final readonly class PickableModel
{
    public function __construct(
        public AiModel $model,
        public string $providerName,
    ) {}
}
