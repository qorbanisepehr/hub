/**
 * Per-field image editor configuration. Enable it on a FileUpload via the
 * `editor` prop; `editor={true}` uses all defaults, `false`/absent disables it.
 *
 * The config is made of independent capability flags (which tools are
 * available) plus constraints (limits for the output), so a field can turn on
 * any combination, e.g. "only crop" ({ crop, rotate: false }), "crop and
 * resize" ({ crop, resize }), or "rotate only" ({ crop: false }).
 */
export type ImageEditorAspectPreset = {
    label: string;
    value: number;
};

export type ImageEditorConfig = {
    /** Show the crop box (react-advanced-cropper). When false, the editor is a
     *  rotate/flip/resize-only view of the full image. Default true. */
    crop?: boolean;
    /** Allow 90° rotation. Default true. */
    rotate?: boolean;
    /** Allow zooming while cropping. Default true. */
    zoom?: boolean;
    /** Allow horizontal/vertical flip. Default false. */
    flip?: boolean;
    /** Scale the output down to maxWidth/maxHeight. Default false. */
    resize?: boolean;
    /** Crop ratio. "original" = free, unresized crop box; a number forces that
     *  ratio. Default "original". */
    aspect?: number | "original";
    /** Quick ratio buttons shown while cropping. Default 1:1, 4:3, 3:2, 16:9. */
    aspectPresets?: ImageEditorAspectPreset[];
    /** Output cap width in px, used when resize is enabled. Default 2000. */
    maxWidth?: number;
    /** Output cap height in px, used when resize is enabled. Default 2000. */
    maxHeight?: number;
    /** JPEG/WebP encoding quality 0..1. Default 0.92. */
    quality?: number;
    /** Encode the output as this format; otherwise the source type is kept. */
    format?: "jpeg" | "png" | "webp";
};

export type ImageEditorTools = {
    crop: boolean;
    rotate: boolean;
    zoom: boolean;
    flip: boolean;
    resize: boolean;
};

export type NormalizedImageEditorConfig = {
    tools: ImageEditorTools;
    aspect: number | "original";
    aspectPresets: ImageEditorAspectPreset[];
    maxWidth: number;
    maxHeight: number;
    quality: number;
    format?: ImageEditorConfig["format"];
};

export const DEFAULT_IMAGE_EDITOR_CONFIG: NormalizedImageEditorConfig = {
    tools: { crop: true, rotate: true, zoom: true, flip: false, resize: false },
    aspect: "original",
    aspectPresets: [
        { label: "۱:۱", value: 1 },
        { label: "۴:۳", value: 4 / 3 },
        { label: "۳:۲", value: 3 / 2 },
        { label: "۱۶:۹", value: 16 / 9 },
    ],
    maxWidth: 2000,
    maxHeight: 2000,
    quality: 0.92,
    format: undefined,
};

export function normalizeImageEditorConfig(
    editor: boolean | ImageEditorConfig | undefined,
): NormalizedImageEditorConfig | null {
    if (editor === undefined || editor === false) {
        return null;
    }

    const c = editor === true ? {} : editor;

    return {
        tools: {
            crop: c.crop ?? DEFAULT_IMAGE_EDITOR_CONFIG.tools.crop,
            rotate: c.rotate ?? DEFAULT_IMAGE_EDITOR_CONFIG.tools.rotate,
            zoom: c.zoom ?? DEFAULT_IMAGE_EDITOR_CONFIG.tools.zoom,
            flip: c.flip ?? DEFAULT_IMAGE_EDITOR_CONFIG.tools.flip,
            resize: c.resize ?? DEFAULT_IMAGE_EDITOR_CONFIG.tools.resize,
        },
        aspect: c.aspect ?? DEFAULT_IMAGE_EDITOR_CONFIG.aspect,
        aspectPresets:
            c.aspectPresets ?? DEFAULT_IMAGE_EDITOR_CONFIG.aspectPresets,
        maxWidth: c.maxWidth ?? DEFAULT_IMAGE_EDITOR_CONFIG.maxWidth,
        maxHeight: c.maxHeight ?? DEFAULT_IMAGE_EDITOR_CONFIG.maxHeight,
        quality: c.quality ?? DEFAULT_IMAGE_EDITOR_CONFIG.quality,
        format: c.format,
    };
}

export function isEditableImage(file: File): boolean {
    return file.type.startsWith("image/");
}

const IMAGE_MIME_TYPES = ["image/png", "image/jpeg", "image/webp"];

export function resolveOutputType(
    sourceType: string,
    format?: ImageEditorConfig["format"],
): string {
    if (format) {
        return `image/${format}`;
    }

    return IMAGE_MIME_TYPES.includes(sourceType) ? sourceType : "image/jpeg";
}

export function scaleCanvasToFit(
    canvas: HTMLCanvasElement,
    maxWidth: number,
    maxHeight: number,
): HTMLCanvasElement {
    const scale = Math.min(maxWidth / canvas.width, maxHeight / canvas.height);

    if (scale >= 1) {
        return canvas;
    }

    const scaled = document.createElement("canvas");
    scaled.width = Math.max(1, Math.round(canvas.width * scale));
    scaled.height = Math.max(1, Math.round(canvas.height * scale));
    const ctx = scaled.getContext("2d");
    if (!ctx) {
        return canvas;
    }
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = "high";
    ctx.drawImage(canvas, 0, 0, scaled.width, scaled.height);

    return scaled;
}

export function canvasToFile(
    canvas: HTMLCanvasElement,
    fileName: string,
    type: string,
    quality: number,
): Promise<File> {
    const output = canvas;
    const ctx = output.getContext("2d");

    if (type === "image/jpeg" && ctx) {
        ctx.globalCompositeOperation = "destination-over";
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, output.width, output.height);
        ctx.globalCompositeOperation = "source-over";
    }

    return new Promise((resolve, reject) => {
        output.toBlob(
            (blob) =>
                blob
                    ? resolve(
                          new File([blob], fileName, { type }),
                      )
                    : reject(new Error("Cropped image is empty")),
            type,
            quality,
        );
    });
}
