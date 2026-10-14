<?php

namespace App\Support\Exports;

use App\Support\Exports\Contract\DocumentRenderer;
use InvalidArgumentException;

/**
 * What somebody asked a bundle to contain: which renderings of the record, and
 * whether the record's own files travel with it.
 *
 * Kernel, not a domain's form request, because "give me this as PDF and Word and
 * the scans" is the same question in every domain and should be asked once. A
 * domain's FormRequest validates the HTTP shape and then hands one of these
 * along, so the rule that a format must actually exist is enforced in one place
 * rather than re-stated per endpoint.
 *
 * Two parts, deliberately, and they are gated by different permissions:
 * `formats` is the record rendered, which is what the export permission is for,
 * while the attachments are documents and are behind the documents permission.
 * Deciding that here — rather than letting each endpoint bundle them into one
 * check — is what stops a bundle endpoint from quietly widening one permission to
 * cover the other.
 */
final class BundleRequest
{
    /** The archive is a record rendered in several formats, and nothing else. */
    private const ATTACHMENTS_OFF = false;

    private const ATTACHMENTS_ON = true;

    /**
     * @param  list<string>  $formats  registered renderer names, in the order the
     *                                 caller asked for them
     */
    private function __construct(
        public readonly array $formats,
        public readonly bool $attachments,
    ) {}

    /**
     * @param  list<string>  $formats
     *
     * @throws InvalidArgumentException when nothing was asked for, or a format
     *                                  no renderer is registered for
     */
    public static function sheets(DocumentRendererRegistry $renderers, array $formats): self
    {
        return new self(
            formats: self::validated($renderers, $formats),
            attachments: self::ATTACHMENTS_OFF,
        );
    }

    /**
     * @param  list<string>  $formats
     */
    public static function sheetsWithAttachments(DocumentRendererRegistry $renderers, array $formats): self
    {
        return new self(
            formats: self::validated($renderers, $formats),
            attachments: self::ATTACHMENTS_ON,
        );
    }

    public function wantsAttachments(): bool
    {
        return $this->attachments;
    }

    /**
     * The renderer for one of the requested formats.
     *
     * Resolved through the same registry the single-record endpoints use, so a
     * bundle and a lone PDF of the same format cannot disagree about what that
     * format means.
     *
     * @throws InvalidArgumentException when the format was not requested
     */
    public function renderer(DocumentRendererRegistry $renderers, string $format): DocumentRenderer
    {
        if (! in_array($format, $this->formats, true)) {
            throw new InvalidArgumentException("Format [{$format}] was not requested in this bundle.");
        }

        return $renderers->get($format);
    }

    /**
     * @param  list<string>  $formats
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    private static function validated(DocumentRendererRegistry $renderers, array $formats): array
    {
        $formats = array_values(array_unique(array_filter(
            array_map('trim', $formats),
            static fn (string $format): bool => $format !== '',
        )));

        // Refused here rather than at the renderer, because the failure the
        // caller can act on is "that format is not one we offer" — and an
        // unknown format reaching the registry would surface as an exception
        // from the middle of building an archive.
        if ($formats === []) {
            throw new InvalidArgumentException('A bundle must contain at least one format.');
        }

        foreach ($formats as $format) {
            if (! $renderers->has($format)) {
                throw new InvalidArgumentException("Unsupported document format [{$format}].");
            }
        }

        return $formats;
    }
}
