import { useState } from "react";
import {
    IconBraces,
    IconEye,
    IconFileText,
    IconFileTypeDoc,
    IconFileTypeTxt,
    IconInfoCircle,
    IconRefresh,
    IconReplace,
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
import { FileUpload } from "@/components/ui/file-upload";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { Spinner } from "@/components/ui/spinner";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ConfirmDeleteButton } from "@/components/ui/confirm-delete-button";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import { EXPORT_MIME_TYPES, saveBlobResponse } from "@/lib/download";
import { cn } from "@/lib/utils";
import { SectionTemplateSettingsCard } from "@/features/board-meetings/pages/section-template-settings-card";
import { TemplateAuthoringGuide } from "@/features/board-meetings/components/template-authoring-guide";
import { usePermission } from "@/features/auth/components/permission-guard";
import {
    useDeleteTemplate,
    usePreviewTemplate,
    useReplaceTemplate,
    useUpdateTemplate,
    useUploadTemplate,
    useTemplates,
} from "@/features/board-meetings/hooks/use-templates";
import type { TemplateDto } from "@/features/board-meetings/templates-api";

const FORMATS = [
    {
        key: "docx",
        label: "Word",
        icon: IconFileTypeDoc,
        accept: ".docx",
        tone: "border-sky-500/20 bg-sky-500/10 text-sky-600 dark:text-sky-400",
    },
    {
        key: "md",
        label: "Markdown",
        icon: IconFileTypeTxt,
        accept: ".md",
        tone: "border-violet-500/20 bg-violet-500/10 text-violet-600 dark:text-violet-400",
    },
    {
        key: "json",
        label: "JSON",
        icon: IconBraces,
        accept: ".json",
        tone: "border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400",
    },
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

    const update = useUpdateTemplate(format);
    const remove = useDeleteTemplate(format);
    const preview = usePreviewTemplate();

    const [uploadOpen, setUploadOpen] = useState(false);
    const [replacing, setReplacing] = useState<TemplateDto | null>(null);

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

            <Card className="gap-0 overflow-hidden py-0">
                <CardHeader className="gap-4 border-b bg-muted/30 p-4">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="flex min-w-0 items-start gap-3">
                            <div className="grid size-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                                <IconFileText className="size-5" />
                            </div>

                            <div className="grid gap-1">
                                <CardTitle className="text-base">
                                    کتابخانهٔ قالب‌ها
                                </CardTitle>
                                <CardDescription className="max-w-2xl">
                                    هر فرمت کتابخانهٔ خودش را دارد. خودِ فایل
                                    تصمیم می‌گیرد چه چاپ شود: اگر{" "}
                                    <span
                                        className="mx-1 font-medium"
                                        dir="ltr"
                                    >
                                        {"{{content}}"}
                                    </span>{" "}
                                    داشته باشد سند تولیدی همان‌جا نوشته می‌شود،
                                    و اگر نداشته باشد خودِ فایل سند است و فقط
                                    نام‌های داخلش پر می‌شوند.
                                </CardDescription>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon-sm"
                                title="بازخوانی"
                                aria-label="بازخوانی"
                                onClick={() => void refetch()}
                                disabled={isFetching}
                            >
                                {isFetching ? (
                                    <Spinner className="size-4" />
                                ) : (
                                    <IconRefresh className="size-4" />
                                )}
                            </Button>

                            {canManage && (
                                <Button
                                    type="button"
                                    onClick={() => setUploadOpen(true)}
                                >
                                    <IconUpload className="size-4" />
                                    بارگذاری
                                </Button>
                            )}
                        </div>
                    </div>

                    <Tabs
                        value={format}
                        onValueChange={(value) =>
                            setFormat(value as TemplateFormat)
                        }
                    >
                        <TabsList className="w-full justify-start sm:w-fit">
                            {FORMATS.map(({ key, label, icon: Icon }) => (
                                <TabsTrigger
                                    key={key}
                                    value={key}
                                    className="gap-1.5 px-3"
                                >
                                    <Icon className="size-4" />
                                    {label}
                                </TabsTrigger>
                            ))}
                        </TabsList>
                    </Tabs>
                </CardHeader>

                <CardContent className="p-0">
                    <div className="flex items-center justify-between gap-2 border-b px-4 py-2.5 text-xs text-muted-foreground">
                        <span>
                            {current.label} · {templates?.length ?? 0} قالب
                        </span>
                        <span className="hidden sm:inline">
                            بدون قالب، خروجی پیش‌فرض تولید می‌شود.
                        </span>
                    </div>

                    {format === "json" && (
                        <JsonTemplateHint className="m-4" />
                    )}

                    {isLoading ? (
                        <TemplateListSkeleton />
                    ) : templates && templates.length > 0 ? (
                        <ul className="divide-y">
                            {templates.map((template) => (
                                <TemplateRow
                                    key={template.slug}
                                    template={template}
                                    canManage={canManage}
                                    canPreview={canPreview}
                                    isPending={
                                        update.isPending || remove.isPending
                                    }
                                    isPreviewing={
                                        preview.isPending &&
                                        preview.variables?.slug ===
                                            template.slug
                                    }
                                    onPreview={() =>
                                        downloadPreview(template)
                                    }
                                    onReplace={() =>
                                        setReplacing(template)
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
                        <EmptyTemplateState
                            format={current}
                            canManage={canManage}
                            onUpload={() => setUploadOpen(true)}
                        />
                    )}
                </CardContent>
            </Card>

            <TemplateAuthoringGuide format={format} />

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

            <ReplaceTemplateDialog
                template={replacing}
                onOpenChange={(open) => {
                    if (!open) {
                        setReplacing(null);
                    }
                }}
            />
        </div>
    );
}

/**
 * The one JSON rule an author cannot guess from a Word-shaped cheat sheet.
 *
 * A template language layered on JSON is not JSON until it is filled: a text
 * placeholder belongs inside a JSON string, while `{{content}}` belongs where
 * an object goes and is deliberately unquoted. Saying that here is cheaper than
 * letting the first export answer with a 422.
 */
function JsonTemplateHint({ className }: { className?: string }) {
    return (
        <div
            className={cn(
                "flex gap-2 rounded-lg border border-amber-500/25 bg-amber-500/5 p-3 text-xs",
                className,
            )}
        >
            <IconInfoCircle className="mt-0.5 size-4 shrink-0 text-amber-600" />

            <div className="grid gap-1">
                <p className="font-medium text-amber-900 dark:text-amber-200">
                    در JSON، مقدار متنی را داخل گیومه بگذارید
                </p>
                <p className="text-muted-foreground">
                    <code dir="ltr" className="font-mono">
                        {'"title": "{{title}}"'}
                    </code>{" "}
                    درست است؛{" "}
                    <code dir="ltr" className="font-mono">
                        {'"meeting": {{content}}'}
                    </code>{" "}
                    تنها استثناست و بدون گیومه می‌آید. نوشتن{" "}
                    <code dir="ltr" className="font-mono">
                        {"{{title}}"}
                    </code>{" "}
                    بدون گیومه خروجی را نامعتبر می‌کند و خطای ۴۲۲ می‌گیرد.
                </p>
            </div>
        </div>
    );
}

function TemplateListSkeleton() {
    return (
        <ul className="divide-y">
            {[0, 1, 2].map((row) => (
                <li key={row} className="flex items-center gap-3 px-4 py-3">
                    <Skeleton className="size-10 rounded-xl" />
                    <div className="grid flex-1 gap-2">
                        <Skeleton className="h-4 w-40" />
                        <Skeleton className="h-3 w-24" />
                    </div>
                </li>
            ))}
        </ul>
    );
}

function EmptyTemplateState({
    format,
    canManage,
    onUpload,
}: {
    format: (typeof FORMATS)[number];
    canManage: boolean;
    onUpload: () => void;
}) {
    const Icon = format.icon;

    return (
        <div className="flex flex-col items-center gap-3 px-6 py-12 text-center">
            <div
                className={cn(
                    "grid size-12 place-items-center rounded-2xl border",
                    format.tone,
                )}
            >
                <Icon className="size-6" />
            </div>

            <div className="grid gap-1">
                <p className="font-medium">هنوز قالب {format.label} ندارید</p>
                <p className="max-w-sm text-sm text-muted-foreground">
                    بدون قالب، خروجی همان سند تولیدی همیشگی است. یک فایل{" "}
                    {format.accept} بارگذاری کنید تا چیدمان خودتان را داشته
                    باشید.
                </p>
            </div>

            {canManage && (
                <Button onClick={onUpload}>
                    <IconUpload className="size-4" />
                    بارگذاری اولین قالب
                </Button>
            )}
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
    onReplace,
    onMakeDefault,
    onDelete,
}: {
    template: TemplateDto;
    canManage: boolean;
    canPreview: boolean;
    isPending: boolean;
    isPreviewing: boolean;
    onPreview: () => void;
    onReplace: () => void;
    onMakeDefault: () => void;
    onDelete: () => void;
}) {
    const meta = FORMATS.find((option) => option.key === template.format);
    const Icon = meta?.icon ?? IconFileText;

    return (
        <li className="group flex flex-wrap items-center gap-3 px-4 py-3 transition-colors hover:bg-muted/40">
            <div
                className={cn(
                    "grid size-10 shrink-0 place-items-center rounded-xl border",
                    meta?.tone ?? "bg-muted text-muted-foreground",
                )}
            >
                <Icon className="size-5" />
            </div>

            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-1.5">
                    <span className="truncate text-sm font-medium">
                        {template.name}
                    </span>

                    {template.is_default && (
                        <Badge variant="info">
                            <IconStar className="size-3" />
                            پیش‌فرض
                        </Badge>
                    )}
                </div>

                <p className="text-xs text-muted-foreground">
                    {meta?.label ?? template.format} ·{" "}
                    {new Intl.NumberFormat("fa-IR", {
                        maximumFractionDigits: 0,
                    }).format(template.size / 1024)}{" "}
                    کیلوبایت
                </p>
            </div>

            {canManage && (
                <div className="flex items-center gap-1 sm:opacity-0 sm:transition-opacity sm:group-focus-within:opacity-100 sm:group-hover:opacity-100">
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

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        title="جایگزینی فایل"
                        onClick={onReplace}
                        disabled={isPending}
                    >
                        <IconReplace className="size-4" />
                    </Button>

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
            description={
                format === "json"
                    ? "فایل JSON را انتخاب کنید. مقدارهای متنی را داخل گیومه بگذارید؛ {{content}} تنها استثناست و بدون گیومه می‌آید."
                    : `فایل ${current.label} را انتخاب کنید. اگر در آن {{content}} بنویسید، سند تولیدی همان‌جا نوشته می‌شود؛ اگر نه، خودِ فایل سند است و فقط نام‌ها پر می‌شوند.`
            }
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
                            <IconUpload className="size-4" />
                        )}
                        بارگذاری
                    </Button>
                </>
            }
        >
            <div className="grid gap-4">
                <FileUpload
                    // Remount on open so a cancelled file never reappears.
                    key={open ? "open" : "closed"}
                    accept={current.accept}
                    acceptedFileTypes={[
                        { label: current.label, icon: current.icon },
                    ]}
                    multiple={false}
                    title={`فایل ${current.label} را اینجا رها کنید`}
                    description={`فقط ${current.accept} — نام فایل در کلید ذخیره‌سازی می‌آید و تغییر نمی‌کند.`}
                    browseLabel="انتخاب فایل"
                    onFilesAccepted={(files) =>
                        setFile(files[0] ?? null)
                    }
                    onFilesChange={(items) => {
                        if (items.length === 0) {
                            setFile(null);
                        }
                    }}
                />

                {format === "json" && <JsonTemplateHint />}

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
                    نام فایلی که انتخاب می‌کنید در کلیدِ ذخیره‌سازی می‌آید و
                    هرگز تغییر نمی‌کند؛ نامی که اینجا می‌نویسید فقط برای نمایش
                    است.
                </p>
            </div>
        </ResponsiveDialog>
    );
}

/**
 * Replace the bytes of an existing template.
 *
 * A dialog of its own rather than a mode on the upload one: the upload asks for
 * a name and a default flag because it is creating a template, while this only
 * changes the file behind one that already has both. The warning matters — the
 * old bytes are overwritten and there is no version to go back to.
 */
function ReplaceTemplateDialog({
    template,
    onOpenChange,
}: {
    template: TemplateDto | null;
    onOpenChange: (open: boolean) => void;
}) {
    const activeFormat = template?.format ?? "docx";
    const replace = useReplaceTemplate(activeFormat);
    const current =
        FORMATS.find((option) => option.key === activeFormat) ?? FORMATS[0];

    const [file, setFile] = useState<File | null>(null);

    const close = (next: boolean) => {
        if (!next) {
            setFile(null);
        }

        onOpenChange(next);
    };

    const submit = () => {
        if (!template || !file) return;

        replace.mutate(
            { slug: template.slug, file },
            { onSuccess: () => close(false) },
        );
    };

    return (
        <ResponsiveDialog
            open={template !== null}
            onOpenChange={close}
            title="جایگزینی فایل قالب"
            description={
                template
                    ? `فایل جدید جایگزین «${template.name}» می‌شود؛ نام، پیش‌فرض و آدرس قالب عوض نمی‌شوند.`
                    : undefined
            }
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
                        disabled={!file || replace.isPending}
                        onClick={submit}
                    >
                        {replace.isPending ? (
                            <Spinner className="size-4" />
                        ) : (
                            <IconReplace className="size-4" />
                        )}
                        جایگزینی
                    </Button>
                </>
            }
        >
            <div className="grid gap-4">
                <FileUpload
                    // Remount per template so a file picked for one row never
                    // reappears when the dialog is opened for another.
                    key={template?.slug ?? "closed"}
                    accept={current.accept}
                    acceptedFileTypes={[
                        { label: current.label, icon: current.icon },
                    ]}
                    multiple={false}
                    title={`فایل ${current.label} تازه را اینجا رها کنید`}
                    description={`فقط ${current.accept} — فایل فعلی بازنویسی می‌شود.`}
                    browseLabel="انتخاب فایل"
                    onFilesAccepted={(files) =>
                        setFile(files[0] ?? null)
                    }
                    onFilesChange={(items) => {
                        if (items.length === 0) {
                            setFile(null);
                        }
                    }}
                />

                {activeFormat === "json" && <JsonTemplateHint />}

                <p className="text-xs text-muted-foreground">
                    فایل فعلی بازنویسی می‌شود و بازگشتی ندارد. اگر می‌خواهید
                    نسخهٔ قبلی بماند، به‌جای جایگزینی یک قالب تازه بارگذاری
                    کنید.
                </p>
            </div>
        </ResponsiveDialog>
    );
}
