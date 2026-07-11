import * as React from "react";
import {
    IconFile,
    IconFileText,
    IconFileTypePdf,
    IconFileSpreadsheet,
    IconFileTypeZip,
    IconPhoto,
} from "@tabler/icons-react";

const FILE_ICON_RULES: Array<{
    pattern: RegExp;
    Icon: React.ComponentType<{ className?: string }>;
}> = [
    { pattern: /^image\//, Icon: IconPhoto },
    { pattern: /pdf/, Icon: IconFileTypePdf },
    { pattern: /msword|officedocument\.wordprocessingml/, Icon: IconFileText },
    {
        pattern: /officedocument\.spreadsheetml|excel/,
        Icon: IconFileSpreadsheet,
    },
    { pattern: /zip|rar|compressed/, Icon: IconFileTypeZip },
];

export function getFileIcon(
    mimeType: string,
    className?: string,
): React.ReactNode {
    const match = FILE_ICON_RULES.find(({ pattern }) => pattern.test(mimeType));
    const Icon = match?.Icon ?? IconFile;

    return <Icon className={className ?? "size-4"} />;
}
