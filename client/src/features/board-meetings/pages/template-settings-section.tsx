import { useRef, useState } from "react";
import {
    IconFileText,
    IconPlus,
    IconRefresh,
    IconStar,
    IconUpload,
} from "@tabler/icons-react";

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Spinner } from "@/components/ui/spinner";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import { SectionTemplateSettingsCard } from "@/features/board-meetings/pages/section-template-settings-card";
import { TemplateAuthoringGuide } from "@/features/board-meetings/components/template-authoring-guide";
import { usePermission } from "@/features/auth/components/permission-guard";
import {
    useDeleteTemplate,
    useUpdateTemplate,
    useUploadTemplate,
    useTemplates,
} from "@/features/board-meetings/hooks/use-templates";
import type { TemplateDto } from "@/features/board-meetings/templates-api";

/**
 * The export templates a meeting can be produced with, and the names one may use.
 *
 * TWO THINGS, ONE TAB. The library is what an administrator maintains; the
 * cheat sheet is what makes the library usable, because a person cannot write
 * `{{…}}` into a file without knowing what the names are. A library screen with
 * no cheat sheet would ask everybody to guess.
 *
 * THE CHEAT SHEET GROUPS ROW FIELDS, and the grouping is the whole reason it
 * exists. Repeating names are filled by duplicating a TABLE ROW, so the columns
 * of a group have to sit in one row together — a flat list of names would teach
 * the one mistake that makes an attendance list render as a single line.
 *
 * IT ALSO SAYS WHERE EACH NAME APPEARS. Somebody laying out a template is
 * deciding what goes where, and a flat list of twenty-two keys does not tell
 * them that `attendee.role` lives under the attendees heading.
 */
export function TemplateSettingsSection() {
    const canManage = usePermission(["export-template.manage"]);

    const { data: templates, isLoading, isFetching, refetch } =
        useTemplates();

    const update = useUpdateTemplate();
    const remove = useDeleteTemplate();

    const [uploadOpen, setUploadOpen] = useState(false);

    return (
        <div className="grid gap-6">
            {/* Sections first. A template that decides WHICH parts of a meeting
                print is the more fundamental question than the file Word is built
                from, and it is the one with no screen anywhere else. */}
            <SectionTemplateSettingsCard />

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <IconFileText className="size-5" />
                        قالب‌های خروجی
                    </CardTitle>
                    <CardDescription>
                        فایلی که خروجی از آن ساخته می‌شود — Word، Markdown یا
                        JSON. خودِ فایل تصمیم می‌گیرد چه چاپ شود: اگر{" "}
                        <span className="mx-1 font-medium" dir="ltr">
                            {"{{content}}"}
                        </span>{" "}
                        داشته باشد سند تولیدی همان‌جا نوشته می‌شود، و اگر نداشته
                        باشد خودِ فایل سند است و فقط نام‌های داخلش پر می‌شوند.
                    </CardDescription>
                </CardHeader>

                <CardContent className="grid gap-3">
                    <div className="flex flex-wrap items-center justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => void refetch()}
                            disabled={isFetching}
                        >
                            {isFetching ? (
                                <Spinner className="size-4" />
                            ) : (
                                <IconRefresh className="size-4" />
                            )}
                            بازخوانی
                        </Button>

                        {canManage && (
                            <Button
                                type="button"
                                onClick={() => setUploadOpen(true)}
                            >
                                <IconUpload className="size-4" />
                                بارگذاری قالب
                            </Button>
                        )}
                    </div>

                    {isLoading ? (
                        <div className="flex justify-center py-8">
                            <Spinner />
                        </div>
                    ) : templates && templates.length > 0 ? (
                        <ul className="grid gap-2">
                            {templates.map((template) => (
                                <TemplateRow
                                    key={template.slug}
                                    template={template}
                                    canManage={canManage}
                                    isPending={
                                        update.isPending ||
                                        remove.isPending
                                    }
                                    onMakeDefault={() =>
                                        update.mutate({
                                            slug: template.slug,
                                            is_default: true,
                                        })
                                    }
                                    onDelete={() =>
                                        remove.mutate(template.slug)
                                    }
                                />
                            ))}
                        </ul>
                    ) : (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            هنوز قالبی بارگذاری نشده است. بدون قالب، خروجی
                            همان سند تولیدی همیشگی است.
                        </p>
                    )}
                </CardContent>
            </Card>

            <TemplateAuthoringGuide />

            <UploadTemplateDialog
                open={uploadOpen}
                onOpenChange={setUploadOpen}
            />
        </div>
    );
}

