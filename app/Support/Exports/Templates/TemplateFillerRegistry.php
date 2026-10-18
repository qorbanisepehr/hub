<?php

namespace App\Support\Exports\Templates;

use App\Support\Exports\DocumentRendererRegistry;

/**
 * The filler for each format a template can be written in.
 *
 * A sibling of {@see DocumentRendererRegistry}, and shaped
 * like it on purpose: a format is filled by ONE registered implementation, and
 * adding one is a registration rather than an edit to the service that needs it.
 *
 * THE LOOKUP FAILS BY NAME RATHER THAN RETURNING NULL, because the caller has
 * already decided to fill a template and cannot do anything sensible with a
 * missing implementation. A template row exists only because a format was
 * accepted at upload, so a filler that is not registered is a wiring mistake -
 * and a wiring mistake named in the message is found in a second, where a null
 * becomes "no template" somewhere four frames away.
 */
final class TemplateFillerRegistry
{
    /** @var array<string, TemplateFiller> */
    private array $fillers = [];

    public function register(TemplateFiller $filler): void
    {
        $this->fillers[$filler->format()] = $filler;
    }

    /**
     * Every format a template may be written in.
     *
     * @return list<string>
     */
    public function formats(): array
    {
        return array_keys($this->fillers);
    }

    public function has(string $format): bool
    {
        return isset($this->fillers[$format]);
    }

    public function get(string $format): TemplateFiller
    {
        return $this->fillers[$format] ?? throw new \InvalidArgumentException(sprintf(
            'No template filler is registered for [%s]. Registered: %s.',
            $format,
            $this->formats() === [] ? 'none' : implode(', ', $this->formats()),
        ));
    }
}
