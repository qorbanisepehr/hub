<?php

namespace App\Support\Exports\Writer;

final class CsvWriter extends DelimitedWriter
{
    protected function delimiter(): string
    {
        return ',';
    }

    public function contentType(): string
    {
        return 'text/csv; charset=UTF-8';
    }

    public function extension(): string
    {
        return 'csv';
    }
}
