<?php

namespace App\Console\Commands;

use App\Support\Imports\Contract\NormalizesValues;
use App\Support\Imports\ImportDefinitionRegistry;
use App\Support\Imports\ImportService;
use App\Support\Imports\Value\ImportPlan;
use App\Support\Imports\Value\ImportRowError;
use App\Support\Imports\Value\ImportSource;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

/**
 * The import kernel's scripted entry point (architecture plan §5.1): the
 * same pipeline the settings tab drives over HTTP, addressed by an
 * operator from the CLI so bulk loads can be scripted and scheduled.
 *
 * Exit codes for scripts: SUCCESS (0) — every row landed; FAILURE (1) —
 * the file was processed but some rows were rejected at validation or at
 * write time (valid rows are still persisted, exactly like the HTTP
 * confirm); INVALID (2) — operator error (unknown entity, missing file,
 * unknown format) or a file the kernel refuses outright (unreadable
 * bytes, missing anchors, unsupported schema).
 */
class ImportRunCommand extends Command
{
    protected $signature = 'imports:run
        {entity : Import entity registered in ImportDefinitionRegistry, e.g. employees}
        {file : Path to the xlsx/csv file to import}
        {--format= : Override the format (xlsx|csv); defaults to the file extension}
        {--dry-run : Validate and preview only — nothing is persisted}';

    protected $description = 'Run a tabular import through the kernel: validate, preview, persist';

    public function __construct(
        private readonly ImportDefinitionRegistry $definitions,
        private readonly ImportService $imports,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $entity = (string) $this->argument('entity');

        if (! $this->definitions->has($entity)) {
            $this->error(sprintf(
                'Unknown import entity [%s]. Available: %s',
                $entity,
                implode(', ', array_keys($this->definitions->all())) ?: '(none)',
            ));

            return self::INVALID;
        }

        $definition = $this->definitions->get($entity);
        $path = (string) $this->argument('file');
        $format = strtolower((string) ($this->option('format') ?: pathinfo($path, PATHINFO_EXTENSION)));

        if (! in_array($format, ['xlsx', 'csv'], true)) {
            $this->error(sprintf(
                'Unrecognized format [%s] for [%s] — pass --format=xlsx or --format=csv.',
                $format,
                $path,
            ));

            return self::INVALID;
        }

        try {
            $source = ImportSource::fromPath($path, $format);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::INVALID;
        }

        try {
            $plan = $this->imports->dryRun(
                $source,
                $definition->acceptedColumns(),
                $definition->requiredRowKeys(),
                $definition->validator(),
                $definition->requiredTemplateColumns(),
                $definition instanceof NormalizesValues ? $definition : null,
            );
        } catch (RuntimeException $e) {
            // Unreadable bytes, missing anchors, unsupported schema — the
            // file itself is the problem (the HTTP surface maps this 422).
            $this->error($e->getMessage());

            return self::INVALID;
        }

        $hasRejections = ! $plan->isValid();

        $this->renderSummary($plan, (bool) $this->option('dry-run'));

        if ((bool) $this->option('dry-run')) {
            return $hasRejections ? self::FAILURE : self::SUCCESS;
        }

        $outcome = $definition->persister()->persist($plan);

        $this->info(sprintf(
            'Created: %d, updated: %d, processed: %d.',
            $outcome->created,
            $outcome->updated,
            $outcome->processed,
        ));

        if (! $outcome->isClean()) {
            $this->warn(sprintf('%d row(s) were rejected at write time:', count($outcome->rejected)));
            $this->renderErrors($outcome->rejected);
        }

        return ($hasRejections || ! $outcome->isClean()) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * The dry-run's picture of the file: how it will land and which rows
     * the validator refused, addressed by spreadsheet row number like
     * every other report of the kernel.
     */
    private function renderSummary(ImportPlan $plan, bool $dryRun): void
    {
        if ($dryRun) {
            $this->info('DRY RUN — nothing will be persisted.');
        }

        $this->info(sprintf(
            'Rows: %d total, %d importable, %d rejected.',
            $plan->total,
            count($plan->rows),
            count($plan->rejected),
        ));

        if ($plan->mapping['unknown'] !== []) {
            $this->warn('Unknown columns (ignored): '.implode(', ', $plan->mapping['unknown']));
        }

        if ($plan->mapping['missing_required'] !== []) {
            $this->warn('Missing required columns: '.implode(', ', $plan->mapping['missing_required']));
        }

        $this->renderErrors($plan->rejected);
    }

    /**
     * @param  list<ImportRowError>  $errors
     */
    private function renderErrors(array $errors): void
    {
        if ($errors === []) {
            return;
        }

        $this->table(
            ['Spreadsheet row', 'Errors'],
            array_map(fn (ImportRowError $error): array => [
                $error->rowNumber,
                collect($error->errors)
                    ->map(fn (array $messages, string $key): string => $key.': '.implode(' ', $messages))
                    ->implode("\n"),
            ], $errors),
        );
    }
}
