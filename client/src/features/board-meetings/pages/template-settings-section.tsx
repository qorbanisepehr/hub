import { useRef, useState } from "react";
import {
    IconBraces,
    IconEye,
    IconFileText,
    IconFileTypeDoc,
    IconFileTypeTxt,
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
import { EXPORT_MIME_TYPES, saveBlobResponse } from "@/lib/download";
import { SectionTemplateSettingsCard } from "@/features/board-meetings/pages/section-template-settings-card";
import { TemplateAuthoringGuide } from "@/features/board-meetings/components/template-authoring-guide";
import { usePermission } from "@/features/auth/components/permission-guard";
import {
    useDeleteTemplate,
    usePreviewTemplate,
    useUpdateTemplate,
    useUploadTemplate,
    useTemplates,
} from "@/features/board-meetings/hooks/use-templates";
import type { TemplateDto } from "@/features/board-meetings/templates-api";

const FORMATS = [
    { key: "docx", label: "Word", icon: IconFileTypeDoc, accept: ".docx" },
    { key: "md", label: "Markdown", icon: IconFileTypeTxt, accept: ".md" },
    { key: "json", label: "JSON", icon: IconBraces, accept: ".json" },
] as const;

type TemplateFormat = (typeof FORMATS)[number]["key"];

/**
 * The export templates a meeting can be produced with, and the names one may use.
 *
 * TWO THINGS, ONE TAB. The library is what an administrator maintains; the
 * cheat sheet is what makes the library usable, because a person cannot write
 * `{{…}}` into a file without knowing what the names are. A library screen with
 * no cheat sheet would ask everybody to guess.
 *
 * ONE LIBRARY PER FORMAT. The server scopes templates by format — a Word picker
 * must not offer a Markdown frame — so the tabs are the contract, not decoration.
 * A flat list built from one request could only ever show Word, which is how the
 * Markdown and JSON libraries stayed invisible even after the export route learned
 * to fill them.
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
    // A preview contains a real meeting, so it needs the permission that lets
    // somebody take a copy of one as well as the one that manages templates.
    const canExportMeetings = usePermission(["board-meeting.export"]);
    const canPreview = canManage && canExportMeetings;

    const [format, setFormat] = useState<TemplateFormat>("docx");

    const { data: templates, isLoading, isFetching, refetch } =
        useTemplates(format);

    const update = useUpdateTemplate();
    const remove = useDeleteTemplate();
    const preview = usePreviewTemplate();

    const [uploadOpen, setUploadOpen] = useState(false);

    const current = FORMATS.find((option) => option.key === format)!;

    const downloadPreview = (template: TemplateDto) => {
        preview.mutate(
            { slug: template.slug, format: template.format },
            {
                onSuccess: (response) => {
                    saveBlobResponse(
                        response,
                        `preview-${template.slug}.${template.format}`,
                        EXPORT_MIME_TYPES[template.format] ??
                            "application/octet-stream",
                    );
                },
            },
        );
    };

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
                        هر فرمت کتابخانهٔ خودش را دارد — Word، Markdown یا JSON.
                        خودِ فایل تصمیم می‌گیرد چه چاپ شود: اگر{" "}
                        <span className="mx-1 font-medium" dir="ltr">
                            {"{{content}}"}
                        </span>{" "}
                        داشته باشد سند تولیدی همان‌جا نوشته می‌شود، و اگر نداشته
                        باشد خودِ فایل سند است و فقط نام‌های داخلش پر می‌شوند.
                    </CardDescription>
                </CardHeader>

                <CardContent className="grid gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div
                            role="group"
                            aria-label="فرمت قالب"
                            className="flex flex-wrap gap-1.5"
                        >
                            {FORMATS.map(({ key, label, icon: Icon }) => (
                                <button
                                    key={key}
                                    type="button"
                                    aria-pressed={format === key}
                                    onClick={() => setFormat(key)}
                                    className={`inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-sm transition-colors ${
                                        format === key
                                            ? "border-primary bg-primary/10 font-medium"
                                            : "border-border hover:bg-muted"
                                    }`}
                                >
                                    <Icon className="size-4" />
                                    {label}
                                </button>
                            ))}
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
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
                                    canPreview={canPreview}
                                    isPending={
                                        update.isPending ||
                                        remove.isPending
                                    }
                                    isPreviewing={
                                        preview.isPending &&
                                        preview.variables?.slug ===
                                            template.slug
                                    }
                                    onPreview={() =>
                                        downloadPreview(template)
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
                            هنوز قالب {current.label} بارگذاری نشده است. بدون
                            قالب، خروجی همان سند تولیدی همیشگی است.
                        </p>
                    )}
                </CardContent>
            </Card>

            <TemplateAuthoringGuide />

            <UploadTemplateDialog
                open={uploadOpen}
                onOpenChange={setUploadOpen}
                format={format}
                onUploaded={(uploaded) => {
                    const match = FORMATS.find(
                        (option) => option.key === uploaded,
                    );

                    if (match) {
                        setFormat(match.key);
                    }
                }}
            />
        </div>
    );
}

function TemplateRow({
    template,
    canManage,
    canPreview,
    isPending,
    isPreviewing,
    onPreview,
    onMakeDefault,
    onDelete,
}: {
    template: TemplateDto;
    canManage: boolean;
    canPreview: boolean;
    isPending: boolean;
    isPreviewing: boolean;
    onPreview: () => void;
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
                    {canPreview && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            title="پیش‌نمایش با جدیدترین جلسه"
                            onClick={onPreview}
                            disabled={isPending || isPreviewing}
                        >
                            {isPreviewing ? (
                                <Spinner className="size-4" />
                            ) : (
                                <IconEye className="size-4" />
                            )}
                        </Button>
                    )}

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
    format,
    onUploaded,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    format: TemplateFormat;
    onUploaded: (format: string) => void;
}) {
    const upload = useUploadTemplate();
    const input = useRef<HTMLInputElement>(null);
    const current = FORMATS.find((option) => option.key === format)!;

    const [name, setName] = useState("");
    // The chosen file is state, not a read of the input during render: the input's
    // `files` list does not re-render the dialog, so the upload button used to stay
    // disabled until some unrelated keystroke happened to refresh it.
    const [file, setFile] = useState<File | null>(null);
    const [isDefault, setIsDefault] = useState(false);

    const close = (next: boolean) => {
        if (!next) {
            setFile(null);
            setName("");
            setIsDefault(false);

            if (input.current) {
                input.current.value = "";
            }
        }

        onOpenChange(next);
    };

    const submit = () => {
        if (!file) return;

        upload.mutate(
            {
                file,
                name: name.trim() || file.name,
                is_default: isDefault,
            },
            {
                onSuccess: (response) => {
                    // The format comes from the file, not from the tab. Following
                    // it means an upload never lands in a library the person is
                    // not looking at.
                    onUploaded(response.data.data.format);
                    close(false);
                },
            },
        );
    };

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={close}
            title="بارگذاری قالب"
            description={`فایل ${current.label} را انتخاب کنید. اگر در آن content بنویسید، سند تولیدی همان‌جا نوشته می‌شود؛ اگر نگویید، خودِ فایل سند است و فقط نام‌های داخلش پر می‌شوند.`}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => close(false)}
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
                        accept={current.accept}
                        onChange={(event) =>
                            setFile(event.target.files?.[0] ?? null)
                        }
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
                        پیش‌فرضِ {current.label} باشد
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
