<?php

namespace App\Support\Exports\Render;

use App\Support\Exports\Contract\DocumentRenderer;
use App\Support\Exports\Value\Document\DocumentSpec;
use Illuminate\Support\Facades\File;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

/**
 * PDF renderer: the kernel DocumentSpec becomes RTL Persian HTML through a
 * shared Blade view, then mPDF turns the HTML into embedded-font PDF bytes.
 *
 * mPDF needs TTF/OTF (not the woff2 the web app ships), so Vazirmatn static
 * TTFs are vendored under `config('exports.documents.font_dir')` and
 * registered with `useOTL` so the Arabic/Persian GSUB features apply (the
 * shaping quality the vault plan picked mPDF for). Font metrics cache and
 * temp files land in `config('exports.documents.temp_dir')` (gitignored).
 */
final class PdfRenderer implements DocumentRenderer
{
    public function render(DocumentSpec $spec, $stream): void
    {
        $html = view('exports.document', ['doc' => $spec])->render();

        $mpdf = new Mpdf($this->config());
        $mpdf->SetDirectionality('rtl');
        $mpdf->mirrorMargins = false;
        // mPDF 8 substitutes only {PAGENO}/{nb}/{nbpg} (NOT TCPDF's
        // _PAGENUM/_TOTALPAGES, which would print as literal garbage).
        $mpdf->SetHTMLFooter(
            '<div style="direction:rtl; text-align:center; font-size:8.5pt; color:#667788;">'
            .e($spec->title).' — صفحه {PAGENO} از {nb}</div>'
        );
        $mpdf->SetTitle($spec->title);
        $mpdf->WriteHTML($html);

        $bytes = $mpdf->Output('', 'S');

        if ($bytes === '' || $bytes === false) {
            throw new \RuntimeException('mPDF produced no output.');
        }

        fwrite($stream, $bytes);
    }

    public function contentType(): string
    {
        return 'application/pdf';
    }

    public function extension(): string
    {
        return 'pdf';
    }

    /**
     * mPDF configuration: bundled font dirs plus the vendored Vazirmatn
     * family, OTL shaping enabled on it, and a writable temp dir under
     * storage (never the vendor tmp).
     *
     * @return array<string, mixed>
     */
    private function config(): array
    {
        $fontDir = (string) config('exports.documents.font_dir');
        $tempDir = (string) config('exports.documents.temp_dir');

        if (! File::isDirectory($tempDir)) {
            File::makeDirectory($tempDir, 0o755, true);
        }

        $defaults = new ConfigVariables;
        $fonts = new FontVariables;
        $fontKey = (string) config('exports.documents.font');

        return [
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 16,
            'margin_bottom' => 18,
            'default_font' => $fontKey,
            // No autoScriptToLang/autoLangToFont: they would re-map Persian
            // runs to mPDF's bundled 'XB Riyaz' fallback via lang2font and
            // bypass Vazirmatn. The view is fully RTL/Persian already, and
            // useOTL below gives Vazirmatn the GSUB shaping instead.
            'fontDir' => [...$defaults->getDefaults()['fontDir'], $fontDir],
            'tempDir' => $tempDir,
            'fontdata' => [
                ...$fonts->getDefaults()['fontdata'],
                $fontKey => [
                    'R' => 'vazirmatn-regular.ttf',
                    'B' => 'vazirmatn-bold.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ],
        ];
    }
}
