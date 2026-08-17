import { useMemo } from "react";
import { cn } from "@/lib/utils";
import { useBranding } from "@/features/settings/hooks/use-branding";
import type { SVGAttributes } from "react";

export const LOGO_VIEWBOX = "0 0 750 750";

export const LOGO_PATH =
    "M0 50C0 22.3858 22.3858 0 50 0H700C727.614 0 750 22.3858 750 50V700C750 727.614 727.614 750 700 750H50C22.3858 750 0 727.614 0 700V50Z";

export const LOGO_TYPE_VIEWBOX = "0 0 950 250";

export const LOGO_TYPE_PATH =
    "M0 50C0 22.3858 22.3858 0 50 0H900C927.614 0 950 22.3858 950 50V200C950 227.614 927.614 250 900 250H50C22.3857 250 0 227.614 0 200V50Z";

const SVG_COLOR_RE =
    /^(?:#[0-9a-fA-F]{3,8}|rgba?\([^)]*\)|hsla?\([^)]*\)|[a-z]+)$/;

const NON_COLOR_KEYWORDS = new Set([
    "none",
    "transparent",
    "currentcolor",
    "inherit",
    "initial",
    "unset",
    "revert",
    "revert-layer",
    "context-fill",
    "context-stroke",
]);

function isColorValue(value: string): boolean {
    const v = value.trim().toLowerCase();

    if (v.startsWith("url(")) {
        return false;
    }

    return SVG_COLOR_RE.test(v) && !NON_COLOR_KEYWORDS.has(v);
}

/**
 * Rewrite fixed fill/stroke/stop colors to `currentColor` so an uploaded SVG
 * becomes a monochrome brand mark that follows the configured brand color.
 * `none`, `currentColor`, `transparent` and gradient references are preserved.
 */
export function recolorSvgContent(svg: string): string {
    return svg
        .replace(
            /(fill|stroke|stop-color|color)=(["'])([^"']*)\2/gi,
            (match, attr: string, quote: string, value: string) =>
                isColorValue(value)
                    ? `${attr}=${quote}currentColor${quote}`
                    : match,
        )
        .replace(
            /((?:fill|stroke|stop-color|color):\s*)([^;}"']+)/gi,
            (match, prefix: string, value: string) =>
                isColorValue(value) ? `${prefix}currentColor` : match,
        );
}

export type BrandImage = { kind: "svg"; content: string } | { kind: "img" };

/**
 * Resolve an uploaded brand image synchronously. SVG uploads arrive inline in
 * the injected `__BRANDING__` payload (`logo_svg` / `logotype_svg`) and are
 * rewritten to `currentColor` so they follow the live brand color — no client
 * fetch, so there is no default-logo flash on load. Raster uploads are
 * returned as-is to be rendered with an `<img>` tag.
 */
export function useBrandImage(
    url: string | null | undefined,
    injectedSvg?: string | null,
): BrandImage | null {
    return useMemo(() => {
        if (!url) {
            return null;
        }

        if (injectedSvg) {
            return { kind: "svg", content: recolorSvgContent(injectedSvg) };
        }

        return { kind: "img" };
    }, [url, injectedSvg]);
}

export function Logo({ className, ...props }: SVGAttributes<SVGSVGElement>) {
    const { data } = useBranding();
    const url = data?.logo_url ?? null;
    const image = useBrandImage(url, data?.logo_svg);

    if (image?.kind === "img") {
        return (
            <img
                src={url!}
                alt=""
                className={cn("object-contain w-full h-full", className)}
            />
        );
    }

    if (image?.kind === "svg") {
        return (
            <span
                role="img"
                aria-label="logo"
                className={cn(
                    "brand-svg text-brand w-full h-full [&>svg]:w-full [&>svg]:h-full",
                    className,
                )}
                dangerouslySetInnerHTML={{ __html: image.content }}
            />
        );
    }

    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox={LOGO_VIEWBOX}
            {...props}
            className={cn("text-brand w-full h-full", className)}
        >
            <path d={LOGO_PATH} fill="currentColor" />
        </svg>
    );
}

export function LogoType({
    className,
    ...props
}: SVGAttributes<SVGSVGElement>) {
    const { data } = useBranding();
    const url = data?.logotype_url ?? null;
    const image = useBrandImage(url, data?.logotype_svg);

    if (image?.kind === "img") {
        return (
            <img
                src={url!}
                alt=""
                className={cn("object-contain w-full h-full", className)}
            />
        );
    }

    if (image?.kind === "svg") {
        return (
            <span
                role="img"
                aria-label="logotype"
                className={cn(
                    "brand-svg text-primary w-full h-full [&>svg]:w-full [&>svg]:h-full",
                    className,
                )}
                dangerouslySetInnerHTML={{ __html: image.content }}
            />
        );
    }

    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox={LOGO_TYPE_VIEWBOX}
            {...props}
            className={cn("text-primary w-full h-full", className)}
        >
            <path d={LOGO_TYPE_PATH} fill="currentColor" />
        </svg>
    );
}
