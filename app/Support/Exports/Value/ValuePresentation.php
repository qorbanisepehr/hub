<?php

namespace App\Support\Exports\Value;

/**
 * How a column's VALUES are shaped for humans in the file (as opposed to
 * `ExportColumnType`, which is the logical type). The kernel's
 * ValuePresenter applies it when the request asks for presentation;
 * a Raw column is emitted exactly as the exporter produced it.
 */
enum ValuePresentation: string
{
    case Raw = 'raw';
    case Date = 'date';
    case Number = 'number';
    case Boolean = 'boolean';
}
