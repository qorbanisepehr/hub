<?php

namespace App\Models;

use Database\Factories\TempEmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Temporary record for the throwaway file-explorer tool. Each row maps to
 * one on-disk folder under storage "temp-files/{personnel_code}".
 */
class TempEmployee extends Model
{
    /** @use HasFactory<TempEmployeeFactory> */
    use HasFactory;

    protected $fillable = [
        'personnel_code',
        'id_number',
        'first_name',
        'last_name',
    ];

    /**
     * Storage-relative directory holding this employee's files.
     */
    public function filesDirectory(): string
    {
        return 'temp-files/'.$this->personnel_code;
    }
}
