<?php

namespace App\Support\Exports\Value;

/**
 * What to export: the field selection, the format, and how the file should
 * read (machine vs human presentation). Domain filters stay in the domain
 * (they are domain vocabulary); the kernel never sees them.
 */
final class ExportRequest
{
    /**
     * @param  list<string>  $fields  Selected column keys; empty list = exporter default (each exporter documents its own empty selection behavior).
     * @param  string  $format  Short format name, e.g. 'csv', 'jsonl', 'tsv', 'xlsx'.
     * @param  array<string, mixed>  $context  Opaque domain payload (e.g. list-endpoint filters). The kernel never interprets it.
     * @param  PresentationOptions  $presentation  Human-reading concerns (headers, calendar, digits). Defaults to the machine form.
     */
    public function __construct(
        public readonly array $fields = [],
        public readonly string $format = 'csv',
        public readonly ExportOptions $options = new ExportOptions,
        public readonly array $context = [],
        public readonly PresentationOptions $presentation = new PresentationOptions,
    ) {}
}
