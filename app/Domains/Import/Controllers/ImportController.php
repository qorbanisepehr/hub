<?php

namespace App\Domains\Import\Controllers;

use App\Domains\Import\Requests\StoreDocumentBundleImportRequest;
use App\Support\Exports\Contract\TabularExporter;
use App\Support\Exports\ExportService;
use App\Support\Exports\Value\ExportFile;
use App\Support\Exports\Value\ExportOptions;
use App\Support\Exports\Value\ExportRequest;
use App\Support\Imports\Contract\DocumentBundleImporter;
use App\Support\Imports\Contract\NormalizesValues;
use App\Support\Imports\Contract\ProvidesExporter;
use App\Support\Imports\DocumentBundleImporterRegistry;
use App\Support\Imports\DocumentBundleImportService;
use App\Support\Imports\ImportDefinition;
use App\Support\Imports\ImportDefinitionRegistry;
use App\Support\Imports\ImportService;
use App\Support\Imports\Value\DocumentBundlePlan;
use App\Support\Imports\Value\ImportPlan;
use App\Support\Imports\Value\ImportSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
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
        private readonly DocumentBundleImporterRegistry $documentImporters,
        private readonly DocumentBundleImportService $documentImports,
    ) {}

    /**
     * The importable entities for the UI picker. Two arms share the
     * catalog: the tabular definitions (spreadsheet imports) and the
     * document-bundle importers (bulk scanned files) — the client splits
     * them by `kind` and reads the per-batch file cap from `max_files`.
     */
    public function entities(): JsonResponse
    {
        $entities = [];

        foreach ($this->definitions->all() as $definition) {
            $entities[] = [
                'entity' => $definition->name(),
                'label' => $definition->label(),
                'kind' => 'tabular',
            ];
        }

        // PHP discards the whole batch once max_file_uploads is exceeded, so
        // advertise the lower of the entity cap and the host limit — the UI
        // must never let a user cross it (0 means unlimited in php.ini).
        $hostFileCap = (int) ini_get('max_file_uploads');

        foreach ($this->documentImporters->all() as $definition) {
            $entities[] = [
                'entity' => $definition->name(),
                'label' => $definition->label(),
                'kind' => 'document',
                'max_files' => $hostFileCap > 0
                    ? min($definition->maxFiles(), $hostFileCap)
                    : $definition->maxFiles(),
            ];
        }

        return response()->json(['data' => $entities]);
    }

    /**
     * Document-bundle dry-run: parse every filename against the user's
     * pattern and report per-file statuses (ready / pattern / no_meeting /
     * duplicate) with the derived mapping. NOTHING is persisted.
     */
    public function documentDryRun(StoreDocumentBundleImportRequest $request, string $entity): JsonResponse
    {
        $plan = $this->documentBundlePlan($request, $entity);

        return response()->json(['data' => $plan->toArray()]);
    }

    /**
     * Document-bundle confirm: re-validate the same upload and persist the
     * ready files; the report names every file instead of failing the
     * whole thing (partial success is a normal result).
     */
    public function documentConfirm(StoreDocumentBundleImportRequest $request, string $entity): JsonResponse
    {
        $definition = $this->resolveBundle($entity);

        try {
            $outcome = $this->documentImports->confirm(
                $definition,
                $request->filesList(),
                $request->input('pattern'),
            );
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json([
            'data' => $outcome,
            'message' => __('import.document_bundle_imported', ['count' => count($outcome['imported'])]),
        ]);
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
     * The document-bundle plan for a validated request: resolve the entity,
     * dry-run the files, and turn a kernel-level pattern error into a 422.
     */
    private function documentBundlePlan(StoreDocumentBundleImportRequest $request, string $entity): DocumentBundlePlan
    {
        $definition = $this->resolveBundle($entity);

        try {
            return $this->documentImports->dryRun(
                $definition,
                $request->filesList(),
                $request->input('pattern'),
            );
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }
    }

    /**
     * Resolve a document-bundle entity or 404 at the routing level — a
     * tabular entity on the documents arm is the caller's mistake, not a
     * validation problem.
     */
    private function resolveBundle(string $entity): DocumentBundleImporter
    {
        if (! $this->documentImporters->has($entity)) {
            abort(404, "Unknown document import entity [{$entity}].");
        }

        return $this->documentImporters->get($entity);
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
