import { IconLoader2, IconTrash } from "@tabler/icons-react";

import { AttachmentAction } from "@/components/ui/attachment";

export function ConfirmDeleteActions({
    docId,
    isPending,
    onConfirm,
    onCancel,
}: {
    docId: number;
    isPending: boolean;
    onConfirm: (id: number) => void;
    onCancel: () => void;
}) {
    return (
        <>
            <AttachmentAction
                onClick={(e) => {
                    e.stopPropagation();
                    onConfirm(docId);
                }}
                disabled={isPending}
                aria-label="تأیید حذف"
                className="text-destructive hover:text-destructive"
            >
                {isPending ? (
                    <IconLoader2 className="size-3.5 animate-spin" />
                ) : (
                    <IconTrash className="size-3.5" />
                )}
            </AttachmentAction>
            <AttachmentAction
                onClick={(e) => {
                    e.stopPropagation();
                    onCancel();
                }}
                disabled={isPending}
                aria-label="انصراف"
            >
                <span className="text-xs">✕</span>
            </AttachmentAction>
        </>
    );
}
