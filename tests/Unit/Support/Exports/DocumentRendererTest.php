<?php

namespace Tests\Unit\Support\Exports;

use App\Support\Exports\Contract\DocumentRenderer;
use App\Support\Exports\DocumentRendererRegistry;
use App\Support\Exports\Render\DocxRenderer;
use App\Support\Exports\Render\PdfRenderer;
use App\Support\Exports\Value\Document\DocumentField;
use App\Support\Exports\Value\Document\DocumentSection;
use App\Support\Exports\Value\Document\DocumentSpec;
use App\Support\Exports\Value\Document\DocumentTable;
use InvalidArgumentException;
use Tests\TestCase;

final class DocumentRendererTest extends TestCase
{
    /**
     * Persian sample covering every element the renderers must lay out:
     * title block, meta lines, a key/value section and a repeater table.
     */
    private function sampleSpec(): DocumentSpec
    {
        return new DocumentSpec(
            title: 'کارکن: آزمودنی',
            sections: [
                new DocumentSection(
                    heading: 'اطلاعات فردی',
                    fields: [
                        new DocumentField('نام', 'آزمودنی'),
                        new DocumentField('تاریخ استخدام', '۱۴۰۳/۰۵/۱۲'),
                    ],
                ),
                new DocumentSection(
                    heading: 'سوابق تحصیلی',
                    tables: [
                        new DocumentTable(
                            caption: 'مدرک‌ها',
                            headers: ['رشته', 'مقطع'],
                            rows: [['مدیریت', 'کارشناسی']],
                        ),
                    ],
                ),
            ],
            meta: [new DocumentField('تاریخ صدور', '۱۴۰۴/۰۷/۰۴')],
            subtitle: 'پروفایل کارمند',
        );
    }

    /**
     * Render to bytes through the same stream contract controllers use.
     *
     * @param  DocumentRenderer  $renderer
     */
    private function renderToBytes($renderer): string
    {
        $stream = fopen('php://temp', 'r+');
        $renderer->render($this->sampleSpec(), $stream);
        rewind($stream);

        return stream_get_contents($stream);
    }

    public function test_registry_resolves_pdf_and_docx_and_rejects_unknown(): void
    {
        $registry = new DocumentRendererRegistry;
        $registry->register('pdf', new PdfRenderer);
        $registry->register('docx', new DocxRenderer);

        $this->assertTrue($registry->has('pdf'));
        $this->assertInstanceOf(PdfRenderer::class, $registry->get('pdf'));
        $this->assertInstanceOf(DocxRenderer::class, $registry->get('docx'));
        $this->assertFalse($registry->has('xlsx'));

        $this->expectException(InvalidArgumentException::class);
        $registry->get('xlsx');
    }

    public function test_pdf_renderer_emits_pdf_with_embedded_vazirmatn(): void
    {
        $pdf = $this->renderToBytes(new PdfRenderer);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(10000, strlen($pdf), 'expected a non-trivial PDF payload');
        // mPDF embeds the registered font family under its lowercase key.
        $this->assertStringContainsStringIgnoringCase('vazirmatn', $pdf);
    }

    public function test_docx_renderer_emits_rtl_word_document(): void
    {
        $bytes = $this->renderToBytes(new DocxRenderer);

        $this->assertStringStartsWith('PK', $bytes, 'docx must be a zip container');

        $path = tempnam(sys_get_temp_dir(), 'docx-render-test-');
        file_put_contents($path, $bytes);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true);

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($path);

        $this->assertNotFalse($xml);
        $this->assertStringContainsString('آزمودنی', $xml, 'Persian content must survive serialization');
        $this->assertStringContainsString('<w:bidi', $xml, 'paragraphs must be right-to-left');
        $this->assertStringContainsString('<w:rtl', $xml, 'runs must be flagged rtl');
        $this->assertStringContainsString('<w:bidiVisual', $xml, 'tables must be visually reversed');
    }
}
