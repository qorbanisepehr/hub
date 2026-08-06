import type { BrandingSettings } from "./types";

/**
 * Push the configured brand colors into the app-wide CSS variables. The Blade
 * layout seeds these on the :root at page load; this mirrors that so saving
 * colors updates `text-brand` / brand-derived styles without a full reload.
 */
export function applyBrandingCss(
    branding: Pick<
        BrandingSettings,
        "primary_color" | "secondary_color"
    >,
): void {
    const root = document.documentElement;

    root.style.setProperty("--brand", branding.primary_color);
    root.style.setProperty("--brand-secondary", branding.secondary_color);
}
