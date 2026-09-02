"use client";

import { useQuery } from "@tanstack/react-query";
import * as React from "react";

import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import { fetchDocumentCategories } from "@/features/documents/api";
import { useDocumentRequirements } from "@/features/documents/hooks/use-document-requirements";
import type { DocumentCategory } from "@/features/documents/types";
import { resolvePlacementRequirement } from "@/features/documents/types";
import { useDocumentDelete } from "@/hooks/use-document-delete";
import { useDocumentUpload } from "@/hooks/use-document-upload";
import type { EntityDocument } from "@/hooks/use-entity-documents";
import { useEntityDocuments } from "@/hooks/use-entity-documents";
import { documentKeys } from "@/lib/query-keys";
import { cn } from "@/lib/utils";
import { AvatarVariant } from "./file-upload-variants/avatar";
import { ThumbnailVariant } from "./file-upload-variants/thumbnail";
import { CardVariant } from "./file-upload-variants/card";
import { DefaultVariant } from "./file-upload-variants/default";

export type FileUploadFieldVariant =
    | "default"
    | "avatar"
    | "thumbnail"
    | "card";

export type FileUploadActionsPlacement = "overlay" | "row" | "column";

type FileUploadFieldProps = {
    uuid: string;
    categorySlug: string;
    label: string;
    /** Grant entity the upload targets. Defaults to "questionnaire". */
    entity?: string;
    variant?: FileUploadFieldVariant;
    accept?: string;
    multiple?: boolean;
    maxFiles?: number;
    notes?: string;
    /** Field placement within the requirement's section, e.g. "front" or "edu-0". */
    fieldKey?: string;
    /**
     * Explicit placement section, e.g. "dependents". When set (or when a
     * field key is given) dynamic requirement groups are matched first and
     * the section key is sent with uploads.
     */
    sectionKey?: string;
    categoryType?: string;
    aspectRatio?: number;
    description?: string;
    className?: string;
    /** Whether the upload field is required. Shows a red asterisk indicator. */
    required?: boolean;
    /** Whether to show the delete action button. Defaults to true. */
    actionsEnabled?: boolean;
    /** Where the delete action button is rendered. Defaults to "overlay". */
    actionsPlacement?: FileUploadActionsPlacement;
    /** When enabled, current documents render a "replace" action. */
    replaceEnabled?: boolean;
    onReplace?: (doc: EntityDocument) => void;
    onUploadComplete?: (doc: EntityDocument) => void;
};

const DEFAULT_ACCEPT = [
    ".pdf",
    ".jpg",
    ".jpeg",
    ".png",
    ".webp",
    "application/pdf",
    "image/jpeg",
    "image/png",
    "image/webp",
].join(",");

const VARIANT_CONTAINER: Record<
    Exclude<FileUploadFieldVariant, "default">,
    string
> = {
    avatar: "w-28 rounded-xl overflow-hidden",
    thumbnail: "size-24 overflow-hidden rounded-lg",
    card: "",
};

/**
 * Shared state computed by the orchestrator and passed to variant sub-components.
 */
export type FileUploadVariantProps = {
    uuid: string;
    entity: string;
    categoryDocs: EntityDocument[];
    effectiveAccept: string;
    effectiveMaxFiles: number;
    canUpload: boolean;
    currentDoc: EntityDocument | null;
    isUploading: boolean;
    isDeleting: boolean;
    isPending: boolean;
    isImage: boolean;
    handleFiles: (fileList: File[]) => void;
    renderDelete: (doc: EntityDocument, overLay?: boolean) => React.ReactNode;
    label: string;
    multiple: boolean;
    required: boolean;
    aspectRatio?: number;
    description?: string;
    className?: string;
    actionsEnabled: boolean;
    actionsPlacement: FileUploadActionsPlacement;
    replaceEnabled: boolean;
    onReplace?: (doc: EntityDocument) => void;
};

export function FileUploadField({
    uuid,
    categorySlug,
    label,
    entity = "questionnaire",
    variant = "default",
    accept = DEFAULT_ACCEPT,
    multiple = false,
    maxFiles = 1,
    notes,
    fieldKey,
    sectionKey,
    categoryType = "personnel",
    aspectRatio,
    description,
    className,
    required = false,
    actionsEnabled = true,
    actionsPlacement = "column",
    replaceEnabled = false,
    onReplace,
    onUploadComplete,
}: FileUploadFieldProps) {
    const { getDocumentsBySlug } = useEntityDocuments(entity, uuid);
    const categoryDocs = getDocumentsBySlug(categorySlug, fieldKey).filter(
        (d) => (notes !== undefined ? (d.notes ?? "") === notes : true),
    );

    const { data: categories } = useQuery({
        queryKey: documentKeys.categories(categoryType),
        queryFn: async () => {
            const { data } = await fetchDocumentCategories(categoryType);
            return data.data;
        },
    });

    const { data: requirementEnvelope } = useDocumentRequirements(entity);

    const categoryId = React.useMemo(() => {
        function find(cats: DocumentCategory[]): number | undefined {
            for (const cat of cats) {
                if (cat.slug === categorySlug) {
                    return cat.id;
                }
                if (cat.children) {
                    const found = find(cat.children);
                    if (found !== undefined) return found;
                }
            }
            return undefined;
        }
        return categories ? find(categories) : undefined;
    }, [categories, categorySlug]);

    const requirement = resolvePlacementRequirement(
        requirementEnvelope,
        categorySlug,
        { sectionKey, fieldKey },
    );

    const effectiveMaxFiles = requirement?.max_files ?? maxFiles;
    const effectiveAccept = requirement?.mime_types?.join(",") ?? accept;

    const { handleFiles, isUploading } = useDocumentUpload({
        entity,
        uuid,
        categoryId,
        requirement,
        sectionKey,
        fieldKey,
        notes,
        onUploadComplete,
    });

    const { deleteDocument, isDeleting } = useDocumentDelete({
        entity,
        uuid,
    });

    const canUpload = multiple
        ? categoryDocs.length < effectiveMaxFiles
        : categoryDocs.length === 0;
    const currentDoc = categoryDocs[0] ?? null;
    const isPending = isUploading || isDeleting;
    const isImage = currentDoc?.mime_type.startsWith("image/") ?? false;

    const containerClass = cn(
        variant !== "default" && VARIANT_CONTAINER[variant],
    );

    const renderDelete = (doc: EntityDocument, overLay?: boolean) =>
        actionsEnabled ? (
            <ConfirmDeleteButton
                iconOnly
                size="icon-xs"
                onConfirm={() => deleteDocument(doc.usage_id)}
                isPending={isDeleting}
                stopPropagation
                className={overLay ? "text-primary-foreground" : ""}
            />
        ) : null;

    const variantProps: FileUploadVariantProps = {
        uuid,
        entity,
        categoryDocs,
        effectiveAccept,
        effectiveMaxFiles,
        canUpload,
        currentDoc,
        isUploading,
        isDeleting,
        isPending,
        isImage,
        handleFiles,
        renderDelete,
        label,
        multiple,
        required,
        aspectRatio,
        description,
        className,
        actionsEnabled,
        actionsPlacement,
        replaceEnabled,
        onReplace,
    };

    if (variant === "avatar") {
        return (
            <AvatarVariant {...variantProps} containerClass={containerClass} />
        );
    }

    if (variant === "thumbnail") {
        return (
            <ThumbnailVariant
                {...variantProps}
                containerClass={containerClass}
            />
        );
    }

    if (variant === "card") {
        return <CardVariant {...variantProps} />;
    }

    return <DefaultVariant {...variantProps} />;
}