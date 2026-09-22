<?php

namespace App\Support\Exports\Value;

enum ExportColumnType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Boolean = 'boolean';
}
