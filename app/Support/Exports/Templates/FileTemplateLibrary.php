<?php

namespace App\Support\Exports\Templates;

use App\Contracts\Settings\Setting;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
use App\Support\Exports\Exceptions\InvalidFileTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The library of Word templates a domain can export with.
 *
 * Management only — turning a template into a document is
 * {@see FileTemplateService}'s job, and the two are separate because their
 * questions are: this one is "which templates exist and which is the default",
 * that one is "what should this export produce".
 *
 * A ROW AND A FILE, KEPT IN STEP. Writing the row before the file would leave a
 * template that lists in the picker and fails at export; writing the file first
 * would leave bytes nothing points at. The row is claimed and the file written in
 * one transaction, so neither is visible without the other: a failed write rolls
 * the claim back, and the failure a person can hit is "the upload did not save"
 * rather than a template that appears and then breaks.
 */
final class FileTemplateLibrary
{
    public function __construct(
        private readonly SystemSettingsRepositoryInterface $settings,
        private readonly FileTemplateStore $store,
        private readonly FileTemplateInspector $inspector,
    ) {}

    /**
     * Every template stored for a domain AND A FORMAT, newest first, with the
     * default first.
     *
     * The format is a filter rather than a detail of the key, and it is the
     * filter a caller almost always wants: the Word picker must not offer a
     * Markdown frame, because a Word export cannot be built from one and
     * offering it would fail at the moment somebody chose it.
     *
     * @return list<FileTemplate>
     */
    public function forFormatDomain(string $format, string $domain, ?string $entity = null): array
    {
        $templates = array_values(array_filter(
            $this->settings->all(FileTemplate::class),
            fn (Setting $setting): bool => $setting instanceof FileTemplate
                && $setting->format() === $format
                && $setting->domain() === $domain
                && $setting->entity() === $entity,
        ));

        // The default leads, because the export button uses it when nobody has to
        // choose. The comparison is on the flag alone and PHP's sort is stable,
        // so the store's key order survives within each group and two people
        // looking at the same library see the same list.
        usort(
            $templates,
            fn (FileTemplate $a, FileTemplate $b): int => (int) $b->isDefault() <=> (int) $a->isDefault(),
        );

        return $templates;
    }

    /**
     * The template this domain and format filed under a slug, or null.
     *
     * A slug that could never be a key answers null WITHOUT asking the store.
     * The settings repository throws on a key its setting class does not handle,
     * which is right for a key from inside the app and wrong for one that came
     * off a query string: "which template is `/etc/passwd`" is not an error, it
     * is a template nobody has. Without this guard a caller who typed a Persian
     * name instead of a slug got a 500 where a 404 was the honest answer.
     */
    public function find(string $format, string $domain, string $slug, ?string $entity = null): ?FileTemplate
    {
        $key = $this->keyFor($format, $domain, $slug, $entity);

        if (! FileTemplate::handles($key)) {
            return null;
        }

        $template = $this->settings->get(FileTemplate::class, $key);

        return $template instanceof FileTemplate ? $template : null;
    }

    /**
     * What a file is, before it is stored.
     *
     * Public because the answer is worth having at the moment of uploading rather
     * than at the moment of exporting: whether the generated document is written
     * into a template at all is {@see TemplateKind}'s decision and is read off the
     * file, so the person who uploaded it is the only one who can find out, and
     * the export is far too late to tell them.
     *
     * @return array{size: int, kind: string, placeholders: list<string>}
     *
     * @throws InvalidFileTemplate
     */
    public function inspect(UploadedFile $file, string $format): array
    {
        return $this->inspector->inspect($file, $format);
    }

    /**
     * Store an uploaded file as a new template.
     *
     * The name is what a person reads and the slug is what the store addresses,
     * so a Persian name needs no transliteration: the slug is derived from the
     * FILE name and is never shown. When the file name yields nothing legal — a
     * fully Persian name, say — a short random handle is used instead, because
     * inventing a transliteration would produce a slug that looks machine-made in
     * a key somebody may read in a log.
     *
     * @param  array{size: int, kind: string, placeholders: list<string>}|null  $inspected  A caller that already inspected this file passes the result; omitted to inspect here.
     *
     * @throws InvalidFileTemplate
     */
    public function upload(
        UploadedFile $file,
        string $format,
        string $domain,
        string $name,
        ?string $entity = null,
        bool $isDefault = false,
        ?int $uploadedBy = null,
        ?array $inspected = null,
    ): FileTemplate {
        // Before anything is read from the file a second time, so a file refused
        // for its size or for carrying macros never reaches the store.
        $inspected ??= $this->inspector->inspect($file, $format);

        $bytes = $this->bytesOf($file);

        // The slug is claimed before any bytes are written, and both happen in one
        // transaction so a row is never visible without its file. `put()` would let
        // a second upload of `official.docx` silently replace the first one's row
        // and bytes; a claim is an insert against the primary key, so exactly one
        // wins and the loser moves to the next free slug.
        return DB::transaction(function () use ($file, $inspected, $format, $domain, $entity, $name, $isDefault, $uploadedBy, $bytes): FileTemplate {
            $template = $this->claimSlug($file, $inspected['size'], $format, $domain, $entity, $name, $isDefault, $uploadedBy);

            $this->store->write($template, $bytes);

            if ($isDefault) {
                $this->clearOtherDefaults($template, $uploadedBy);
            }

            return $template;
        });
    }

