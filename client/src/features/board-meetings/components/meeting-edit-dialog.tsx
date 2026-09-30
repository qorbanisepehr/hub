import { useEffect, useState } from "react";
import { IconHash, IconTag } from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxInputGroup,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxList,
} from "@/components/ui/combobox";
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { usePermission } from "@/features/auth/components/permission-guard";
import { PERMISSIONS } from "@/lib/permissions";
import { useAttachTags, useTagOptions } from "@/features/tags/hooks";
import { useUpdateBoardMeeting } from "../hooks";
import type { BoardMeetingTagDto } from "../types";

/**
 * Editing a meeting's human-owned fields.
 *
 * One dialog rather than in-place fields, because the meeting's columns are
 * registry-owned and read-only-looking on purpose: a reader should not be able
 * to tell at a glance which of these a person may change. A single explicit
 * action says "this is the editable surface", and a cancel that really cancels
 * is worth more here than the convenience of typing straight into the page.
 *
 * Tags live here too rather than in a tab of their own. A tag is a facet of the
 * meeting, not a section of it: the question is "what is this meeting about",
 * which is the same question the title answers, and splitting the answer across
 * a tab and a dialog made a person hunt for the control. The registry columns
 * are not here, and neither is the summary — the AI writes that.
 *
 * Attendees are the one human-owned column this does not edit yet. The endpoint
 * accepts them and the cast already converts them; the editor is a repeater
 * with a role and a presence flag, and that belongs with the rest of the CRUD
 * surface rather than half-built here.
 */
