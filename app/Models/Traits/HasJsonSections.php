<?php

namespace App\Models\Traits;

use Illuminate\Support\Str;

/**
 * Shared JSONB-section persistence for candidate models.
 *
 * Provide the getSection()/setSection() accessors and, where configured, the
 * creating UUID hook and the section-persistence behaviors that keep the
 * personal-info section consistent (orphaned military record cleanup for
 * non-male candidates). The per-model trigger for the military cleanup is
 * genuinely divergent (the gender source and dirty-column differ), so each
 * model overrides handleSectionPersistence() and calls the shared prune helper.
 */
trait HasJsonSections
{
    public function getSection(string $name): ?array
    {
        return $this->{"section_{$name}"} ?? null;
    }

    public function setSection(string $name, array $data): void
    {
        $this->{"section_{$name}"} = $data;
    }

    public static function bootHasJsonSections(): void
    {
        static::creating(function ($model): void {
            $uuidColumn = $model->sectionUuidColumn();

            if ($uuidColumn !== null && empty($model->{$uuidColumn})) {
                $model->{$uuidColumn} = (string) Str::uuid();
            }
        });

        static::saving(function ($model): void {
            if ($model->exists) {
                $model->handleSectionPersistence();
            }
        });
    }

    protected function handleSectionPersistence(): void
    {
        // No-op by default; models that need it override.
    }

    /**
     * Drop any orphaned military record from the personal-info section when
     * the candidate is no longer male. Shared by Cv and Questionnaire, which
     * differ only in how they determine the gender change.
     */
    protected function pruneNonMaleMilitaryStatus(): void
    {
        $sectionPersonal = $this->getSection('personal') ?? [];

        if (array_key_exists('military_status', $sectionPersonal) && $sectionPersonal['military_status'] !== null) {
            unset($sectionPersonal['military_status']);
            $this->setSection('personal', $sectionPersonal);
        }
    }

    /**
     * Column that receives an auto-generated UUID on create, or null to
     * disable UUID generation (e.g. Employee has no uuid column).
     */
    protected function sectionUuidColumn(): ?string
    {
        return 'uuid';
    }
}
