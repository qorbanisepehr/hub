import { useEffect, useState } from "react";
import { IconRestore } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { Badge } from "@/components/ui/badge";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import { usePermission } from "@/features/auth/components/permission-guard";
import { TemplateSectionEditor } from "@/features/board-meetings/components/template-section-editor";
import {
    useClearMeetingSectionTemplate,
    useMeetingSectionTemplate,
    useUpdateMeetingSectionTemplate,
} from "@/features/board-meetings/hooks/use-section-templates";

/**
 * This meeting's own printed form.
 *
 * HERE AND NOT IN SETTINGS. A Settings table of overrides would mean picking a
 * meeting out of a search dropdown to tick eight boxes for it — discoverable only
 * if you already know which meeting to look for. The person who wants a different
 * layout for one meeting is looking at that meeting.
 *
 * The domain's template is what this falls back to, and the dialog says so
 * rather than showing an empty editor that looks like the meeting has no sections
 * at all. "پاک کردن و پیروی از قالب دامنه" is a button, not an empty submission,
 * so handing a meeting back is an explicit act.
 */
export function MeetingSectionTemplateDialog({
    open,
    onOpenChange,
    meetingId,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    meetingId: number | string;
}) {
    const canManage = usePermission(["export-template.manage"]);

    const { data, isLoading } = useMeetingSectionTemplate(
        meetingId,
        open && canManage,
    );

    const save = useUpdateMeetingSectionTemplate(meetingId);
    const clear = useClearMeetingSectionTemplate(meetingId);

    const [draft, setDraft] = useState<string[]>([]);

    const stored = data?.effective?.join("|") ?? "";

    useEffect(() => {
        if (stored !== "") {
            setDraft(stored.split("|"));
        }
    }, [stored]);

    const isOverridden = (data?.stored ?? null) !== null;
    const dirty = draft.join("|") !== stored;
    const pending = save.isPending || clear.isPending;

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            title="بخش‌های چاپی این جلسه"
            description={
                isOverridden
                    ? "این جلسه قالبِ مخصوص خود را دارد."
                    : "این جلسه از قالبِ دامنه پیروی می‌کند."
            }
            footer={
                <>
                    {isOverridden && (
                        <Button
                            type="button"
                            variant="ghost"
                            disabled={!canManage || pending}
                            onClick={() => clear.mutate()}
                        >
                            {clear.isPending ? (
                                <Spinner className="size-4" />
                            ) : (
                                <IconRestore className="size-4" />
                            )}
                            پیروی از قالب دامنه
                        </Button>
                    )}

                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        بستن
                    </Button>

                    <Button
                        type="button"
                        disabled={!canManage || !dirty || pending}
                        onClick={() => save.mutate(draft)}
                    >
                        {save.isPending && <Spinner className="size-4" />}
                        ذخیره
                    </Button>
                </>
            }
        >
            {isLoading || !data ? (
                <div className="flex justify-center py-8">
                    <Spinner />
                </div>
            ) : (
                <div className="grid gap-4">
                    {!isOverridden && (
                        <div className="flex items-center gap-2">
                            <Badge variant="outline">از قالب دامنه</Badge>
                            <span className="text-xs text-muted-foreground">
                                هر تغییری اینجا، قالبِ مخصوص همین جلسه
                                می‌شود.
                            </span>
                        </div>
                    )}

                    <TemplateSectionEditor
                        available={data.available}
                        value={draft}
                        onChange={setDraft}
                        disabled={!canManage || pending}
                    />
                </div>
            )}
        </ResponsiveDialog>
    );
}
