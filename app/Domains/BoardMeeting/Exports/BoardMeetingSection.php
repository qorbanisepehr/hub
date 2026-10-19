<?php

namespace App\Domains\BoardMeeting\Exports;

use App\Support\Exports\Templates\TemplateField;

/**
 * One printable section of a board meeting, and the values a template may put in it.
 *
 * A value object rather than an array so that "which section does this field belong
 * to" is a question with an answer in the type, and so a section cannot be declared
 * with its fields left implicit. Before this existed the two were separate lists —
 * section keys in one class, field keys in another — and nothing connected them.
 */
final class BoardMeetingSection
{
    /**
     * @param  list<TemplateField>  $fields  every value a template may address inside
     *                                       this section, in the order they read
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $fields,
    ) {}

    /**
     * @return list<string>
     */
    public function fieldKeys(): array
    {
        return array_map(
            static fn (TemplateField $field): string => $field->key,
            $this->fields,
        );
    }
}
