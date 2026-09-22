<?php

namespace App\Support\Exports\Value;

/**
 * One column of the tabular export. `key` is the machine-stable dotted path
 * (also the JSONB path the value comes from); `column` is the written
 * header — machine key by default, localized label when the request asks
 * for it.
 */
final class ExportColumn
{
    public function __construct(
        public readonly string $key,
        public readonly string $faLabel,
        public readonly string $column,
        public readonly ExportColumnType $type = ExportColumnType::Text,
        public readonly ValuePresentation $presentation = ValuePresentation::Raw,
        /**
         * When the request asks for both calendars, a Date column emits this
         * sibling right after itself (null = no sibling). Derived from the
         * exporter's own label source, so both headers stay localized.
         */
        public readonly ?self $bothSibling = null,
    ) {}

    /**
     * The header as the requested presentation language reads it.
     */
    public function headerFor(string $headers): self
    {
        if ($headers !== 'label') {
            return $this;
        }

        return new self(
            key: $this->key,
            faLabel: $this->faLabel,
            column: $this->faLabel,
            type: $this->type,
            presentation: $this->presentation,
            bothSibling: $this->bothSibling?->headerFor('key'),
        );
    }

    /**
     * The both-calendars sibling pair for one Date column: itself plus the
     * Jalali sibling, in emission order.
     *
     * @return list<self>
     */
    public function columnsForCalendarShape(): array
    {
        if ($this->type !== ExportColumnType::Date || $this->bothSibling === null) {
            return [$this];
        }

        return [$this, $this->bothSibling];
    }

    /**
     * @return array{key: string, label: string, column: string, type: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->faLabel,
            'column' => $this->column,
            'type' => $this->type->value,
        ];
    }
}
