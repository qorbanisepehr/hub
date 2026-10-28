<?php

namespace App\Support\Exports\Templates;

use App\Support\Exports\Contract\ProvidesTemplateLabels;
use App\Support\External\PersianDateTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The placeholders every template may use, whatever domain it belongs to.
 *
 * A template field is published by a domain — the kernel never guesses, because only
 * the domain knows what a meeting has. These four are the exception, and they are the
 * exception because every domain has them: the export has a date, the record has a
 * creation date, and the domain and the record have names.
 *
 * `{{content}}` is the fifth and needs no entry here: it is the marker that asks for
 * the generated document, and {@see TemplateKind} reads it rather than filling it.
 *
 * DATES ARE JALALI, because every other date a person reads in this application is,
 * and a template that mixes the two calendars in one page is a page nobody trusts.
 * {@see PersianDateTime} formats them, so the shape here is the same shape the rest
 * of the hub uses rather than a fourth opinion about what a Persian date looks like.
 *
 * A VALUE THAT DOES NOT EXIST IS NOT FILLED, and that is the rule worth stating.
 * `{{created_date}}` on a record with no timestamp and `{{entity_label}}` on a domain
 * that does not publish labels both leave the macro exactly as the author typed it.
 * Printing an empty string would produce a document that looks finished and says
 * nothing where a word belongs, and nobody reading a certificate can tell that apart
 * from a certificate with no date on it.
 */
final class GeneralValues
{
    /** When this export was produced. */
    public const EXPORT_DATE = 'export_date';

    /** When the record was created. */
    public const CREATED_DATE = 'created_date';

    /** What this domain's documents are about. */
    public const DOMAIN_LABEL = 'domain_label';

    /** Which record this document is about. */
    public const ENTITY_LABEL = 'entity_label';

    /**
     * Every general placeholder, in the order the cheat sheet shows them.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return [
            self::EXPORT_DATE,
            self::CREATED_DATE,
            self::DOMAIN_LABEL,
            self::ENTITY_LABEL,
        ];
    }

    /**
     * The general values this export can actually produce.
     *
     * Keyed by placeholder name, and ABSENT where there is nothing to say — the
     * fillers use a missing key to mean "leave the macro alone".
     *
     * @return array<string, string>
     */
    public function resolve(object $entity, TemplateFieldRegistry $registry): array
    {
        $values = [
            self::EXPORT_DATE => $this->jalali(CarbonImmutable::now()),
        ];

        $created = $this->createdAt($entity);

        if ($created !== null) {
            $values[self::CREATED_DATE] = $this->jalali($created);
        }

        // A domain that publishes no labels simply has none of these. Not an error,
        // not a fallback to the slug: a slug in a printed document is worse than a
        // macro the author can see and fix.
        if ($registry instanceof ProvidesTemplateLabels) {
            $domain = $registry->domainLabel();

            if (trim($domain) !== '') {
                $values[self::DOMAIN_LABEL] = $domain;
            }

            $label = $registry->entityLabel($entity);

            if (trim($label) !== '') {
                $values[self::ENTITY_LABEL] = $label;
            }
        }

        return $values;
    }

    /**
     * When the record was created, if it was, and if the record can say.
     *
     * Read with `??` rather than `property_exists`, and that is not a style choice.
     * `created_at` on an Eloquent model is not a DECLARED property — it lives in the
     * attribute bag and is reached through `__get` — so asking whether the property
     * exists says no to every record in the application and the date silently never
     * appears. `??` goes through `__isset`, which is where the model answers it, and
     * on a plain object with no such property it yields null without a notice rather
     * than throwing in the middle of an export.
     */
    private function createdAt(object $entity): ?CarbonImmutable
    {
        $created = $entity->created_at ?? null;

        if ($created instanceof CarbonInterface) {
            return CarbonImmutable::instance($created);
        }

        if (is_string($created) && $created !== '') {
            return CarbonImmutable::parse($created);
        }

        return null;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function jalali(CarbonImmutable $date): string
    {
        return PersianDateTime::toJalaliDate($date);
    }
}
