<?php

namespace App\Domains\BoardMeeting\Value;

use App\Domains\Ai\Value\AiRun;
use App\Domains\BoardMeeting\Ai\MeetingSummaryAgent;
use App\Domains\BoardMeeting\Ai\MeetingSummaryField;

/**
 * One generated summary, in the shape §۶.۳ stores on the meeting.
 *
 * The status vocabulary is deliberately three-valued per field plus a
 * `pending` row state, because "the model had nothing to say about the
 * decisions" and "we never asked" are different facts and a reader has to be
 * able to tell them apart — that is what makes D26's "regenerate just this
 * field" actionable instead of guesswork.
 *
 * `error` holds a stable reason key, never prose. A reason string written
 * into the database is read again in whatever language the UI is in next
 * year, so it must be translatable rather than already translated; the field
 * states use the same discipline for `error`.
 *
 * @phpstan-type FieldState array{status: string, error?: string, generated_at: string|null}
 * @phpstan-type Values array<string, string|list<string>>
 */
final readonly class MeetingSummary
{
    /** Every requested field came back with something in it. */
    public const STATUS_OK = 'ok';

    /** Some fields came back and some did not. */
    public const STATUS_PARTIAL = 'partial';

    /** No field came back at all, or the run never reached a model. */
    public const STATUS_FAILED = 'failed';

    /** Queued or in flight — the run started and has not reported yet. */
    public const STATUS_PENDING = 'pending';

    public const FIELD_OK = 'ok';

    public const FIELD_FAILED = 'failed';

    /** The agent answered with prose instead of the requested structure. */
    public const REASON_NO_STRUCTURED_OUTPUT = 'no_structured_output';

    /** The structure arrived, but nothing the model put in it was usable. */
    public const REASON_NO_FIELDS_RETURNED = 'no_fields_returned';

    /** No active provider exists, so the run never left the hub. */
    public const REASON_NOT_CONFIGURED = 'not_configured';

    /** Per-field: the model left this one empty. */
    public const REASON_EMPTY = 'empty';

    /**
     * @param  Values  $values  one entry per requested field
     * @param  array<string, FieldState>  $fields  one entry per requested field
     * @param  array{input_tokens: int, output_tokens: int, total_tokens: int}|null  $usage
     */
    public function __construct(
        public string $status,
        public array $values,
        public array $fields,
        public ?string $provider = null,
        public ?string $model = null,
        public ?array $usage = null,
        public ?string $error = null,
        public ?string $generatedAt = null,
    ) {}

    /**
     * The row state for a meeting whose run has been dispatched but has not
     * reported. Written before the model is called so a bulk action over the
     * whole registry reads as "queued" rather than as "never asked".
     */
    public static function pending(): self
    {
        return new self(status: self::STATUS_PENDING, values: [], fields: []);
    }

    /**
     * A run that could not start, and a run whose output is unusable, are
     * both failures — but they are different failures and the reason key is
     * the only thing that tells them apart later.
     */
    public static function failure(string $reason): self
    {
        return new self(
            status: self::STATUS_FAILED,
            values: [],
            fields: [],
            error: $reason,
            generatedAt: now()->toIso8601String(),
        );
    }

    /**
     * The stored shape. Field values sit at the top level next to the
     * envelope so a reader can reach `summary.executive_summary` without
     * walking the per-field status map first.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            ...$this->values,
            'fields' => $this->fields,
            'agent' => MeetingSummaryAgent::REGISTRY_KEY,
            'provider' => $this->provider,
            'model' => $this->model,
            'usage' => $this->usage,
            'error' => $this->error,
            'generated_at' => $this->generatedAt,
        ];
    }

    /**
     * Read one run's structured output into an artifact.
     *
     * The response is treated as untrusted: a model that was asked for three
     * fields can return two, five, or a string where a list belongs, and
     * none of those may reach the row as anything other than a value the
     * BoardMeeting domain is willing to read back. A field the model skipped
     * is recorded as failed and the row as partial, not silently absent.
     *
     * @param  list<MeetingSummaryField>  $requested
     */
    public static function fromRun(AiRun $run, array $requested): self
    {
        $generatedAt = now()->toIso8601String();
        $structured = $run->structured;

        $values = [];
        $fields = [];

        foreach ($requested as $field) {
            $value = self::readValue($structured, $field);
            $filled = $field->isList() ? $value !== [] : $value !== null;

            $values[$field->value] = $value;
            $fields[$field->value] = [
                'status' => $filled ? self::FIELD_OK : self::FIELD_FAILED,
                // Always present, so a reader never has to tell "no reason"
                // from "an older shape that had no reason key".
                'error' => $filled ? null : self::REASON_EMPTY,
                'generated_at' => $filled ? $generatedAt : null,
            ];
        }

        $status = self::deriveStatus($fields);

        return new self(
            status: $status,
            values: $values,
            fields: $fields,
            provider: $run->provider,
            model: $run->model,
            usage: $run->usage,
            // Derived from the status rather than recounted here, so the
            // reason can never disagree with the state it explains. Which of
            // the two reasons it is depends on whether a structure ever
            // arrived: prose where fields were asked for is a different
            // failure from a structure with nothing usable in it.
            error: $status === self::STATUS_FAILED
                ? ($structured === null ? self::REASON_NO_STRUCTURED_OUTPUT : self::REASON_NO_FIELDS_RETURNED)
                : null,
            // Stamped even when nothing was filled, because it answers "when
            // was the row's current state established" — and a run that
            // established "all five fields failed" is a state worth dating.
            // Whether an individual value is fresh is the per-field
            // timestamp's job, not this one. `pending()` is the one state
            // with no date, because nothing has been established yet.
            generatedAt: $generatedAt,
        );
    }

    /**
     * Read a stored artifact back off a row.
     *
     * Tolerant on read, because the row it reads is the one most likely to
     * need regenerating: refusing to parse it would make a stale artifact the
     * one thing the feature cannot fix. So unknown keys are ignored and a
     * missing state defaults rather than throws.
     *
     * A field that was never generated stays absent from both maps. That is
     * the difference between "asked and came back empty" and "never asked",
     * and the whole per-field map exists to keep them apart.
     *
     * @param  array<string, mixed>|null  $raw
     */
    public static function fromArray(?array $raw): ?self
    {
        if ($raw === null || $raw === []) {
            return null;
        }

        $values = [];
        $fields = [];
        $rawStates = is_array($raw['fields'] ?? null) ? $raw['fields'] : [];

        foreach (MeetingSummaryField::cases() as $field) {
            $name = $field->value;
            $state = is_array($rawStates[$name] ?? null) ? $rawStates[$name] : [];

            if (! array_key_exists($name, $raw) && $state === []) {
                continue;
            }

            $values[$name] = self::readValue($raw, $field);

            $fields[$name] = [
                'status' => ($state['status'] ?? null) === self::FIELD_OK ? self::FIELD_OK : self::FIELD_FAILED,
                'error' => is_string($state['error'] ?? null) ? $state['error'] : null,
                'generated_at' => is_string($state['generated_at'] ?? null) ? $state['generated_at'] : null,
            ];
        }

        return new self(
            status: self::readStatus($raw['status'] ?? null),
            values: $values,
            fields: $fields,
            provider: is_string($raw['provider'] ?? null) ? $raw['provider'] : null,
            model: is_string($raw['model'] ?? null) ? $raw['model'] : null,
            usage: self::readUsage($raw['usage'] ?? null),
            error: is_string($raw['error'] ?? null) ? $raw['error'] : null,
            generatedAt: is_string($raw['generated_at'] ?? null) ? $raw['generated_at'] : null,
        );
    }

    /**
     * Fold this run's outcome into whatever the row already held.
     *
     * Overwriting instead would make the second run of a meeting destroy the
     * first, and D26 is built on the opposite promise: regenerating one bad
     * field must leave the other four alone. So the rules are —
     *
     *  - a field this run filled replaces the old value;
     *  - a field this run could not fill *keeps* its old value, because a
     *    failed regeneration throwing away a good answer helps nobody, while
     *    the state map still says the field failed and `generated_at: null`
     *    still says the value on screen is older than the run that just
     *    finished. Nothing stale is ever presented as fresh;
     *  - a field this run was not asked about is carried over untouched;
     *  - the envelope describes this run, not an average of them — "which
     *    model wrote this?" has to name the model that wrote the most recent
     *    thing written.
     *
     * The row status is then re-derived from the *merged* field map rather
     * than carried over, because it answers a question about the row and not
     * about the run: regenerating one field that comes back empty leaves four
     * good fields and one bad one, which is partial, and deriving from this
     * run alone would call it failed and throw away a working summary. A run
     * that never judged any field — pending, or failed before a model was
     * reached — keeps saying so, since it knows nothing about the others.
     */
    public function mergedOver(?self $previous): self
    {
        if ($previous === null) {
            return $this;
        }

        $values = $this->values;

        foreach ($previous->values as $name => $value) {
            $refilled = array_key_exists($name, $values)
                && ($this->fields[$name]['status'] ?? null) === self::FIELD_OK;

            if (! $refilled) {
                $values[$name] = $value;
            }
        }

        $fields = $this->fields + $previous->fields;

        return new self(
            status: $this->fields === [] ? $this->status : self::deriveStatus($fields),
            values: $values,
            fields: $fields,
            provider: $this->provider,
            model: $this->model,
            usage: $this->usage,
            error: $this->error,
            generatedAt: $this->generatedAt,
        );
    }

    /**
     * §۶.۳: every field ok is `ok`, some is `partial`, none is `failed`.
     *
     * @param  array<string, FieldState>  $fields
     */
    private static function deriveStatus(array $fields): string
    {
        if ($fields === []) {
            return self::STATUS_FAILED;
        }

        $filled = count(array_filter(
            $fields,
            fn (array $state): bool => ($state['status'] ?? null) === self::FIELD_OK,
        ));

        return $filled === count($fields)
            ? self::STATUS_OK
            : ($filled === 0 ? self::STATUS_FAILED : self::STATUS_PARTIAL);
    }

    /**
     * Pull one field's value out of whatever the model actually sent.
     *
     * List fields are de-duplicated and emptied of blank entries because a
     * model that emits the same decision twice, or pads a list with `""` to
     * hit a count, should not turn one meeting into two rows of evidence.
     *
     * @param  array<string, mixed>|null  $source
     * @return string|null|list<string>
     */
    private static function readValue(?array $source, MeetingSummaryField $field): string|null|array
    {
        $raw = $source[$field->value] ?? null;

        if (! $field->isList()) {
            return is_string($raw) && trim($raw) !== '' ? trim($raw) : null;
        }

        if (! is_array($raw)) {
            return [];
        }

        $items = array_filter(
            array_map(
                fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
                $raw,
            ),
            fn (string $item): bool => $item !== '',
        );

        return array_values(array_unique($items));
    }

    /**
     * An unrecognised status reads as failed rather than as anything better:
     * every other value would tell a user to trust a row whose shape this
     * code no longer recognises, and regenerating is cheap.
     */
    private static function readStatus(mixed $status): string
    {
        return in_array($status, [
            self::STATUS_OK,
            self::STATUS_PARTIAL,
            self::STATUS_FAILED,
            self::STATUS_PENDING,
        ], true) ? $status : self::STATUS_FAILED;
    }

    /** @return array{input_tokens: int, output_tokens: int, total_tokens: int}|null */
    private static function readUsage(mixed $usage): ?array
    {
        if (! is_array($usage)) {
            return null;
        }

        foreach (['input_tokens', 'output_tokens', 'total_tokens'] as $key) {
            if (! is_int($usage[$key] ?? null)) {
                return null;
            }
        }

        return [
            'input_tokens' => $usage['input_tokens'],
            'output_tokens' => $usage['output_tokens'],
            'total_tokens' => $usage['total_tokens'],
        ];
    }
}
