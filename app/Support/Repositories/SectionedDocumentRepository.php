<?php

namespace App\Support\Repositories;

use Illuminate\Database\Eloquent\Model;

/**
 * Parameterized repository base for section-based candidate documents
 * (Cv, Questionnaire; Employee-ready).
 *
 * Provides shared persistence helpers via protected methods: create with
 * default status, UUID lookup, JSONB section update, status transitions,
 * and optimistic-locking version bumps. Hooks let each concrete entity
 * adapt the base behavior to its own schema.
 *
 * Concrete repositories extend this class and expose interface-compatible
 * public methods that delegate to the `perform*` helpers. This avoids PHP's
 * parameter-type variance conflicts between the shared `Model`-typed base
 * and per-domain interfaces with concrete model params. The `perform*`
 * helpers are public so the shared contract stays directly testable.
 *
 * @template TModel of Model
 */
abstract class SectionedDocumentRepository
{
    /**
     * The Eloquent model this repository persists.
     *
     * @return class-string<TModel>
     */
    abstract protected function modelClass(): string;

    /**
     * Lifecycle defaults merged into every `create()` call. Subclasses
     * may override to add or omit fields (e.g. Employee omits version).
     *
     * @return array<string, mixed>
     */
    protected function createDefaults(): array
    {
        return [
            'status' => 'draft',
            'version' => 1,
        ];
    }

    /**
     * Column holding the candidate-facing UUID, or null for models
     * without one (e.g. Employee has no uuid column). When null,
     * `performFindByUuid()` returns null.
     */
    protected function uuidColumn(): ?string
    {
        return 'uuid';
    }

    /**
     * Column holding the lifecycle status string.
     */
    protected function statusColumn(): string
    {
        return 'status';
    }

    /**
     * Column holding the optimistic-locking version integer, or null
     * for models without version tracking. When null,
     * `performIncrementVersion()` is a no-op.
     */
    protected function versionColumn(): ?string
    {
        return 'version';
    }

    /**
     * Create a new document with the entity's default lifecycle values
     * merged beneath the caller-supplied data.
     *
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function performCreate(array $data): Model
    {
        $class = $this->modelClass();

        return $class::create($this->createDefaults() + $data);
    }

    /**
     * Find a document by its UUID, or null when the entity has no UUID
     * column or the record does not exist.
     *
     * @return TModel|null
     */
    public function performFindByUuid(string $uuid): ?Model
    {
        $column = $this->uuidColumn();

        if ($column === null) {
            return null;
        }

        return ($this->modelClass())::query()
            ->where($column, $uuid)
            ->first();
    }

    /**
     * Persist the full JSONB payload for a section column and return
     * a freshly-loaded model instance.
     *
     * @param  TModel  $document
     * @param  array<string, mixed>  $data
     * @return TModel
     */
    public function performUpdateSection(Model $document, string $jsonbColumn, array $data): Model
    {
        $document->update([
            $jsonbColumn => $data,
        ]);

        return $document->fresh();
    }

    /**
     * Transition a document's lifecycle status and return a freshly-loaded
     * model instance.
     *
     * @param  TModel  $document
     * @return TModel
     */
    public function performUpdateStatus(Model $document, string $status): Model
    {
        $document->update([
            $this->statusColumn() => $status,
        ]);

        return $document->fresh();
    }

    /**
     * Bump the optimistic-locking version counter and return a freshly-
     * loaded model instance. No-op when `versionColumn()` is null.
     *
     * @param  TModel  $document
     * @return TModel
     */
    public function performIncrementVersion(Model $document): Model
    {
        $column = $this->versionColumn();

        if ($column !== null) {
            $document->increment($column);
        }

        return $document->fresh();
    }
}
