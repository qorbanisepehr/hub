import { useCallback } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";

import { api } from "@/lib/api";
import { publicApi } from "@/lib/public-api";
import { getApiError } from "@/lib/error-utils";
import { documentKeys } from "@/lib/query-keys";
import { isAuthedDocumentEntity } from "@/hooks/use-entity-documents";
import type { EntityDocument } from "@/hooks/use-entity-documents";
import type { DocumentRequirement } from "@/features/documents/types";
import { useDocumentValidation } from "@/hooks/use-document-validation";

type UseDocumentUploadOptions = {
    entity: string;
    uuid: string;
    categoryId: number | undefined;
    requirement: DocumentRequirement | null;
    fieldKey?: string;
    notes?: string;
    onUploadComplete?: (doc: EntityDocument) => void;
};

export function useDocumentUpload({
    entity,
    uuid,
    categoryId,
    requirement,
    fieldKey,
    notes,
    onUploadComplete,
}: UseDocumentUploadOptions) {
    const queryClient = useQueryClient();
    const authed = isAuthedDocumentEntity(entity);
    const docClient = authed ? api : publicApi;
    const { validateFile } = useDocumentValidation(requirement);

    const uploadMutation = useMutation({
        mutationFn: (file: File) => {
            const formData = new FormData();
            formData.append("document_category_id", String(categoryId));
            formData.append("file", file);
            if (requirement?.section_key) {
                formData.append("section_key", requirement.section_key);
            }
            if (fieldKey && requirement?.section_key) {
                formData.append("field_key", fieldKey);
            }
            if (notes) {
                formData.append("notes", notes);
            }
            return docClient
                .post<{ data: EntityDocument }>(
                    `/${entity}/${uuid}/documents`,
                    formData,
                    {
                        headers: { "Content-Type": "multipart/form-data" },
                        ...(authed
                            ? {}
                            : {
                                  grant: {
                                      entity,
                                      uuid,
                                      purpose: "edit",
                                  },
                              }),
                    },
                )
                .then((r) => r.data.data);
        },
        onSuccess: (doc) => {
            queryClient.invalidateQueries({
                queryKey: documentKeys.entityDocuments(entity, uuid),
            });
            onUploadComplete?.(doc);
            toast.success("فایل با موفقیت آپلود شد");
        },
        onError: (e) => {
            toast.error(getApiError(e) ?? "خطا در بارگذاری فایل");
        },
    });

    const handleFiles = useCallback(
        async (fileList: File[]) => {
            for (const file of fileList) {
                const errors = await validateFile(file);
                if (errors.length > 0) {
                    toast.error(errors[0]);
                    continue;
                }
                uploadMutation.mutate(file);
            }
        },
        [uploadMutation, validateFile],
    );

    return {
        uploadMutation,
        handleFiles,
        isUploading: uploadMutation.isPending,
    };
}
