import { useState } from "react";
import { toast } from "sonner";
import {
    IconDotsVertical,
    IconFileText,
    IconLoader2,
    IconPrinter,
} from "@tabler/icons-react";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { getApiError } from "@/lib/error-utils";
import {
    saveBlobResponse,
    exportDateStamp,
    EXPORT_MIME_TYPES,
} from "@/lib/download";

type DocumentPrintMenuProps = {
    /**
     * Fetch the printable document for one record in the given format
     * (the domain api's `/document?format=` call, responseType blob).
     */
    fetchDocument: (format: "pdf" | "docx") => Promise<{
        data: unknown;
        headers?: Record<string, unknown>;
    }>;
    /** Filename fallback when the server withholds Content-Disposition. */
    filenamePrefix: string;
    ariaLabel?: string;
};

/**
 * Print/download menu for the single-record document endpoints
 * (employee profile, CV, questionnaire): renders the record server-side
 * as one PDF or Word document, field-access rules already applied.
 */
export function DocumentPrintMenu({
    fetchDocument,
    filenamePrefix,
    ariaLabel = "چاپ و دریافت سند",
}: DocumentPrintMenuProps) {
    const [pendingFormat, setPendingFormat] = useState<"pdf" | "docx" | null>(
        null,
    );

    const download = async (format: "pdf" | "docx") => {
        setPendingFormat(format);

        try {
            const response = await fetchDocument(format);

            saveBlobResponse(
                response,
                `${filenamePrefix}-${exportDateStamp()}.${format}`,
                EXPORT_MIME_TYPES[format],
            );
        } catch (err) {
            toast.error(getApiError(err) ?? "خطا در دریافت سند.");
        } finally {
            setPendingFormat(null);
        }
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <Button
                        variant="outline"
                        aria-label={ariaLabel}
                        disabled={pendingFormat !== null}
                    />
                }
            >
                {pendingFormat ? (
                    <IconLoader2 className="size-4 animate-spin" />
                ) : (
                    <IconPrinter className="size-4" />
                )}
                سند
                <IconDotsVertical className="size-3" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" side="bottom">
                <DropdownMenuItem
                    onClick={() => download("pdf")}
                    disabled={pendingFormat !== null}
                >
                    <IconFileText className="size-4" />
                    دانلود PDF
                </DropdownMenuItem>
                <DropdownMenuItem
                    onClick={() => download("docx")}
                    disabled={pendingFormat !== null}
                >
                    <IconFileText className="size-4" />
                    دانلود Word
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
