<?php

namespace App\Support\Exports\Writer;

final class TsvWriter extends DelimitedWriter
{
    protected function delimiter(): string
    {
        return "\t";
    }

    public function contentType(): string
    {
        return 'text/tab-separated-values; charset=UTF-8';
    }

    public function extension(): string
    {
        return 'tsv';
    }
}
