import { useCallback } from "react";
import type { DocumentRequirement } from "@/features/documents/types";
import { formatBytes } from "@/lib/file-size";

export function useDocumentValidation(requirement: DocumentRequirement | null) {
    const validateFile = useCallback(
        async (file: File): Promise<string[]> => {
            const errors: string[] = [];

            if (!requirement) return errors;

            // File size
            if (
                requirement.min_file_size &&
                file.size < requirement.min_file_size
            ) {
                errors.push(
                    `حجم فایل نباید کمتر از ${formatBytes(requirement.min_file_size)} باشد.`,
                );
            }

            if (
                requirement.max_file_size &&
                file.size > requirement.max_file_size
            ) {
                errors.push(
                    `حجم فایل نباید بیشتر از ${formatBytes(requirement.max_file_size)} باشد.`,
                );
            }

            // MIME type
            if (
                requirement.mime_types &&
                !requirement.mime_types.includes(file.type)
            ) {
                errors.push(`فرمت فایل مجاز نیست.`);
            }

            // Image dimensions
            if (requirement.dimensions && file.type.startsWith("image/")) {
                await new Promise<void>((resolve) => {
                    const img = new Image();
                    const objectUrl = URL.createObjectURL(file);

                    img.onload = () => {
                        URL.revokeObjectURL(objectUrl);
                        const {
                            min_width,
                            min_height,
                            max_width,
                            max_height,
                        } = requirement.dimensions!;

                        if (min_width && img.width < min_width) {
                            errors.push(
                                `عرض تصویر باید حداقل ${min_width} پیکسل باشد.`,
                            );
                        }
                        if (min_height && img.height < min_height) {
                            errors.push(
                                `ارتفاع تصویر باید حداقل ${min_height} پیکسل باشد.`,
                            );
                        }
                        if (max_width && img.width > max_width) {
                            errors.push(
                                `عرض تصویر نباید بیشتر از ${max_width} پیکسل باشد.`,
                            );
                        }
                        if (max_height && img.height > max_height) {
                            errors.push(
                                `ارتفاع تصویر نباید بیشتر از ${max_height} پیکسل باشد.`,
                            );
                        }
                        resolve();
                    };

                    img.onerror = () => {
                        URL.revokeObjectURL(objectUrl);
                        errors.push("فایل تصویری معتبر نیست.");
                        resolve();
                    };

                    img.src = objectUrl;
                });
            }

            return errors;
        },
        [requirement],
    );

    return { validateFile };
}
