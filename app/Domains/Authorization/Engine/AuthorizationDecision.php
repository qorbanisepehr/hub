<?php

namespace App\Domains\Authorization\Engine;

/**
 * The outcome of an authorization evaluation. The public API reduces this to a
 * bool (can()), while explain() exposes the full rule-level detail.
 */
final class AuthorizationDecision
{
    /**
     * @param  array<int, array<string, mixed>>  $matchedRules  allow rules that granted access
     * @param  array<int, array<string, mixed>>  $deniedRules  deny rules that blocked access
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly string $reason,
        public readonly array $matchedRules = [],
        public readonly array $deniedRules = [],
        public readonly bool $policyPending = false,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $matchedRules
     */
    public static function allow(string $reason = 'allow', array $matchedRules = []): self
    {
        return new self(allowed: true, reason: $reason, matchedRules: $matchedRules);
    }

    /**
     * @param  array<int, array<string, mixed>>  $deniedRules
     */
    public static function deny(string $reason = 'deny', array $deniedRules = [], bool $policyPending = false): self
    {
        return new self(allowed: false, reason: $reason, deniedRules: $deniedRules, policyPending: $policyPending);
    }

    /**
     * @return array{allowed: bool, reason: string, matched_rules: array<int, array<string, mixed>>, denied_rules: array<int, array<string, mixed>>, policy_pending: bool}
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reason' => $this->reason,
            'matched_rules' => $this->matchedRules,
            'denied_rules' => $this->deniedRules,
            'policy_pending' => $this->policyPending,
        ];
    }
}
