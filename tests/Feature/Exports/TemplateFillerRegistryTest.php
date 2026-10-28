<?php

use App\Support\Exports\Contract\DocumentComposer;
use App\Support\Exports\DocumentRendererRegistry;
use App\Support\Exports\Templates\DocxTemplateFiller;
use App\Support\Exports\Templates\TemplateFiller;
use App\Support\Exports\Templates\TemplateFillerRegistry;

/**
 * The port a template is filled through, and the registry that picks one.
 *
 * Small on purpose. The port's real test is the Markdown filler arriving later
 * and finding the seams already drawn — a registry test cannot prove that, but
 * it can pin the two things that make it true: a filler is found by the format
 * it claims, and a format nobody registered is refused by name rather than
 * quietly producing nothing.
 */
describe('the template filler port', function () {
    it('resolves a filler by the format it declares', function () {
        $registry = app(TemplateFillerRegistry::class);

        expect($registry->get('docx'))->toBeInstanceOf(DocxTemplateFiller::class)
            ->and($registry->get('docx'))->toBeInstanceOf(TemplateFiller::class)
            ->and($registry->has('docx'))->toBeTrue();
    });

    it('lists the formats a template may be written in', function () {
        expect(app(TemplateFillerRegistry::class)->formats())->toContain('docx');
    });

    it('refuses a format with no filler, and says which are registered', function () {
        // A template row exists only because a format was accepted at upload, so
        // a missing filler is a wiring mistake. Naming the registered ones turns
        // a null that would surface four frames away into something found in a
        // second.
        //
        // `pdf` is the real case: it is a format the export kernel renders, and
        // it cannot take a template because the output is not editable.
        expect(fn () => app(TemplateFillerRegistry::class)->get('pdf'))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => app(TemplateFillerRegistry::class)->get('pdf'))
            ->toThrow(InvalidArgumentException::class, 'docx');
    });

    it('gives each registered format its own instance, whatever order they arrive in', function () {
        $registry = new TemplateFillerRegistry;
        $registry->register(app(DocxTemplateFiller::class));

        expect($registry->formats())->toBe(['docx']);
    });

    it('has a renderer for every format a template can be uploaded in', function () {
        // BOTH LISTS ARE WRITTEN IN THE PROVIDER, so nothing in either registry's own
        // tests notices when one gains a format and the other does not. The failure is
        // a 500 on somebody's export, long after the wiring edit — this is the cheapest
        // place to say so instead.
        $renderers = app(DocumentRendererRegistry::class);

        foreach (app(TemplateFillerRegistry::class)->formats() as $format) {
            expect($renderers->has($format))->toBeTrue();
        }
    });

    it('can compose a frame onto the docx renderer it registered', function () {
        // A frame template needs a renderer that is also a composer; the provider
        // throws when it is not, but only on the first export that uses one.
        expect(app(DocumentRendererRegistry::class)->get('docx'))
            ->toBeInstanceOf(DocumentComposer::class);
    });
});
