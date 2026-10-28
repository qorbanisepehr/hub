<?php

namespace App\Contracts\Ai;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;

/**
 * An agent whose structured output is a named set of fields, any subset of
 * which one run may be asked to fill.
 *
 * This interface exists because of a boundary, not for symmetry. The runner
 * builds the agent class from a registry row and knows nothing about which
 * domain owns it (D28), so the only way a per-run instruction such as "only
 * the decisions this time" can reach the agent is through a contract the
 * runner and the agent both speak. The two alternatives are worse: reading
 * the subset in the runner would make the Ai domain name BoardMeeting's
 * vocabulary, and leaving it in the prompt would make the JSON schema declare
 * fields the run does not want — a model asked for three fields in prose
 * still pays to write five, which is exactly the cost D26 exists to avoid.
 *
 * The contract is deliberately tiny and it says nothing about *which* fields.
 * The vocabulary stays with the domain that owns the concept: a board
 * meeting's `key_decisions` and a CV's are the same word and different
 * things, and a shared list would quietly merge them. What is genuinely
 * shared — declare a set of fields, narrow it per run, let the schema follow
 * — is the mechanism, and that is all this declares.
 */
interface FieldedAgent extends Agent, HasStructuredOutput
{
    /**
     * Restrict this run to the given fields, dropping every other field
     * from the structured-output schema entirely.
     *
     * @param  list<string>  $fields
     */
    public function withFields(array $fields): static;
}
