<?php

namespace App\Support\Exports\Templates;

use App\Contracts\Settings\Setting;
use App\Domains\Settings\Exceptions\InvalidSetting;

/**
 * Which sections a rendered document contains, and in what order.
 *
 * NAMED FOR WHAT IT HOLDS, BECAUSE IT SITS BESIDE A `FileTemplate`. Two classes in
 * one settings group, and the old name — `ExportTemplate` — said neither what this
 * one held nor how it differed from the other. A reader arriving at the pair had no
 * way to tell that one is a template FILE and this one is a list of SECTION NAMES.
 *
 * The printed form of a record is a decision, not a fact: a board secretariat
 * files the accepted minutes with the attendance sheet and does not care about
 * tags, while a governance reviewer wants the registry numbers and neither.
 * Both are the same record, so that difference belongs in configuration rather
 * than in a deploy.
 *
 * SECTION ORDER IS THE TEMPLATE. There is no separate "order" field, because a
 * list already has one and two fields describing one thing is two that can
 * disagree. Reordering is reordering the list.
 *
 * THIS KNOWS SHAPE, NOT VOCABULARY. It can tell that `sections` is a list of
 * non-empty strings; it cannot tell that `agenda` is a board meeting's section
 * and a meaningless word in a CV. That half belongs to the domain, which is the
 * same split the Notification domain draws: the contract and the mechanism are
 * shared, the words are the domain's.
 *
 * KEYS ARE PREFIXED AND DOMAIN-FIRST. `sections/boardmeeting` is the domain's
 * default and `sections/boardmeeting/session/42` is one meeting's override, so
 * the most specific answer sorts last under any listing and the most general one
 * is the shortest key in its group. Entity keys nest rather than flatten because
 * an entity id is only meaningful under its own domain's namespace - a bare `42`
 * would claim meeting 42 and CV 42 at once.
 *
 * THE `sections/` PREFIX IS LOAD-BEARING, NOT COSMETIC. `handles()` is what the
 * store uses to decide which kind of setting owns a key, and the `exports` group
 * holds more than one kind — {@see FileTemplate} keeps its uploaded files under
 * `template/`. Without a prefix on both sides, a key like
 * `template/boardmeeting/official` parses as domain `template` with entity
 * `boardmeeting/official` and BOTH classes could claim it: a listing starts
 * returning another kind's rows, and a delete quietly stops matching anything.
 * Two namespaces that cannot overlap is the same discipline the AI registry
 * applies to `providers/` and `agents/`.
 */
final class SectionTemplate implements Setting
{
    public const GROUP = 'exports';

    /** The namespace these keys live under. See {@see handles()}. */
    public const PREFIX = 'sections/';

    /** A key that scopes a template to one entity inside its domain. */
    private const ENTITY_SEPARATOR = '/';

    private function __construct(
        private readonly string $domain,
        private readonly ?string $entity,
        /** @var list<string> */
        private readonly array $sections,
    ) {}

    public static function group(): string
    {
        return self::GROUP;
    }

    /**
     * Whether the key is a SECTION template's.
     *
     * The prefix check, then the shape check. Both are needed: the prefix is what
     * keeps this class and {@see FileTemplate} from claiming each other's keys,
     * and the shape check is what keeps a malformed tail out of the store — a
     * key with the right prefix but an illegal segment is caught here, on every
     * read, rather than by trusting whoever wrote it.
     */
    public static function handles(string $key): bool
    {
        return self::split($key) !== null;
    }

    /**
     * Narrows a listing to this kind, which is the whole point of having a
     * prefix on both sides of a shared group.
     */
    public static function keyPrefix(): ?string
    {
        return self::PREFIX;
    }

    public function key(): string
    {
        return self::PREFIX.$this->keyTail();
    }

    /**
     * The key without its namespace: `{domain}` or `{domain}/{entity}`.
     *
     * Public because it is what the two setting classes are separated by, and
     * {@see handles()} is the authority on both sides.
     */
    public function keyTail(): string
    {
        return $this->entity === null
            ? $this->domain
            : $this->domain.self::ENTITY_SEPARATOR.$this->entity;
    }

    public function domain(): string
    {
        return $this->domain;
    }

    /** The entity this overrides, as the tail of its key. Null for a domain default. */
    public function entity(): ?string
    {
        return $this->entity;
    }

    /**
     * @return list<string>
     */
    public function sections(): array
    {
        return $this->sections;
    }

