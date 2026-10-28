<?php

namespace App\Support\Exports\Templates;

use App\Contracts\Settings\Setting;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;

/**
 * Which sections a document should be rendered with, most specific answer first.
 *
 * Three answers are possible and they are asked in a fixed order: the entity's
 * own template, then its domain's, then the domain's built-in default. Each
 * step is a whole template rather than a patch of one, so a more specific
 * answer is complete on its own - somebody who overrides one meeting's template
 * is not quietly inheriting a domain default's sections and having to
 * enumerate all of them to remove one.
 *
 * THE FALLBACK IS THE CALLER'S. `default` is a parameter because only a domain
 * knows its own section names, and a resolver in Support holding one would be
 * the kernel holding a list of which domains exist - the same coupling the
 * settings key space deliberately avoids. Passing the default in keeps this
 * class knowing about specificity and nothing else.
 *
 * A TEMPLATE IS NOT AN ERROR. A malformed row is refused at read time by
 * {@see SectionTemplate::fromStored()}, but a row naming a section this version
 * does not have is dropped and the rest honoured: a template written on a newer
 * deploy has to survive a rollback, and refusing the read would break rendering
 * altogether over one name. A section that quietly stops appearing is the
 * cheaper failure than a document that will not print.
 */
final class TemplateResolver
{
    public function __construct(
        private readonly SystemSettingsRepositoryInterface $settings,
    ) {}

    /**
     * @param  list<string>  $default  the domain's own sections, used when nothing is stored
     * @return list<string>
     */
    public function sections(string $domain, ?string $entity, array $default): array
    {
        $template = $this->stored($domain, $entity) ?? $this->stored($domain);

        if ($template === null) {
            return $default;
        }

        // Filtered by the TEMPLATE's order, not the default's: a template that
        // reorders sections actually reorders them, which is most of what
        // "declaring a template" is for. Names the domain does not have are
        // dropped, and a name listed twice is kept once — a section printed
        // twice is never what anybody meant.
        $sections = [];

        foreach ($template->sections() as $section) {
            if (in_array($section, $default, true) && ! in_array($section, $sections, true)) {
                $sections[] = $section;
            }
        }

        return $sections;
    }

    /**
     * Drop a stored template, entity override or domain default alike.
     *
     * Exists so the key is built here and nowhere else. It is assembled from a
     * domain and an entity in three places — read, write and delete — and a
     * caller that spelled it out in only one of them produces a delete that
     * silently matches nothing and a page that appears to still hold an
     * override forever.
     */
    public function forget(string $domain, ?string $entity = null): void
    {
        $this->settings->forget(SectionTemplate::class, SectionTemplate::keyFor($domain, $entity));
    }

    /**
     * The stored template for a domain or entity, for a settings screen.
     *
     * Null rather than a synthesised default so a caller can tell "configured"
     * from "inherited", which is the difference between showing an editor full
     * of values the administrator did not choose and showing it empty.
     */
    public function stored(string $domain, ?string $entity = null): ?SectionTemplate
    {
        $template = $this->settings->get(SectionTemplate::class, SectionTemplate::keyFor($domain, $entity));

        return $template instanceof SectionTemplate ? $template : null;
    }

    /**
     * Every template stored for a domain, entity overrides included.
     *
     * @return list<SectionTemplate>
     */
    public function forDomain(string $domain): array
    {
        return array_values(array_filter(
            $this->settings->all(SectionTemplate::class),
            fn (Setting $setting): bool => $setting instanceof SectionTemplate
                && $setting->domain() === $domain,
        ));
    }
}
