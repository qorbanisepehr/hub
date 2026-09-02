<?php

namespace App\Support\Sections\Definitions;

use App\Rules\FormOptionValue;
use App\Support\Sections\BaseSection;
use App\Support\ValidationRules;

/**
 * Configurable Contact Info section shared across Cv, Employee, and
 * Questionnaire domains (ADR-007).
 *
 * The three domains differ only in how much of the contact shape they expose,
 * which fields are persisted to real columns, and how strictly the section
 * must be completed on submit. Everything else (validation rules, JSONB
 * storage, search metadata) is identical, so a single base hosts the shape
 * and each domain supplies a thin delta subclass via constructor config.
 *
 * Note: address.province/city are validated as FormOptionValue everywhere so
 * the domains stay consistent (a CV previously allowed free text here).
 */
abstract class ContactInfoSection extends BaseSection
{
    /**
     * @param  string  $labelKey  translation key, e.g. "cv.sections.contact_info"
     * @param  array<int, string>  $fields  contact fields the domain exposes
     * @param  array<int, string>  $realFields  columns persisted to the entity
     * @param  bool  $strictCompletion  require the full strict set on submit
     */
    public function __construct(
        protected string $labelKey,
        protected array $fields = [],
        protected array $realFields = [],
        protected bool $strictCompletion = false,
    ) {}

    public function key(): string
    {
        return 'contact_info';
    }

    public function label(): string
    {
        return __($this->labelKey);
    }

    public function fields(): array
    {
        return $this->fields;
    }

    public function structuralRules(): array
    {
        return [
            'email' => 'nullable|'.ValidationRules::EMAIL,
            'mobile' => ['nullable', 'string', 'max:15', ValidationRules::MOBILE_ACCEPTED],
            'phone' => 'nullable|'.ValidationRules::LANDLINE,
            'emergency_phone' => 'nullable|'.ValidationRules::MOBILE_OR_LANDLINE,
            'address' => 'nullable|array',
            'address.postal_code' => 'nullable|'.ValidationRules::POSTAL_CODE,
            'address.province' => ['nullable', new FormOptionValue('province')],
            'address.city' => ['nullable', new FormOptionValue('city')],
            'address.neighborhood' => 'nullable|'.ValidationRules::TEXT.'|max:100',
            'address.address' => 'nullable|'.ValidationRules::TEXT.'|max:500',
            'address.plaque' => 'nullable|'.ValidationRules::TEXT.'|max:10',
            'address.floor' => 'nullable|'.ValidationRules::TEXT.'|max:10',
            'address.unit' => 'nullable|'.ValidationRules::TEXT.'|max:10',
        ];
    }

    public function completionRules(): array
    {
        if (! $this->strictCompletion) {
            return [
                // Email stays optional on a CV, but once filled in it must be
                // verified before submit (enforced in the controller).
                'email' => 'nullable|'.ValidationRules::EMAIL,
                'mobile' => ['required', 'string', 'max:15', ValidationRules::MOBILE_ACCEPTED],
                'phone' => 'nullable|'.ValidationRules::LANDLINE,
                'emergency_phone' => 'nullable|'.ValidationRules::MOBILE_OR_LANDLINE,
                'address' => 'nullable|array',
            ];
        }

        return [
            'email' => 'required|'.ValidationRules::EMAIL,
            'mobile' => ['required', 'string', 'max:15', ValidationRules::MOBILE_ACCEPTED],
            'phone' => 'required|'.ValidationRules::LANDLINE,
            'emergency_phone' => 'required|'.ValidationRules::MOBILE_OR_LANDLINE,
            'address' => 'required|array',
            'address.postal_code' => 'required|'.ValidationRules::POSTAL_CODE,
            'address.province' => ['required', new FormOptionValue('province')],
            'address.city' => ['required', new FormOptionValue('city')],
            'address.neighborhood' => 'nullable|'.ValidationRules::TEXT.'|max:100',
            'address.address' => 'required|'.ValidationRules::TEXT.'|max:500',
        ];
    }

    public function storage(): array
    {
        return [
            'real' => $this->realFields,
            'jsonb' => 'section_contact_address',
        ];
    }

    public function searchMetadata(): array
    {
        return ['address.province', 'address.city'];
    }

    public function prefill(): array
    {
        return [];
    }
}
