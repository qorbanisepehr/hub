<?php

namespace App\Support\Exports\Concerns;

use App\Support\Exports\ExportService;
use App\Support\Exports\Value\ExportFile;
use App\Support\Exports\Value\PresentationOptions;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The three HTTP-side idioms every tabular export endpoint shares: the
 * comma-separated `fields` query param, the presentation query params,
 * and the streamed-download response. One kernel implementation so a new
 * exporter is `use` + three method calls, not a fourth copy of the
 * wiring.
 *
 * Presentation values are whitelisted so a stray query value degrades to
 * the default (machine) form instead of erroring.
 */
trait StreamsTabularExports
{
    /**
     * Stream an ExportFile as a download response.
     */
    protected function streamExport(ExportService $exports, ExportFile $file): StreamedResponse
    {
        return response()->streamDownload(function () use ($file): void {
            $stream = fopen('php://output', 'w');
            $file->copyTo($stream);
        }, $exports->dispositionFilename($file), [
            'Content-Type' => $file->mimeType,
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Comma-separated `fields` query param → list<string> for ExportRequest.
     *
     * @return list<string>
     */
    protected static function exportFieldsQuery(Request $request): array
    {
        return array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $request->query('fields', '')),
        )));
    }

    /**
     * Presentation query params → PresentationOptions. Whitelisted so a
     * stray query value degrades to the default (machine) form instead of
     * erroring.
     */
    protected static function exportPresentationQuery(Request $request): PresentationOptions
    {
        $headers = $request->query('headers');
        $calendar = $request->query('calendar');
        $digits = $request->query('digits');

        return new PresentationOptions(
            headers: in_array($headers, ['key', 'label'], true) ? $headers : 'key',
            calendar: in_array($calendar, ['gregorian', 'persian', 'both'], true) ? $calendar : 'gregorian',
            digits: $digits === 'persian' ? 'persian' : 'latin',
            detailSheets: $request->boolean('details'),
        );
    }
}