export function MeetingEditDialog({
    open,
    onOpenChange,
    meetingId,
    title,
    minutes,
    tags,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    meetingId: string;
    title: string | null;
    minutes: string | null;
    tags: BoardMeetingTagDto[];
}) {
    const canUpdate = usePermission([PERMISSIONS.BOARD_MEETING_UPDATE]);
    // Tagging is its own permission, separate from editing the meeting: the
    // taxonomy is shared across every domain, so who may extend it is not the
    // same question as who may fix a typo in a title.
    const canManageTags = usePermission([PERMISSIONS.TAG_MANAGE]);

    const update = useUpdateBoardMeeting(meetingId);
    const attach = useAttachTags();
    const { data: options = [], isLoading: optionsLoading } =
        useTagOptions(undefined);

    const [draftTitle, setDraftTitle] = useState(title ?? "");
    const [draftMinutes, setDraftMinutes] = useState(minutes ?? "");
    const [draftTags, setDraftTags] = useState<string[]>(
        tags.map((tag) => tag.slug),
    );

    // Re-seed whenever the dialog opens, so a cancel really discards and a
    // second open does not show the previous attempt.
    useEffect(() => {
        if (open) {
            setDraftTitle(title ?? "");
            setDraftMinutes(minutes ?? "");
            setDraftTags(tags.map((tag) => tag.slug));
        }
    }, [open, title, minutes, tags]);

    const nameBySlug = new Map(options.map((tag) => [tag.slug, tag.name]));
    for (const tag of tags) {
        if (!nameBySlug.has(tag.slug)) {
            nameBySlug.set(tag.slug, tag.name);
        }
    }

    const isPending = update.isPending || attach.isPending;

    // The two writes are separate endpoints because they are separate
    // permissions and separate resources. Sequencing them — content first, then
    // tags — keeps a failure from leaving the person unsure which half saved,
    // and the tag write only runs when the tagging actually changed.
    function save() {
        const contentChanged =
            draftTitle !== (title ?? "") ||
            draftMinutes !== (minutes ?? "");

        const next = contentChanged
            ? update.mutateAsync({
                  title: draftTitle.trim() === "" ? null : draftTitle.trim(),
                  minutes:
                      draftMinutes.trim() === "" ? null : draftMinutes,
              })
            : Promise.resolve();

        void next
            .then(() => {
                const current = tags.map((tag) => tag.slug);
                const same =
                    current.length === draftTags.length &&
                    current.every((slug) => draftTags.includes(slug));

                return same || !canManageTags
                    ? undefined
                    : attach.mutateAsync({
                          taggable_type: "board-meeting",
                          taggable_id: meetingId,
                          slugs: draftTags,
                      });
            })
            .then(() => onOpenChange(false));
    }

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            title="ویرایش جلسه"
            description="فیلدهایی که در اختیار انسان است. ستون‌های رجیستری با همگام‌سازی به‌روز می‌شوند و خلاصهٔ هوش مصنوعی از تب تحلیل هوشمند می‌آید."
            footer={
                <>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        انصراف
                    </Button>
                    <Button
                        onClick={save}
                        // The header action that opens this dialog is already
                        // permission-gated, but the check is kept here as well:
                        // a permission can be revoked while the dialog is open,
                        // and the save must refuse rather than rely on the
                        // button that opened it having been allowed.
                        disabled={isPending || !canUpdate}
                    >
                        {isPending ? "در حال ذخیره…" : "ذخیرهٔ تغییرات"}
                    </Button>
                </>
            }
        >
            <FieldGroup>
                <Field>
                    <FieldLabel htmlFor="meeting-title">عنوان</FieldLabel>
                    <Input
                        id="meeting-title"
                        value={draftTitle}
                        onChange={(event) => setDraftTitle(event.target.value)}
                        placeholder="عنوان جلسه"
                    />
                </Field>

                <Field>
                    <FieldLabel htmlFor="meeting-minutes">صورتجلسه</FieldLabel>
                    <Textarea
                        id="meeting-minutes"
                        value={draftMinutes}
                        onChange={(event) =>
                            setDraftMinutes(event.target.value)
                        }
                        rows={12}
                        className="font-normal"
                    />
                    <FieldDescription>
                        {draftMinutes.length.toLocaleString("fa-IR")} کاراکتر
                    </FieldDescription>
                </Field>

                {canManageTags && (
                    <Field>
                        <FieldLabel>
                            <span className="flex items-center gap-1.5">
                                <IconTag />
                                برچسب‌ها
                            </span>
                        </FieldLabel>
                        <Combobox
                            multiple
                            value={draftTags}
                            onValueChange={setDraftTags}
                        >
                            <ComboboxInputGroup>
                                <ComboboxInput placeholder="افزودن برچسب…" />
                            </ComboboxInputGroup>
                            <ComboboxContent>
                                <ComboboxList>
                                    {optionsLoading ? (
                                        <div className="flex flex-col gap-1 p-1">
                                            <Skeleton className="h-6 w-full" />
                                            <Skeleton className="h-6 w-2/3" />
                                        </div>
                                    ) : (
                                        options.map((tag) => (
                                            <ComboboxItem
                                                key={tag.slug}
                                                value={tag.slug}
                                                onSelect={(event) => {
                                                    event.preventDefault();
                                                    setDraftTags((prev) =>
                                                        prev.includes(tag.slug)
                                                            ? prev.filter(
                                                                  (s) =>
                                                                      s !==
                                                                      tag.slug,
                                                              )
                                                            : [
                                                                  ...prev,
                                                                  tag.slug,
                                                              ],
                                                    );
                                                }}
                                            >
                                                {tag.name}
                                                <ComboboxItemIndicator />
                                            </ComboboxItem>
                                        ))
                                    )}
                                    <ComboboxEmpty>
                                        برچسبی یافت نشد
                                    </ComboboxEmpty>
                                </ComboboxList>
                            </ComboboxContent>
                        </Combobox>

                        <div className="mt-2 flex flex-wrap items-center gap-1.5">
                            {draftTags.length > 0 ? (
                                draftTags.map((slug) => (
                                    <Badge key={slug} variant="outline">
                                        <IconHash data-icon="inline-start" />
                                        {nameBySlug.get(slug) ?? slug}
                                    </Badge>
                                ))
                            ) : (
                                <span className="text-muted-foreground text-sm">
                                    برچسبی انتخاب نشده است
                                </span>
                            )}
                        </div>
                        <FieldDescription>
                            فهرست کامل برچسب‌های جلسه ذخیره می‌شود؛ آنچه
                            تیک بخورد همان برچسب‌های نهایی است.
                        </FieldDescription>
                    </Field>
                )}
            </FieldGroup>
        </ResponsiveDialog>
    );
}
