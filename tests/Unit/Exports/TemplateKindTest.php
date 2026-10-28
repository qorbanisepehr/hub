<?php

use App\Support\Exports\Templates\TemplateKind;

describe('template kind', function () {
    it('reads a file asking for the document as a frame', function () {
        // The marker IS the request. Nothing else in the file changes what
        // happens next.
        expect(TemplateKind::fromNames(['title', 'content']))->toBe(TemplateKind::Frame)
            ->and(TemplateKind::fromNames(['content']))->toBe(TemplateKind::Frame);
    });

    it('reads a file with values but no document marker as a document', function () {
        // The distinction, and the bug this replaced: a document somebody wrote
        // by hand was getting the generated meeting appended underneath it.
        expect(TemplateKind::fromNames(['title', 'tag.name']))->toBe(TemplateKind::Detail)
            ->and(TemplateKind::fromNames(['title']))->toBe(TemplateKind::Detail);
    });

    it('reads a file mentioning nothing as a frame', function () {
        // A letterhead with no placeholders in its body plainly wants something
        // under it, and refusing would produce a blank page. The empty list is a
        // frame, NOT a document — a document cannot be told from a letterhead by
        // having nothing in it, because a hand-written page with no macros is
        // indistinguishable from a frame with none.
        expect(TemplateKind::fromNames([]))->toBe(TemplateKind::Frame);
    });

    it('is decided by the marker alone, not by how many other values are there', function () {
        // The failure that made this worth writing down: the old classifier asked
        // "does this file hold ANY placeholder", and the inspector counted headers
        // and footers too. A letterhead with {{date}} in its footer therefore
        // became a document and the meeting vanished off it with no error. Asking
        // about `content` specifically cannot reproduce that, because a date in a
        // footer is not a request for the document.
        expect(TemplateKind::fromNames(['date']))->toBe(TemplateKind::Detail)
            ->and(TemplateKind::fromNames(['date', 'content']))->toBe(TemplateKind::Frame);
    });

    it('matches the marker on the trimmed name a person actually types', function () {
        // The fillers trim before asking, so `{{ content }}` and `{{content}}` reach
        // here as the same string. Asserting it here documents that the rule does
        // not depend on the spacing having been written the usual way.
        expect(TemplateKind::fromNames(array_map('trim', [' content '])))->toBe(TemplateKind::Frame);
    });
});