function TemplateRow({
    template,
    canManage,
    isPending,
    onMakeDefault,
    onDelete,
}: {
    template: TemplateDto;
    canManage: boolean;
    isPending: boolean;
    onMakeDefault: () => void;
    onDelete: () => void;
}) {
    return (
        <li className="flex flex-wrap items-center justify-between gap-3 rounded-lg border px-3 py-2">
            <div className="flex min-w-0 flex-col">
                <div className="flex flex-wrap items-center gap-1.5">
                    <span className="truncate font-medium text-sm">
                        {template.name}
                    </span>

                    {/* The format is what the file IS, not decoration on it. Two
                        templates called "official" can be a letterhead and a
                        Markdown payload, and without this they are two identical
                        rows that a person has to open each of to tell apart. */}
                    <Badge variant="outline" dir="ltr">
                        {template.format}
                    </Badge>

                    {template.is_default && (
                        <Badge variant="info">
                            <IconStar className="size-3" />
                            پیش‌فرض
                        </Badge>
                    )}
                </div>

                <span className="text-xs text-muted-foreground">
                    {new Intl.NumberFormat("fa-IR", {
                        maximumFractionDigits: 0,
                    }).format(template.size / 1024)}{" "}
                    کیلوبایت
                </span>
            </div>

            {canManage && (
                <div className="flex items-center gap-1">
                    {!template.is_default && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            title="پیش‌فرض کردن"
                            onClick={onMakeDefault}
                            disabled={isPending}
                        >
                            <IconStar className="size-4" />
                        </Button>
                    )}

                    <ConfirmDeleteButton
                        iconOnly
                        onConfirm={onDelete}
                        isPending={isPending}
                    />
                </div>
            )}
        </li>
    );
}

function UploadTemplateDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const upload = useUploadTemplate();
    const input = useRef<HTMLInputElement>(null);

    const [name, setName] = useState("");
    const [isDefault, setIsDefault] = useState(false);

    const file = input.current?.files?.[0] ?? null;

    const submit = () => {
        if (!file) return;

        upload.mutate(
            {
                file,
                name: name.trim() || file.name,
                is_default: isDefault,
            },
            {
                onSuccess: () => {
                    onOpenChange(false);
                    setName("");
                    setIsDefault(false);

                    if (input.current) {
                        input.current.value = "";
                    }
                },
            },
        );
    };

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            title="بارگذاری قالب"
            description="فایل docx بدون ماکرو، یا Markdown و JSON. اگر در آن content بنویسید، سند تولیدی همان‌جا نوشته می‌شود؛ اگر نگویید، خودِ فایل سند است و فقط نام‌های داخلش پر می‌شوند."
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        انصراف
                    </Button>
                    <Button
                        type="button"
                        disabled={!file || upload.isPending}
                        onClick={submit}
                    >
                        {upload.isPending ? (
                            <Spinner className="size-4" />
                        ) : (
                            <IconPlus className="size-4" />
                        )}
                        بارگذاری
                    </Button>
                </>
            }
        >
            <div className="grid gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="template-file">فایل</Label>
                    <Input
                        id="template-file"
                        type="file"
                        ref={input}
                        accept=".docx,.md,.json"
                    />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="template-name">نام قالب</Label>
                    <Input
                        id="template-name"
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                        placeholder={file ? file.name : "مثلاً نامهٔ رسمی جلسه"}
                    />
                </div>

                <div className="flex items-center gap-2">
                    <Checkbox
                        id="template-default"
                        checked={isDefault}
                        onCheckedChange={(checked) =>
                            setIsDefault(checked === true)
                        }
                    />
                    <Label htmlFor="template-default">
                        پیش‌فرضِ دامنه باشد
                    </Label>
                </div>

                <p className="text-xs text-muted-foreground">
                    نام فایل شما در کلیدِ ذخیره‌سازی می‌آید و هرگز تغییر نمی‌کند؛
                    نامی که اینجا می‌نویسید فقط برای نمایش است.
                </p>
            </div>
        </ResponsiveDialog>
    );
}

