<?php

namespace App\Support\Exports\Templates;

use App\Contracts\Settings\Setting;
use App\Domains\Settings\Exceptions\InvalidSetting;

/**
 * One uploaded `.docx` an export can be built from.
 *
 * Stored as a setting rather than in a table of its own. It has no relations,
 * no lifecycle beyond written-or-not, and nothing queries across templates, which
 * is the shape the settings store exists for; a table would have brought a
 * migration and an orphan-file problem for rows that are really just typed keys.
 *
 * THE FILE IS ON A DISK, NOT IN THE ROW. The private half of a setting is an
 * encrypted `string` map, which would have stored the .docx as base64 — and
 * listing twenty templates would then decrypt twenty files to read twenty names.
 * The file's path is DERIVED FROM THE KEY instead of stored, so there is no
 * second place for it to disagree with the row that owns it and no way for a row
 * to point at another template's file.
 *
 * A template is a FRAME, not a replacement. The document is always generated and
 * added to the file, and every placeholder in the file is always filled — in the
 * body, in the headers and in the footers. A file may therefore contain nothing
 * but `{{title}}`, nothing but a crest, or both, and all three behave the same
 * way; the only thing that changes the output is whether a template was chosen.
 *
 * KEYS ARE `word/{domain}/{slug}[/{entity}]`. The `word/` prefix is what keeps
 * this class from colliding with {@see ExportTemplate} over the shared `exports`
 * group, and the slug is a machine handle the person never sees — the readable
 * name is in the value, so a Persian name needs no transliteration to be storable.
 */
final class WordTemplate implements Setting
{
    public const GROUP = 'exports';

    /** The namespace these keys live under. See {@see handles()}. */
    public const PREFIX = 'word/';

    /** Where uploaded files are written, relative to the private disk. */
    public const FILE_DIRECTORY = 'word';

    private const SEPARATOR = '/';

    private function __construct(
        private readonly string $domain,
        private readonly string $slug,
        private readonly ?string $entity,
        private readonly string $name,
        private readonly int $size,
        private readonly string $mime,
        private readonly bool $isDefault,
    ) {}

    public static function group(): string
    {
        return self::GROUP;
    }

    /**
     * Whether the key is a Word template's.
     *
     * Same two checks as {@see ExportTemplate::handles()}, and for the same
     * reasons: the prefix keeps the two kinds apart in a shared group, and the
     * shape check refuses a malformed tail where it would be read rather than
     * trusting whoever wrote it.
     */
    public static function handles(string $key): bool
    {
        return self::split($key) !== null;
    }

    public static function keyPrefix(): ?string
    {
        return self::PREFIX;
    }

    public function key(): string
    {
        return self::PREFIX.$this->keyTail();
    }

    /**
     * The key without its namespace, and the file's path without its extension.
     *
     * Both come from the same place so they cannot drift: the file lives at
     * `{FILE_DIRECTORY}/{keyTail}.docx`, which is why an entity-scoped template
     * nests its own directory instead of overwriting a domain-wide one.
     */
    public function keyTail(): string
    {
        return $this->entity === null
            ? $this->domain.self::SEPARATOR.$this->slug
            : $this->domain.self::SEPARATOR.$this->slug.self::SEPARATOR.$this->entity;
    }

    public function domain(): string
    {
        return $this->domain;
    }

    /** The machine handle within the domain. Never shown to a person. */
    public function slug(): string
    {
        return $this->slug;
    }

    /** The entity this template belongs to, as the tail of its key. */
    public function entity(): ?string
    {
        return $this->entity;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function mime(): string
    {
        return $this->mime;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    /**
     * Where the uploaded file lives, on the private disk.
     *
     * Derived, never taken from the request: the key segments are the only input,
     * and they were validated as legal when the key was accepted.
     */
    public function filePath(): string
    {
        return self::FILE_DIRECTORY.self::SEPARATOR.$this->keyTail().'.docx';
    }

    public static function make(
        string $domain,
        string $slug,
        ?string $entity,
        string $name,
        int $size,
        string $mime,
        bool $isDefault = false,
    ): self {
        $template = new self($domain, $slug, $entity, $name, $size, $mime, $isDefault);

        // Round-tripped through the key so an illegal slug or entity is refused
        // where the caller can still see which value was wrong, rather than at
        // the first read of the row it wrote.
        if (self::split($template->key()) === null) {
            throw InvalidSetting::malformed(
                self::class,
                $template->key(),
                sprintf(
                    'cannot store a template under this key. It reads [%s].',
                    $template->key(),
                ),
            );
        }

        if (trim($name) === '') {
            throw InvalidSetting::malformed(
                self::class,
                $template->key(),
                'a template needs a name a person can recognise.',
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
            'slug' => $this->slug,
            'entity' => $this->entity,
            'name' => $this->name,
            'size' => $this->size,
            'mime' => $this->mime,
            'is_default' => $this->isDefault,
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
     * Rebuilt strictly, and the key wins over the payload.
     *
     * The key is what every lookup is written against, so a row stored under one
     * key claiming to be about another template is the one inconsistency that
     * would make one file answer to another's name — or answer at all when its
     * derived path points somewhere else entirely.
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
                'a template key reads word/{domain}/{slug}[/{entity}]. Given: '.$key.'.',
            );
        }

        [$domain, $slug, $entity] = $split;

        if (($value['domain'] ?? $domain) !== $domain) {
            throw InvalidSetting::malformed(
                self::class,
                $key,
                sprintf('stored domain [%s] disagrees with its key [%s].', (string) $value['domain'], $domain),
            );
        }

        if (($value['slug'] ?? $slug) !== $slug) {
            throw InvalidSetting::malformed(
                self::class,
                $key,
                sprintf('stored slug [%s] disagrees with its key [%s].', (string) $value['slug'], $slug),
            );
        }

        $name = $value['name'] ?? null;

        if (! is_string($name) || trim($name) === '') {
            throw InvalidSetting::malformed(
                self::class,
                $key,
                'name must be a non-empty string. Given: '.get_debug_type($name).'.',
            );
        }

        $size = $value['size'] ?? 0;

        if (! is_int($size) && ! (is_string($size) && ctype_digit($size))) {
            throw InvalidSetting::malformed(
                self::class,
                $key,
                'size must be an integer. Given: '.get_debug_type($size).'.',
            );
        }

        return new self(
            $domain,
            $slug,
            $entity,
            $name,
            (int) $size,
            is_string($value['mime'] ?? null) ? $value['mime'] : '',
            (bool) ($value['is_default'] ?? false),
        );
    }

    /**
     * A key as [domain, slug, entity|null], or null when it is not shaped like one.
     *
     * @return array{0: string, 1: string, 2: string|null}|null
     */
    public static function split(string $key): ?array
    {
        if (! str_starts_with($key, self::PREFIX)) {
            return null;
        }

        $tail = substr($key, strlen(self::PREFIX));

        if ($tail === '' || str_ends_with($tail, self::SEPARATOR)) {
            return null;
        }

        $segments = explode(self::SEPARATOR, $tail);

        // A domain and a slug, and an entity that may itself nest (`session/42`).
        if (count($segments) < 2) {
            return null;
        }

        foreach ($segments as $segment) {
            if (! self::isLegalSegment($segment)) {
                return null;
            }
        }

        [$domain, $slug] = $segments;

        $entity = count($segments) > 2
            ? implode(self::SEPARATOR, array_slice($segments, 2))
            : null;

        return [$domain, $slug, $entity];
    }

    private static function isLegalSegment(string $segment): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $segment) === 1;
    }
}
