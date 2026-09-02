<?php

namespace App\Support\Sections;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Shared section-orchestration base for the candidate-facing domain services
 * (Cv, Questionnaire, Employee). Extends SectionRegistry — which owns the
 * section definitions — with the persistence helpers every service reuses:
 * real-field extraction, completion validation, and the standard error
 * aggregation used on submit.
 *
 * Behavior-preserving by design: entity-specific differences are expressed as
 * hooks/overrides rather than by copy-editing each service (ADR-007). In
 * particular, `gatherAllData()` differs per entity (which real columns are
 * authoritative for completion validation), and whether per-row document
 * requirements are enforced at submit is a per-service switch so the N1
 * enforcement gap stays an explicit decision, not an implicit behavior change.
 */
abstract class SectionService extends SectionRegistry
{
    /**
     * Whether submit-time completion validation also enforces each section's
     * per-row document requirements. Employee enables this; Cv and
     * Questionnaire keep the default off (the N1 gap is out of scope pending a
     * product decision).
     */
    protected bool $mergeCompletionDocumentErrors = false;

    /**
     * Extract the real-column values for a section from `storage()`. Empty
     * strings are kept as-is here; Employee overrides to normalize `''` to
     * `null` so its nullable unique columns never collide.
     *
     * @param  array<string, mixed>  $data
     * @param  string[]  $realFields
     * @return array<string, mixed>
     */
    protected function extractRealFields(array $data, array $realFields): array
    {
        return array_intersect_key($data, array_flip($realFields));
    }

    /**
     * Run completion validation against all registered sections.
     *
     * @return array<string, string[]>
     */
    public function validateCompletion(mixed $entity): array
    {
        $allData = $this->gatherAllData($entity);
        $allErrors = [];

        foreach ($this->sections as $key => $section) {
            if (empty($section->rulesFor(SectionDefinition::MODE_COMPLETION))) {
                continue;
            }

            $validator = $section->validateData($allData[$key] ?? [], SectionDefinition::MODE_COMPLETION);

            if ($validator->fails()) {
                $allErrors = array_merge($allErrors, $validator->errors()->toArray());
            }

            if ($this->mergeCompletionDocumentErrors) {
                $allErrors = array_merge($allErrors, $section->completionDocumentErrors($entity));
            }
        }

        return $allErrors;
    }

    /**
     * Gather the full section data for completion validation. The base
     * implementation reads each section's JSONB column and casts its real
     * columns back onto the section so rules see the complete object. Entities
     * whose authoritative values live in real columns commit them here
     * (Employee); Cv and Questionnaire override to preserve their own semantics
     * (Cv re-commits email/mobile, Qn reads JSONB only).
     *
     * @return array<string, mixed>
     */
    protected function gatherAllData(mixed $entity): array
    {
        $data = [];

        foreach ($this->sections as $key => $section) {
            $storage = $section->storage();
            $jsonbColumn = $storage['jsonb'] ?? null;
            $sectionData = $jsonbColumn ? ($entity->{$jsonbColumn} ?? []) : [];

            foreach ($storage['real'] ?? [] as $field) {
                $value = $entity->{$field} ?? null;
                if ($value !== null) {
                    $sectionData[$field] = $value;
                }
            }

            $data[$key] = $sectionData;
        }

        return $data;
    }

    /**
     * Throw a ValidationException built from a field → messages error map.
     *
     * @param  array<string, string[]>  $errors
     *
     * @throws ValidationException
     */
    protected function throwValidationErrors(array $errors): never
    {
        $validator = Validator::make([], []);

        foreach ($errors as $field => $messages) {
            foreach ($messages as $message) {
                $validator->errors()->add($field, $message);
            }
        }

        throw new ValidationException($validator);
    }
}
