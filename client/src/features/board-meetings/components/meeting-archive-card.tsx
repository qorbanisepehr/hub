import { IconFileText, IconPaperclip } from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { EmptyState } from "@/components/layout";

/**
 * Read-only listing of the meeting's source-archive documents (the
 * `documents` JSONB carried over from TCK: approval PDF filenames under
 * `archive/approvals/…`). The binaries stay on the source server until a
 * one-time import into the hub Document system; until then this card is
 * the archive's window into the UI. Filenames encode the approval number
 * and the meeting date — e.g. `1001-2(1401-01-17).pdf`.
 */
export function MeetingArchiveCard({ filenames }: { filenames: string[] }) {
    if (filenames.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
                <CardTitle className="flex items-center gap-2 text-lg">
                    <IconFileText className="size-5" />
                    آرشیو مصوبات (از سیستم مبدأ)
                    <Badge variant="outline">
                        {filenames.length.toLocaleString("fa-IR")}
                    </Badge>
                </CardTitle>
            </CardHeader>
            <CardContent>
                {filenames.length > 0 ? (
                    <ul className="divide-y">
                        {filenames.map((filename) => (
                            <li
                                key={filename}
                                className="flex items-center gap-2 py-2"
                            >
                                <IconPaperclip className="text-muted-foreground size-4 shrink-0" />
                                <span
                                    dir="ltr"
                                    className="text-muted-foreground truncate text-xs"
                                >
                                    {filename}
                                </span>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <EmptyState
                        icon={IconFileText}
                        message="موردی در آرشیو مبدأ ثبت نشده است"
                    />
                )}
            </CardContent>
        </Card>
    );
}
