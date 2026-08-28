<?php

namespace Database\Seeders;

use App\Domains\TempEmployees\Models\TempEmployee;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds a few temp employees and their on-disk sample folders so the
 * temporary file-explorer page has something real to browse and preview.
 */
class TempEmployeeSeeder extends Seeder
{
    use WithoutModelEvents;

    /** A minimal, structurally valid one-page PDF. */
    private const SAMPLE_PDF = <<<'PDF'
%PDF-1.4
1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj
2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj
3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 200 100] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj
4 0 obj << /Length 44 >> stream
BT /F1 18 Tf 20 50 Td (Sample Document) Tj ET
endstream
endobj
5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj
trailer << /Root 1 0 R >>
%%EOF
PDF;

    public function run(): void
    {
        $employees = [
            ['personnel_code' => '1001', 'id_number' => '0012345678', 'first_name' => 'علی', 'last_name' => 'رضایی'],
            ['personnel_code' => '1002', 'id_number' => '0023456789', 'first_name' => 'مریم', 'last_name' => 'احمدی'],
            ['personnel_code' => '1003', 'id_number' => null, 'first_name' => 'حسین', 'last_name' => 'کریمی'],
        ];

        foreach ($employees as $attributes) {
            $employee = TempEmployee::query()->updateOrCreate(
                ['personnel_code' => $attributes['personnel_code']],
                $attributes,
            );

            $this->seedFiles($employee);
        }
    }

    private function seedFiles(TempEmployee $employee): void
    {
        $disk = Storage::disk('local');
        $base = $employee->filesDirectory();

        $disk->makeDirectory($base);

        // Stable sample content: a nested folder, an image, a pdf, and a text
        // note — enough to exercise tree/table/card modes and the lightbox.
        // NOTE: sample names avoid ZWNJ (U+200C) — Flysystem v3's whitespace
        // normalizer treats it as a corrupted path.
        $disk->put("{$base}/قرارداد.pdf", self::SAMPLE_PDF);
        $disk->put("{$base}/یادداشت.txt", "پرونده نمونه برای {$employee->first_name} {$employee->last_name}\n");

        $disk->makeDirectory("{$base}/مدارک هویتی");
        $disk->put(
            "{$base}/مدارک هویتی/کارت ملی.png",
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAYAAADED76LAAAAFklEQVR4nGP8z8Dwn4EIwESMolGFSQEA3gUD7AH5k0YAAAAASUVORK5CYII='),
        );

        $disk->makeDirectory("{$base}/مدارک هویتی/تاییدیه ها");
        $disk->put("{$base}/مدارک هویتی/تاییدیه ها/تاییدیه.pdf", self::SAMPLE_PDF);

        $disk->makeDirectory("{$base}/گزارش ها");
        $disk->put("{$base}/گزارش ها/خلاصه.txt", "گزارش ماهانه\n");
    }
}
