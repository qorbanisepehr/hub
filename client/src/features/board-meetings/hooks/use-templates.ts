import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { exportTemplateKeys } from "@/lib/query-keys";
import { getApiError } from "@/lib/error-utils";
import {
    deleteTemplate,
    fetchTemplateFields,
    fetchTemplates,
    previewTemplate,
    replaceTemplateFile,
    updateTemplate,
    uploadTemplate,
} from "../templates-api";

/**
 * The uploaded templates of ONE format.
 *
 * The server scopes the library by format, because a Word picker must not offer a
 * Markdown frame. That makes the format part of the question, not a filter applied
 * afterwards: a client that asks once and filters locally sees only Word templates,
 * which is exactly how the Markdown and JSON libraries stayed invisible.
 */
export function useTemplates(
    format = "docx",
    domain = "boardmeeting",
    enabled = true,
) {
    return useQuery({
        queryKey: exportTemplateKeys.list(domain, format),
        // The envelope's `data` is unwrapped here so no caller has to know the
        // API's response shape; only the domain comes back alongside it, and the
        // library already knows which domain it asked for.
        queryFn: async () => (await fetchTemplates(domain, format)).data.data
            .templates,
        // Nothing here is secret enough to be worth a spinner on every visit,
        // but a library is small and rarely changes.
        staleTime: 30_000,
        // PDF is the one format that takes no template, so the dialog asks for
        // nothing there instead of fetching a Word library it would never show.
        enabled,
    });
}

/**
 * The cheat sheet: every name a template may refer to, grouped by row.
 *
 * Fetched for its own sake rather than beside the library, because it answers a
 * different question and is read by a different screen - the person authoring a
 * file needs the names, the person exporting needs the list.
 */
export function useTemplateFields(domain = "boardmeeting") {
    return useQuery({
        queryKey: exportTemplateKeys.fields(domain),
        queryFn: async () => (await fetchTemplateFields(domain)).data.data,
        staleTime: 5 * 60_000,
    });
}

/**
 * The template filled with the newest meeting, as a downloadable file.
 *
 * A mutation rather than a query because the answer is a file, not state to
 * cache: the preview changes when the template does, and a stale cached
 * document would be worse than a fresh request.
 */
export function usePreviewTemplate(domain = "boardmeeting") {
    return useMutation({
        mutationFn: ({ slug, format }: { slug: string; format: string }) =>
            previewTemplate(slug, domain, format),
        onError: (error) => toast.error(getApiError(error)),
    });
}

export function useUploadTemplate(domain = "boardmeeting") {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: uploadTemplate,
        // The upload response carries WHAT THE FILE TURNED OUT TO BE, and this is
        // the only moment anybody can do anything about it. The export is where the
        // answer becomes visible otherwise — a template with `{{content}}` receives
        // the meeting and one without it does not — and by then it is a document
        // somebody has to open to find out they got the other kind.
        //
        // So it is said out loud rather than filed away. A person who uploaded a
        // letterhead expecting their minutes under it finds out here, not in Word.
        onSuccess: (response) => {
            void queryClient.invalidateQueries({
                queryKey: exportTemplateKeys.lists(domain),
            });

            const inspection = response.data.inspection;

            toast.success(
                inspection?.kind === "frame"
                    ? "قالب بارگذاری شد — سند تولیدی در جای نشان‌داده‌شده نوشته می‌شود."
                    : "قالب بارگذاری شد — فقط نام‌های داخل آن پر می‌شوند و سندی ساخته نمی‌شود.",
            );
        },
        // The server's own reason, verbatim: it knows which part of the archive
        // it refused and why, and replacing that with "upload failed" would throw
        // away the only sentence that tells the person what to change.
        onError: (error) => toast.error(getApiError(error)),
    });
}

export function useUpdateTemplate(
    format = "docx",
    domain = "boardmeeting",
) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({
            slug,
            ...payload
        }: { slug: string; name?: string; is_default?: boolean }) =>
            updateTemplate(slug, payload, format, domain),
        onSuccess: () => {
            void queryClient.invalidateQueries({
                queryKey: exportTemplateKeys.lists(domain),
            });

            toast.success("قالب به‌روزرسانی شد.");
        },
        onError: (error) => toast.error(getApiError(error)),
    });
}

export function useDeleteTemplate(
    format = "docx",
    domain = "boardmeeting",
) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (slug: string) => deleteTemplate(slug, format, domain),
        onSuccess: () => {
            void queryClient.invalidateQueries({
                queryKey: exportTemplateKeys.lists(domain),
            });

            toast.success("قالب حذف شد.");
        },
        onError: (error) => toast.error(getApiError(error)),
    });
}

/**
 * Replace a template's file without changing its identity.
 *
 * A separate mutation from {@see useUpdateTemplate} because the request is a
 * different content type and the interesting failure is different: the server
 * refuses a file whose extension does not match the template's format.
 */
export function useReplaceTemplate(
    format = "docx",
    domain = "boardmeeting",
) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ slug, file }: { slug: string; file: File }) =>
            replaceTemplateFile(slug, file, format, domain),
        onSuccess: (response) => {
            void queryClient.invalidateQueries({
                queryKey: exportTemplateKeys.lists(domain),
            });

            // What the NEW file turned out to be, said out loud for the same
            // reason the upload says it: a letterhead replaced by a file
            // without the {{content}} marker changes what an export produces,
            // and this toast is the only moment anybody hears about it.
            const inspection = response.data.inspection;

            toast.success(
                inspection?.kind === "frame"
                    ? "فایل قالب جایگزین شد — سند تولیدی در جای نشان‌داده‌شده نوشته می‌شود."
                    : "فایل قالب جایگزین شد — فقط نام‌های داخل آن پر می‌شوند و سندی ساخته نمی‌شود.",
            );
        },
        onError: (error) => toast.error(getApiError(error)),
    });
}
