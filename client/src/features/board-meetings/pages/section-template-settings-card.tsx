import { useEffect, useState } from "react";
import { IconAdjustments, IconRestore } from "@tabler/icons-react";

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Spinner } from "@/components/ui/spinner";
import { usePermission } from "@/features/auth/components/permission-guard";
import { TemplateSectionEditor } from "@/features/board-meetings/components/template-section-editor";
import {
    useSectionTemplates,
    useUpdateSectionTemplate,
} from "@/features/board-meetings/hooks/use-section-templates";

/**
 * Which sections every printed meeting contains, and in what order.
 *
 * The draft is held here and seeded from the server's answer, so a person edits
 * freely and only commits once. Saving starts from the DOMAIN template rather
 * than from whatever is on screen, which is what makes "reset" the same gesture
 * as "undo": both put the whole list back rather than leaving a section out that
 * they cannot tell was there.
 *
 * The order shown IS the order that will print, so what a person reads while
 * editing is what they will get — the alternative is a list that looks
 * alphabetical while the document is not.
 */
export function SectionTemplateSettingsCard() {
    const canManage = usePermission(["export-template.manage"]);

    const { data, isLoading } = useSectionTemplates();
    const save = useUpdateSectionTemplate();

    const [draft, setDraft] = useState<string[]>([]);

    // Seeded whenever the server's answer changes, so a save elsewhere is
    // reflected rather than quietly overwritten by a stale draft.
    const stored = data?.effective?.join("|") ?? "";

    useEffect(() => {
        if (stored !== "") {
            setDraft(stored.split("|"));
        }
    }, [stored]);

    if (isLoading || !data) {
        return (
            <Card>
                <CardContent className="flex justify-center py-10">
                    <Spinner />
                </CardContent>
            </Card>
        );
    }

    const dirty = draft.join("|") !== stored;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <IconAdjustments className="size-5" />
                    بخش‌های سند چاپی
                </CardTitle>
                <CardDescription>
                    هر جلسه با همین بخش‌ها چاپ می‌شود. ترتیب همین است که در خروجی
                    می‌آید؛ یک جلسه می‌تواند قالبِ خودش را داشته باشد.
                    {data.stored === null && (
                        <>
                            {" "}
                            <span className="text-muted-foreground">
                                (هنوز قالبی ذخیره نشده؛ فهرست بالا همان پیش‌فرضِ
                                برنامه است.)
                            </span>
                        </>
                    )}
                </CardDescription>
            </CardHeader>

            <CardContent className="grid gap-4">
                <TemplateSectionEditor
                    available={data.available}
                    value={draft}
                    onChange={setDraft}
                    disabled={!canManage || save.isPending}
                />

                {canManage && (
                    <div className="flex items-center justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={!dirty || save.isPending}
                            onClick={() =>
                                setDraft(stored === "" ? [] : stored.split("|"))
                            }
                        >
                            <IconRestore className="size-4" />
                            بازگشت
                        </Button>

                        <Button
                            type="button"
                            disabled={!dirty || draft.length === 0}
                            onClick={() => save.mutate(draft)}
                        >
                            {save.isPending && (
                                <Spinner className="size-4" />
                            )}
                            ذخیره
                        </Button>
                    </div>
                )}

                {data.overrides.length > 0 && (
                    <p className="text-xs text-muted-foreground">
                        {data.overrides.length.toLocaleString("fa-IR")} جلسه قالبِ
                        مخصوص خود را دارد و از این فهرست پیروی نمی‌کند.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
