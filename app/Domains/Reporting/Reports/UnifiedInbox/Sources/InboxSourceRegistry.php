<?php

namespace App\Domains\Reporting\Reports\UnifiedInbox\Sources;

/**
 * The set of sources the unified inbox serves (V2 §63). Registration-based
 * like the export WriterRegistry — adding a system is a binding + one
 * registration, never an edit to the report (OCP). Deliberately not a
 * service-discovery framework.
 */
final class InboxSourceRegistry
{
    /** @var array<string, InboxSource> */
    private array $sources = [];

    public function register(InboxSource $source): void
    {
        $this->sources[$source->key()] = $source;
    }

    /**
     * @param  list<string>  $keys  Requested subset; empty = all.
     * @return list<InboxSource>
     */
    public function forQuery(array $keys): array
    {
        if ($keys === []) {
            return array_values($this->sources);
        }

        $selected = [];

        foreach ($keys as $key) {
            if (isset($this->sources[$key])) {
                $selected[] = $this->sources[$key];
            }
        }

        return $selected;
    }

    /**
     * @return array<string, string> key => label
     */
    public function labels(): array
    {
        $labels = [];

        foreach ($this->sources as $key => $source) {
            $labels[$key] = $source->label();
        }

        return $labels;
    }
}