    /**
     * Replace the bytes behind an existing template, keeping its identity.
     *
     * The slug, name, format and default flag ARE the template: a replacement is
     * the same template with a new file, not a second template. The extension
     * has to match that identity — a Word template cannot become a Markdown one
     * without changing its key — and the new file is inspected before the old
     * bytes are overwritten, so a refused file leaves the stored template
     * untouched.
     *
     * @throws InvalidFileTemplate
     */
    public function replace(FileTemplate $template, UploadedFile $file, ?int $updatedBy = null): FileTemplate
    {
        $extension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));

        if ($extension !== $template->format()) {
            throw InvalidFileTemplate::because(__('board_meeting.template.format_mismatch', [
                'format' => $template->format(),
            ]));
        }

        $inspected = $this->inspector->inspect($file, $template->format());
        $bytes = $this->bytesOf($file);

        $replacement = FileTemplate::make(
            format: $template->format(),
            domain: $template->domain(),
            slug: $template->slug(),
            entity: $template->entity(),
            name: $template->name(),
            size: $inspected['size'],
            mime: (string) $file->getClientMimeType(),
            isDefault: $template->isDefault(),
        );

        // Bytes before the row: a failed byte write changes nothing, and a failed
        // row write leaves only stale size/mime metadata beside the new bytes —
        // smaller than the reverse order's risk of a half-written file under a
        // row that already claims it. The key is unchanged, so there is no claim
        // to make.
        DB::transaction(function () use ($replacement, $bytes, $updatedBy): void {
            $this->store->write($replacement, $bytes);
            $this->settings->put($replacement, $updatedBy);
        });

        return $replacement;
    }

    /**
     * The uploaded bytes, or a refusal the uploader can understand.
     *
     * @throws InvalidFileTemplate
     */
    private function bytesOf(UploadedFile $file): string
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw InvalidFileTemplate::because(__('board_meeting.template.bytes_unreadable'));
        }

        $bytes = file_get_contents($path);

        if ($bytes === false) {
            throw InvalidFileTemplate::because(__('board_meeting.template.bytes_unreadable'));
        }

        return $bytes;
    }

    /**
     * The first free slug for this upload, written as a row.
     *
     * The loop exists because the store is the only thing that can answer "who
     * claimed it first" — `uniqueSlug()` reads, and a read can be stale by the time
     * the insert lands.
     *
     * @throws InvalidFileTemplate
     */
    private function claimSlug(
        UploadedFile $file,
        int $size,
        string $format,
        string $domain,
        ?string $entity,
        string $name,
        bool $isDefault,
        ?int $uploadedBy,
    ): FileTemplate {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $template = FileTemplate::make(
                format: $format,
                domain: $domain,
                slug: $this->uniqueSlug($format, $domain, $entity, (string) $file->getClientOriginalName()),
                entity: $entity,
                name: trim($name),
                size: $size,
                mime: (string) $file->getClientMimeType(),
                isDefault: $isDefault,
            );

            if ($this->settings->claim($template, $uploadedBy)) {
                return $template;
            }
        }

        throw InvalidFileTemplate::because(__('board_meeting.template.slug_collision'));
    }

    /**
     * Make one template the default for its scope, clearing the rest.
     *
     * Done as a sweep rather than a flag because there can only be one answer to
     * "which template does the button use"; promoting a second while the first
     * still claims to be it leaves the export picking one of two arbitrarily.
     */
    public function promoteDefault(FileTemplate $template, ?int $updatedBy = null): FileTemplate
    {
        $this->clearOtherDefaults($template, $updatedBy);

        $promoted = $this->reissue($template, isDefault: true);

        $this->settings->put($promoted, $updatedBy);

        return $promoted;
    }

    public function rename(FileTemplate $template, string $name, ?int $updatedBy = null): FileTemplate
    {
        $renamed = $this->reissue($template, name: $name);

        $this->settings->put($renamed, $updatedBy);

        return $renamed;
    }

    /**
     * Remove a template and its file.
     *
     * Row first, then bytes. A file left behind by a failure is invisible and
     * harmless; a row left behind points at a template that cannot be exported
     * and offers itself in a picker.
     */
    public function delete(FileTemplate $template): void
    {
        $this->settings->forget(FileTemplate::class, $template->key());

        $this->store->delete($template);
    }

    /**
     * The default for a scope, or null when none has been chosen.
     */
    public function defaultFor(string $format, string $domain, ?string $entity = null): ?FileTemplate
    {
        foreach ($this->forFormatDomain($format, $domain, $entity) as $template) {
            if ($template->isDefault()) {
                return $template;
            }
        }

        return null;
    }

    /**
     * When each of these templates was last written, and by whom, keyed by the
     * template's key.
     *
     * The stamp is on the settings ROW rather than in the value, so it is not
     * part of {@see FileTemplate} and needs no reissue to carry it — one
     * batched read beside the list is the whole cost of showing it, and the
     * write paths pay nothing.
     *
     * @param  list<FileTemplate>  $templates
     * @return array<string, array{at: Carbon|null, by: int|null}>
     */
    public function lastChanges(array $templates): array
    {
        return $this->settings->lastChanges(
            FileTemplate::class,
            array_map(fn (FileTemplate $template): string => $template->key(), $templates),
        );
    }

    /**
     * The same template rebuilt, with only the parts a caller may change.
     *
     * Three of the four places that rewrite a row listed all seven arguments
     * between them, which is how a field gets left out of one of them - and a
     * template whose row has no format is a template whose file path points at
     * somewhere that does not exist.
     */
    private function reissue(
        FileTemplate $template,
        ?string $name = null,
        ?bool $isDefault = null,
    ): FileTemplate {
        return FileTemplate::make(
            format: $template->format(),
            domain: $template->domain(),
            slug: $template->slug(),
            entity: $template->entity(),
            name: trim($name ?? $template->name()),
            size: $template->size(),
            mime: $template->mime(),
            isDefault: $isDefault ?? $template->isDefault(),
        );
    }

    private function clearOtherDefaults(FileTemplate $except, ?int $updatedBy = null): void
    {
        foreach ($this->forFormatDomain($except->format(), $except->domain(), $except->entity()) as $template) {
            if ($template->isDefault() && $template->key() !== $except->key()) {
                $this->settings->put($this->reissue($template, isDefault: false), $updatedBy);
            }
        }
    }

    private function keyFor(string $format, string $domain, string $slug, ?string $entity = null): string
    {
        $head = FileTemplate::PREFIX.$format.'/'.$domain.'/'.$slug;

        return $entity === null ? $head : $head.'/'.$entity;
    }

    /**
     * A slug from the file name that no other template in this scope is using.
     *
     * Suffixed rather than rejected on a clash, because two people uploading
     * "official.docx" is ordinary and one of them being told to rename their file
     * is not help. The loop stops at a number nobody will reach.
     */
    private function uniqueSlug(string $format, string $domain, ?string $entity, string $originalName): string
    {
        $base = $this->slugFrom($originalName);

        if ($this->settings->exists(FileTemplate::class, $this->keyFor($format, $domain, $base, $entity)) === false) {
            return $base;
        }

        for ($suffix = 2; $suffix <= 50; $suffix++) {
            $candidate = $base.'-'.$suffix;

            if ($this->settings->exists(FileTemplate::class, $this->keyFor($format, $domain, $candidate, $entity)) === false) {
                return $candidate;
            }
        }

        return $base.'-'.Str::lower(Str::random(8));
    }

    private function slugFrom(string $originalName): string
    {
        $stem = pathinfo($originalName, PATHINFO_FILENAME);

        // STRIPPED, NOT TRANSLITERATED. `Str::ascii()` is the obvious helper and
        // it is the wrong one: it invents a reading of the name, turning a
        // Persian file into `namh-rsmy`, which is neither what the person wrote
        // nor anything they could recognise. Removing what cannot be used leaves
        // nothing for a fully Persian name, and the random handle below is the
        // honest answer — a handle nobody is shown beats a machine-made guess in
        // a key somebody may read in a log.
        $slug = Str::of($stem)
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->trim('-')
            ->limit(40, '')
            ->value();

        return $slug === '' ? 'tpl-'.Str::lower(Str::random(8)) : $slug;
    }
}
