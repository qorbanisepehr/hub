<?php

namespace App\Domains\BoardMeeting\Value;

use App\Domains\Ai\Value\AiRun;
use App\Domains\BoardMeeting\Ai\MeetingSummaryAgent;
use App\Domains\BoardMeeting\Ai\MeetingSummaryField;

/**
 * One summary, in the shape §۶.۳ stores on the meeting.
 *
 * The status vocabulary is deliberately three-valued per field plus a
 * `pending` row state, because "the model had nothing to say about the
 * decisions" and "we never asked" are different facts and a reader has to be
 * able to tell them apart — that is what makes D26's "regenerate just this
 * field" actionable instead of guesswork.
 *
 * A stored summary holds two of these, not one: itself, which is what the
 * meeting is, and `pending`, which is what the last run proposed and a person
 * has not decided about yet. Both are the same shape on purpose — the thing a
 * person is being asked to choose between has to be readable by the same code
 * that reads the thing already chosen, or "show me what changed" becomes a
 * second, differently-shaped reader. The nesting is one level by construction:
 * a candidate never has a candidate of its own, because a new run supersedes
 * the one before it rather than queueing behind it.
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

    /**
     * Asked, and nothing has been accepted yet.
     *
     * A fourth row state, and the one that would have been missing: a meeting
     * whose only summary is a candidate nobody has looked at has no content
     * and has not failed, and reporting it as `failed` would tell a user their
     * summary is broken when in fact it is waiting for them. `pending` cannot
     * carry it either — that says a run is in flight, and this one has already
     * reported.
     */
    public const STATUS_NONE = 'none';

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
     * @param  self|null  $pending  the last run's proposal, undecided
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
        public ?self $pending = null,
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
     * A stored summary that holds nothing: asked about, nothing accepted.
     *
     * Only reachable as the outer layer of a review-mode write, where the run's
     * output has to live somewhere and the record itself must stay untouched.
     */
    public static function none(): self
    {
        return new self(status: self::STATUS_NONE, values: [], fields: []);
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
     * walking the per-field status map first, and `pending` sits beside the
     * envelope as the same shape again rather than as a flattened variant of
     * it.
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
            // Always present, null included: a reader must not have to tell
            // "nothing awaits a decision" from "an older shape that had no
            // pending key at all".
            'pending' => $this->pending?->toArray(),
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
     * A candidate is read as the same shape, one level only. A `pending` nested
     * inside a `pending` is dropped rather than followed: nothing writes one,
     * so honouring it would let a hand-edited row claim a queue of proposals
     * that has no meaning.
     *
     * @param  array<string, mixed>|null  $raw
     */
    public static function fromArray(?array $raw): ?self
    {
        if ($raw === null || $raw === []) {
            return null;
        }

        $stored = self::read($raw);
        $candidate = is_array($raw['pending'] ?? null) ? $raw['pending'] : null;

        return $candidate === null || $candidate === []
            ? $stored
            : $stored->withPending(self::read($candidate));
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function read(array $raw): self
    {
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
     * This row with a proposal waiting on a person, or with none.
     *
     * Passing null is how a decision is recorded: a person who looked at the
     * candidate and took none of it has still decided, and the row must stop
     * offering it. There is no separate "dismissed" marker, because a dismissed
     * proposal and one that was never made look the same from here — which is
     * the truth, and the reason a fresh run is the only way to get another.
     */
    public function withPending(?self $pending): self
    {
        return $this->copy(['pending' => $pending]);
    }

    /**
     * This run seen as only the fields a person accepted.
     *
     * The same narrowing the agent was asked for, on the way back: accepting
     * two of five fields writes a summary of two, so its status is derived from
     * those two and not from the run as a whole. A field the run left empty can
     * still be accepted — it carries its failed state, and merging that over a
     * stored value keeps the stored one, so ticking it changes nothing rather
     * than blanking the row.
     *
     * @param  list<MeetingSummaryField>  $accepted
     */
    public function narrowedTo(array $accepted): self
    {
        $values = [];
        $states = [];

        foreach ($accepted as $field) {
            $name = $field->value;

            $values[$name] = $this->values[$name] ?? ($field->isList() ? [] : null);
            $states[$name] = $this->fields[$name] ?? [
                'status' => self::FIELD_FAILED,
                'error' => self::REASON_EMPTY,
                'generated_at' => null,
            ];
        }

        return $this->copy([
            'status' => self::deriveStatus($states),
            'values' => $values,
            'fields' => $states,
        ]);
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
     *    thing written;
     *  - any undecided proposal is dropped, because this run supersedes it
     *    rather than queueing behind it.
     *
     * The row status is then re-derived from the *merged* field map rather
     * than carried over, because it answers a question about the row and not
     * about the run: regenerating one field that comes back empty leaves four
     * good fields and one bad one, which is partial, and deriving from this
     * run alone would call it failed and throw away a working summary. A run
     * that never judged any field — pending, or failed before a model was
     * reached — keeps saying so, since it knows nothing about the others.
     *
     * The row `error` follows the same rule and for the same reason. Carrying
     * the run's reason over unconditionally would store `partial` next to
     * `no_fields_returned` — a row that has usable content telling the reader
     * something went wrong. A reason exists to explain a row with nothing
     * usable in it, so it survives only while the status is `failed`, and the
     * failures inside a partially-successful row are already named per field.
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
        $status = $this->fields === [] ? $this->status : self::deriveStatus($fields);

        return $this->copy([
            'status' => $status,
            'values' => $values,
            'fields' => $fields,
            'error' => $status === self::STATUS_FAILED ? $this->error : null,
            // Whatever the row was waiting on, this run has answered.
            'pending' => null,
        ]);
    }

    /**
     * §۶.۳: every field ok is `ok`, some is `partial`, none is `failed`.
     *
     * An empty map is `none` rather than `failed` — nothing was judged, which
     * is not the same as everything having failed. The callers that mean
     * "failed" for an empty map say so themselves, with a reason key.
     *
     * @param  array<string, FieldState>  $fields
     */
    private static function deriveStatus(array $fields): string
    {
        if ($fields === []) {
            return self::STATUS_NONE;
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
            self::STATUS_NONE,
        ], true) ? $status : self::STATUS_FAILED;
    }

    /**
     * This summary with some of its parts replaced.
     *
     * Every derived summary goes through here rather than through a second
     * hand-written constructor call, because a property added to this class
     * and forgotten in one of them is a silent data loss that no test of the
     * four operations would necessarily catch.
     *
     * `array_key_exists`, not `??`, because `pending` and `error` both take a
     * meaningful null: `??` would make "clear the proposal" unreachable and
     * silently keep the one being cleared.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function copy(array $overrides): self
    {
        $read = fn (string $key, mixed $fallback): mixed => array_key_exists($key, $overrides)
            ? $overrides[$key]
            : $fallback;

        return new self(
            status: (string) $read('status', $this->status),
            values: (array) $read('values', $this->values),
            fields: (array) $read('fields', $this->fields),
            provider: $read('provider', $this->provider),
            model: $read('model', $this->model),
            usage: $read('usage', $this->usage),
            error: $read('error', $this->error),
            generatedAt: $read('generatedAt', $this->generatedAt),
            pending: $read('pending', $this->pending),
        );
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
