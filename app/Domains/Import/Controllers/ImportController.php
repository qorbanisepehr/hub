<?php

namespace App\Domains\Import\Controllers;

use App\Support\Exports\Contract\TabularExporter;
use App\Support\Exports\ExportService;
use App\Support\Exports\Value\ExportFile;
use App\Support\Exports\Value\ExportOptions;
use App\Support\Exports\Value\ExportRequest;
use App\Support\Imports\Contract\NormalizesValues;
use App\Support\Imports\Contract\ProvidesExporter;
use App\Support\Imports\ImportDefinition;
use App\Support\Imports\ImportDefinitionRegistry;
use App\Support\Imports\ImportService;
use App\Support\Imports\Value\ImportPlan;
use App\Support\Imports\Value\ImportSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use LogicException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse as SymfonyStreamedResponse;

/**
 * The import surface: catalog (what can be imported), template download,
 * dry-run preview (row statuses, nothing persisted), and confirm (persist
 * through the definition's persister). One controller for every entity —
 * entity knowledge lives in the definitions (OCP).
 */
final class ImportController
{
    public function __construct(
        private readonly ImportService $imports,
        private readonly ImportDefinitionRegistry $definitions,
        private readonly ExportService $exports,
    ) {}

    /**
     * The importable entities for the UI picker.
     */
    public function entities(): JsonResponse
    {
        $entities = [];

        foreach ($this->definitions->all() as $definition) {
            $entities[] = [
                'entity' => $definition->name(),
                'label' => $definition->label(),
            ];
        }

        return response()->json(['data' => $entities]);
    }

    /**
     * A fresh fill-and-import template for one entity, in the chosen
     * format — the export kernel's template pipeline, the identical shape
     * the import reader validates.
     */
    public function template(Request $request, string $entity)
    {
        $definition = $this->resolve($entity);
        $format = $this->formatFrom($request, default: 'xlsx');

        $file = $this->exports->template(
            $this->exporterFor($definition),
            new ExportRequest(format: $format, options: new ExportOptions(bom: $format === 'csv')),
        );

        return $this->stream($file);
    }

    /**
     * Dry-run: map + validate the upload and return the plan (mapping
     * summary + per-row statuses). NOTHING is persisted.
     */
    public function dryRun(Request $request, string $entity): JsonResponse
    {
        $plan = $this->planFor($request, $this->resolve($entity));

        return response()->json(['data' => $plan->toArray()]);
    }

    /**
     * Confirm: re-validate the same upload and persist it through the
     * definition's persister; returns the outcome (created/updated/
     * rejected with spreadsheet row numbers).
     */
    public function confirm(Request $request, string $entity): JsonResponse
    {
        $definition = $this->resolve($entity);
        $plan = $this->planFor($request, $definition);

        $outcome = $definition->persister()->persist($plan);

        return response()->json(['data' => $outcome->toArray()]);
    }

    /**
     * Validate the upload, then dry-run it against the definition.
     */
    private function planFor(Request $request, ImportDefinition $definition): ImportPlan
    {
        /** @var UploadedFile $file */
        $file = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'format' => ['sometimes', Rule::in(['xlsx', 'csv'])],
        ])['file'];

        try {
            return $this->imports->dryRun(
                ImportSource::fromUploaded($file, $this->formatFrom($request, default: 'xlsx')),
                $definition->acceptedColumns(),
                $definition->requiredRowKeys(),
                $definition->validator(),
                $definition->requiredTemplateColumns(),
                $definition instanceof NormalizesValues ? $definition : null,
            );
        } catch (RuntimeException $e) {
            // Unreadable file, missing anchors, unsupported schema — the
            // file itself is the problem, so 422 with the reason.
            abort(422, $e->getMessage());
        }
    }

    /**
     * The tabular exporter backing the entity's template download.
     * Definitions that can offer templates implement ProvidesExporter.
     */
    private function exporterFor(ImportDefinition $definition): TabularExporter
    {
        if ($definition instanceof ProvidesExporter) {
            return $definition->importExporter();
        }

        throw new LogicException("The entity [{$definition->name()}] does not offer templates.");
    }

    /**
     * Resolve the entity or 404 — a wrong entity name in the URL is a
     * routing-level problem, not a validation problem.
     */
    private function resolve(string $entity): ImportDefinition
    {
        if (! $this->definitions->has($entity)) {
            abort(404, "Unknown import entity [{$entity}].");
        }

        return $this->definitions->get($entity);
    }

    /**
     * Stream an ExportFile as a download response (the same pattern the
     * export endpoints use — streamDownload so tests and HTTP both work).
     */
    private function stream(ExportFile $file): SymfonyStreamedResponse
    {
        return response()->streamDownload(function () use ($file): void {
            $out = fopen('php://output', 'w');
            $file->copyTo($out);
            fclose($out);
        }, $this->exports->dispositionFilename($file), [
            'Content-Type' => $file->mimeType,
            'Cache-Control' => 'no-store',
        ]);
    }

    private function formatFrom(Request $request, string $default): string
    {
        $format = $request->query('format', $request->input('format', $default));

        return in_array($format, ['xlsx', 'csv'], true) ? $format : $default;
    }
}
