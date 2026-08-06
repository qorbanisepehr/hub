import { useCallback, useEffect } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type { QueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import {
    deleteBrandingImage,
    updateBranding,
    uploadBrandingImage,
    type BrandingImageKind,
} from "@/features/settings/api";
import { applyBrandingCss } from "@/features/settings/branding-css";
import type { BrandingSettings } from "@/features/settings/types";
import type { UpdateBrandingData } from "@/features/settings/types";
import { COMPANY_NAME, COMPANY_SUB_NAME } from "@/lib/brand";
import { getApiError } from "@/lib/error-utils";
import { settingsKeys } from "@/lib/query-keys";

const FALLBACK_BRANDING: BrandingSettings = {
    name: COMPANY_NAME,
    sub_name: COMPANY_SUB_NAME,
    logo_url: null,
    logotype_url: null,
    favicon_url: null,
    og_image_url: null,
    logo_svg: null,
    logotype_svg: null,
    primary_color: "#db7868",
    secondary_color: "#1c2538",
    version: 0,
};

function getInitialBranding(): BrandingSettings {
    const injected = window.__BRANDING__;

    return injected
        ? {
              name: injected.name ?? COMPANY_NAME,
              sub_name: injected.sub_name ?? COMPANY_SUB_NAME,
              logo_url: injected.logo_url,
              logotype_url: injected.logotype_url,
              favicon_url: injected.favicon_url,
              og_image_url: injected.og_image_url,
              logo_svg: injected.logo_svg ?? null,
              logotype_svg: injected.logotype_svg ?? null,
              primary_color: injected.primary_color ?? "#db7868",
              secondary_color: injected.secondary_color ?? "#1c2538",
              version: injected.version,
          }
        : FALLBACK_BRANDING;
}

function writeBranding(queryClient: QueryClient, branding: BrandingSettings) {
    applyBrandingCss(branding);
    queryClient.setQueryData(settingsKeys.branding(), branding);
}

/**
 * Current branding. Seeded from the server-injected window.__BRANDING__ payload
 * (set in the Blade layout) and treated as immutable for the session — admin
 * mutations write their response back into the cache so the same tab updates
 * instantly; other tabs converge on the next full page load.
 */
export function useBranding() {
    const query = useQuery({
        queryKey: settingsKeys.branding(),
        queryFn: () => getInitialBranding(),
        initialData: getInitialBranding,
        staleTime: Infinity,
        gcTime: Infinity,
    });

    useEffect(() => {
        applyBrandingCss(query.data);
    }, [query.data]);

    return query;
}

export function useUpdateBranding() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (data: UpdateBrandingData) =>
            updateBranding(data),
        onSuccess: ({ data }) => writeBranding(queryClient, data.data),
    });
}

export function useUploadBrandingImage(kind: BrandingImageKind) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (file: File) => uploadBrandingImage(kind, file),
        onSuccess: ({ data }) => writeBranding(queryClient, data.data),
    });
}

export function useDeleteBrandingImage(kind: BrandingImageKind) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: () => deleteBrandingImage(kind),
        onSuccess: ({ data }) => writeBranding(queryClient, data.data),
    });
}

/**
 * Upload/delete handlers + pending state for a single branding image kind,
 * wired to the shared toasts. Used directly by the generic ImageUpload so
 * every new branding image is a one-line config addition.
 */
export function useBrandingImage(kind: BrandingImageKind) {
    const uploadMutation = useUploadBrandingImage(kind);
    const deleteMutation = useDeleteBrandingImage(kind);

    const onUpload = useCallback(
        (file: File) => {
            uploadMutation.mutate(file, {
                onSuccess: () => toast.success("تصویر با موفقیت ذخیره شد"),
                onError: (err) => toast.error(getApiError(err)),
            });
        },
        [uploadMutation],
    );

    const onDelete = useCallback(() => {
        deleteMutation.mutate(undefined, {
            onSuccess: () =>
                toast.success("تصویر حذف شد و نمونه پیش‌فرض برگشت"),
            onError: (err) => toast.error(getApiError(err)),
        });
    }, [deleteMutation]);

    return {
        onUpload,
        onDelete,
        isPending: uploadMutation.isPending || deleteMutation.isPending,
    };
}
