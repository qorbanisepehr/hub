<?php

namespace App\Support\Exports\Templates;

use App\Contracts\Settings\Setting;
use App\Domains\Settings\Exceptions\InvalidSetting;

/**
 * Which sections a rendered document contains, and in what order.
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
 * KEYS ARE DOMAIN-FIRST. `boardmeeting` is the domain's default and
 * `boardmeeting/session/42` is one meeting's override, so the most specific
 * answer sorts last under any listing and the most general one is the shortest
 * key in its group. Entity keys nest rather than flatten because an entity id
 * is only meaningful under its own domain's namespace - a bare `42` would claim
 * meeting 42 and CV 42 at once.
 */
final class ExportTemplate implements Setting
{
    public const GROUP = 'exports';

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
     * The whole group is templates, so anything shaped like a key is one. The
     * shape check rather than a list of known domains is deliberate: the store
     * must not need editing when a domain adds its first `DocumentSource`, or
     * the kernel would be holding a registry of which domains exist.
     */
    public static function handles(string $key): bool
    {
        return self::split($key) !== null;
    }

    /**
     * Null rather than the group name: the group holds nothing else, so
     * narrowing the query would exclude nothing while implying it had.
     */
    public static function keyPrefix(): ?string
    {
        return null;
    }

    public function key(): string
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
        if ($key === '' || str_starts_with($key, self::ENTITY_SEPARATOR) || str_ends_with($key, self::ENTITY_SEPARATOR)) {
            return null;
        }

        [$domain, $tail] = array_pad(explode(self::ENTITY_SEPARATOR, $key, 2), 2, null);

        if (! self::isLegalSegment($domain)) {
            return null;
        }

        if ($tail === null) {
            return [$domain, null];
        }

        // The tail nests: `session/42` names an entity kind and an id, and both
        // halves are checked because the whole key is what has to survive a URL
        // segment and a config lookup.
        foreach (explode(self::ENTITY_SEPARATOR, $tail) as $segment) {
            if (! self::isLegalSegment($segment)) {
                return null;
            }
        }

        return [$domain, $tail];
    }

    private static function isLegalSegment(string $segment): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $segment) === 1;
    }
}
