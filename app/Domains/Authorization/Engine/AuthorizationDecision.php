<?php

namespace App\Domains\Authorization\Engine;

/**
 * The outcome of an authorization evaluation. The public API reduces this to a
 * bool (can()), while explain() exposes the full rule-level detail.
 */
final class AuthorizationDecision
{
    public const REASON_EXPLICIT_DENY = 'explicit_deny';

    /**
     * @param  array<int, array<string, mixed>>  $matchedRules  allow rules that granted access
     * @param  array<int, array<string, mixed>>  $deniedRules  deny rules that blocked access
     * @param  array<int, array<string, mixed>>  $policyResults  per-rule policy evaluation detail
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly string $reason,
        public readonly array $matchedRules = [],
        public readonly array $deniedRules = [],
        public readonly array $policyResults = [],
        public readonly bool $policyPending = false,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $matchedRules
     * @param  array<int, array<string, mixed>>  $policyResults
     */
    public static function allow(string $reason = 'allow', array $matchedRules = [], array $policyResults = []): self
    {
        return new self(allowed: true, reason: $reason, matchedRules: $matchedRules, policyResults: $policyResults);
    }

    /**
     * @param  array<int, array<string, mixed>>  $deniedRules
     * @param  array<int, array<string, mixed>>  $policyResults
     */
    public static function deny(
        string $reason = 'deny',
        array $deniedRules = [],
        array $policyResults = [],
        bool $policyPending = false,
    ): self {
        return new self(allowed: false, reason: $reason, deniedRules: $deniedRules, policyResults: $policyResults, policyPending: $policyPending);
    }

    /**
     * @return array{allowed: bool, reason: string, matched_rules: array<int, array<string, mixed>>, denied_rules: array<int, array<string, mixed>>, policy_results: array<int, array<string, mixed>>, policy_pending: bool}
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reason' => $this->reason,
            'matched_rules' => $this->matchedRules,
            'denied_rules' => $this->deniedRules,
            'policy_results' => $this->policyResults,
            'policy_pending' => $this->policyPending,
        ];
    }
}
