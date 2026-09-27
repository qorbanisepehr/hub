<?php

namespace App\Domains\Cv\Exports;

use App\Domains\Cv\Models\Cv;
use App\Domains\Cv\Services\CvService;
use App\Domains\FormOptions\Services\FormOptionService;
use App\Support\Exports\Contract\DocumentSource;
use App\Support\Exports\Documents\SectionDocumentBuilder;
use App\Support\Exports\Value\Document\DocumentField;
use App\Support\Exports\Value\Document\DocumentSpec;
use App\Support\Exports\ValuePresenter;

/**
 * The printable CV document (PDF/Word): every registered section of one
 * candidate CV, walked by the shared SectionDocumentBuilder. Status is shown
 * under the bank's own label source (CvStatus  fa labels live client-side;
 * the print keeps the enum word through the cv lang file).
 */
final class CvDocument implements DocumentSource
{
    public function __construct(
        private readonly CvService $sections,
        private readonly FormOptionService $formOptions,
    ) {}

    public function document(mixed $entity): DocumentSpec
    {
        /** @var Cv $entity */
        $builder = new SectionDocumentBuilder(
            sections: $this->sections,
            labelMaps: [
                (array) trans('validation.attributes'),
            ],
            optionLabel: fn (string $key, string $value, ?string $group): ?string => $this->optionLabel($group, $value),
        );

        return new DocumentSpec(
            title: (string) __('cv.document.title'),
            sections: $builder->build($entity),
            meta: array_values(array_filter([
                DocumentField::from((string) __('cv.document.status'), $this->statusLabel($entity)),
                DocumentField::from((string) __('exports.generated_at'), ValuePresenter::generatedAtStamp()),
            ])),
            subtitle: trim("{$entity->first_name} {$entity->last_name}"),
        );
    }

    public function baseFilename(): string
    {
        return 'cv';
    }

    private function statusLabel(Cv $entity): ?string
    {
        $key = 'cv.document.statuses.'.$entity->status->value;
        $label = (string) __($key);

        return $label === $key ? $entity->status->value : $label;
    }

    /**
     * CV option cells all come from form-options groups declared on the
     * section rules; unknown (deactivated) values keep their stored form.
     */
    private function optionLabel(?string $group, string $value): ?string
    {
        if ($group === null) {
            return null;
        }

        $resolved = $this->formOptions->resolveValues($group, [$value]);

        return $resolved[0]['label'] ?? null;
    }
}
