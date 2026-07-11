import * as pdfjsLib from "pdfjs-dist";

const workerUrl = new URL(
    "pdfjs-dist/build/pdf.worker.min.mjs",
    import.meta.url,
).toString();

let initialized = false;

function ensurePdfjs() {
    if (initialized) {
        return;
    }

    pdfjsLib.GlobalWorkerOptions.workerSrc = workerUrl;
    initialized = true;
}

export async function renderPdfThumbnailUrl({
    pageIndex,
    url,
    width,
}: {
    pageIndex: number;
    url: string;
    width: number;
}): Promise<string | null> {
    try {
        ensurePdfjs();

        const doc = await pdfjsLib.getDocument({
            url,
            verbosity: pdfjsLib.VerbosityLevel.ERRORS,
        }).promise;
        const page = await doc.getPage(pageIndex + 1);

        const viewport = page.getViewport({ scale: 1 });
        const scale = width / viewport.width;
        const scaledViewport = page.getViewport({ scale });

        const canvas = document.createElement("canvas");
        canvas.width = scaledViewport.width;
        canvas.height = scaledViewport.height;

        const ctx = canvas.getContext("2d");
        if (!ctx) {
            doc.destroy();

            return null;
        }

        await page.render({
            canvasContext: ctx,
            viewport: scaledViewport,
        }).promise;

        const dataUrl = canvas.toDataURL("image/png");
        doc.destroy();

        return dataUrl;
    } catch (error) {
        console.error("PDF thumbnail render failed:", error);

        return null;
    }
}