    /**
     * @param  list<string>  $sections
     */
    /**
     * The store key for a domain and entity.
     *
     * Built here and nowhere else. The same pair is assembled in three places —
     * read, write and delete — and a caller that spelled it out in only one of
     * them produces a delete that silently matches nothing and a template that
     * appears to be configured while no longer applying. One builder is the whole
     * fix; a comment asking people to be careful is not.
     */
    public static function keyFor(string $domain, ?string $entity = null): string
    {
        return self::PREFIX.($entity === null ? $domain : $domain.self::ENTITY_SEPARATOR.$entity);
    }

    public static function make(string $domain, ?string $entity, array $sections): self
    {
        $template = new self($domain, $entity, array_values($sections));

        // Round-tripped through the key so a domain or entity that cannot be
        // spelled is refused here, where the caller can still see which value was
        // wrong, rather than at the first read of the row it wrote.
        if (self::split($template->key()) === null) {
            throw InvalidSetting::malformed(
                self::class,
                $template->key(),
                sprintf(
                    'cannot store a template under this key. A domain is a name like [boardmeeting]; '
                        .'an entity is appended after a slash, like [session/42].',
                    $template->key(),
                ),
            );
        }

        return $template;
    }

    /**
     * @return array<string, mixed>
     */
    public function toValue(): array
    {
        return [
            'domain' => $this->domain,
            'entity' => $this->entity,
            'sections' => $this->sections,
        ];
    }

    /**
     * @return array<string, string>|null
     */
    public function toSecrets(): ?array
    {
        return null;
    }

    /**
     * The stored half is rebuilt strictly: a `sections` that is not a list of
     * non-empty strings is a configuration error and throws, exactly as a driver
     * name that will not parse does. The section *names* are not checked here —
     * which of them exist is the owning domain's question, and a template naming
     * a section from a newer version has to survive being read by this one.
     *
     * @param  array<string, mixed>  $value
     * @param  array<string, string>|null  $secrets
     */
    public static function fromStored(string $key, array $value, ?array $secrets): static
    {
        $split = self::split($key);

        if ($split === null) {
            throw InvalidSetting::malformed(
                self::class,
                $key,
                'a template key is a domain name, optionally followed by /{entity}. Given: '.$key.'.',
            );
        }

        [$domain, $entity] = $split;

        // The key is the address and the payload is the content; where they
        // disagree the key wins, because it is what every lookup is written
        // against. A row stored under one key claiming to be about another is
        // the one inconsistency that would make a template apply to the wrong
        // domain entirely.
        if (($value['domain'] ?? $domain) !== $domain) {
            throw InvalidSetting::malformed(
                self::class,
                $key,
                sprintf('stored domain [%s] disagrees with its key [%s].', (string) $value['domain'], $domain),
            );
        }

        $sections = $value['sections'] ?? null;

        if (! is_array($sections) || ! array_is_list($sections)) {
            throw InvalidSetting::malformed(
                self::class,
                $key,
                'sections must be a list of section names. Given: '.get_debug_type($sections).'.',
            );
        }

        foreach ($sections as $section) {
            if (! is_string($section) || trim($section) === '') {
                throw InvalidSetting::malformed(
                    self::class,
                    $key,
                    'every section name must be a non-empty string. Given: '
                        .(is_string($section) ? '['.$section.']' : get_debug_type($section)).'.',
                );
            }
        }

        return new self($domain, $entity, array_values($sections));
    }

    /**
     * A key as [domain, entity|null], or null when it is not shaped like one.
     *
     * The same pattern as the AI registry's key space, for the same reason: two
     * spellings of what this store accepts would make a row that one of them
     * writes and the other refuses.
     *
     * @return array{0: string, 1: string|null}|null
     */
    public static function split(string $key): ?array
    {
        if (! str_starts_with($key, self::PREFIX)) {
            return null;
        }

        $tail = substr($key, strlen(self::PREFIX));

        if ($tail === '' || str_ends_with($tail, self::ENTITY_SEPARATOR)) {
            return null;
        }

        [$domain, $remainder] = array_pad(explode(self::ENTITY_SEPARATOR, $tail, 2), 2, null);

        if (! self::isLegalSegment($domain)) {
            return null;
        }

        if ($remainder === null) {
            return [$domain, null];
        }

        // The tail nests: `session/42` names an entity kind and an id, and both
        // halves are checked because the whole key is what has to survive a URL
        // segment and a config lookup.
        foreach (explode(self::ENTITY_SEPARATOR, $remainder) as $segment) {
            if (! self::isLegalSegment($segment)) {
                return null;
            }
        }

        return [$domain, $remainder];
    }

    private static function isLegalSegment(string $segment): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $segment) === 1;
    }
}
