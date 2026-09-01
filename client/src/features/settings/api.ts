import { api, FORM_DATA_HEADERS } from "@/lib/api";
import type { BrandingSettings, UpdateBrandingData } from "./types";

export type BrandingImageKind = "logo" | "logotype" | "favicon" | "og_image";

export function updateBranding(data: UpdateBrandingData) {
    return api.put<{ data: BrandingSettings }>("/settings/branding", data);
}

export function uploadBrandingImage(kind: BrandingImageKind, file: File) {
    const formData = new FormData();
    formData.append("file", file);

    return api.post<{ data: BrandingSettings }>(`/settings/branding/${kind}`, formData, {
        headers: FORM_DATA_HEADERS,
    });
}

export function deleteBrandingImage(kind: BrandingImageKind) {
    return api.delete<{ data: BrandingSettings }>(`/settings/branding/${kind}`);
}
