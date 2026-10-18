import { useRef, useState } from "react";
import {
    IconFileText,
    IconInfoCircle,
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
import { usePermission } from "@/features/auth/components/permission-guard";
import {
    useDeleteWordTemplate,
    useUpdateWordTemplate,
    useUploadWordTemplate,
    useWordTemplateFields,
    useWordTemplates,
} from "@/features/board-meetings/hooks/use-word-templates";
import type { WordTemplateDto } from "@/features/board-meetings/word-templates-api";

/**
 * The Word templates a meeting can be exported with, and the names one may use.
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
 */
export function WordTemplateSettingsSection() {
    const canManage = usePermission(["export-template.manage"]);

    const { data: templates, isLoading, isFetching, refetch } =
        useWordTemplates();

    const update = useUpdateWordTemplate();
    const remove = useDeleteWordTemplate();

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
                        قالب‌های Word
                    </CardTitle>
                    <CardDescription>
                        فایلی که خروجی Word از آن ساخته می‌شود. قالبی که
                        <span className="mx-1 font-medium">متنی</span> باشد خودش
                        تصمیم می‌گیرد چه چاپ شود؛ قالبی که فقط{" "}
                        <span className="mx-1 font-medium">ظاهر</span> باشد
                        سربرگ و قلم و اندازهٔ صفحه را می‌دهد و محتوا زیر آن ساخته
                        می‌شود.
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
                            هنوز قالبی بارگذاری نشده است. بدون قالب، خروجی Word
                            همان سند تولیدی همیشگی است.
                        </p>
                    )}
                </CardContent>
            </Card>

            <PlaceholderCheatSheet />

            <UploadWordTemplateDialog
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
    template: WordTemplateDto;
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

                    {template.is_default && (
                        <Badge variant="info">
                            <IconStar className="size-3" />
                            پیش‌فرض
                        </Badge>
                    )}

                    {/* The fact that decides what the file is FOR, shown before
                        anyone picks it rather than discovered at export time. */}
                    <Badge
                        variant={
                            template.kind === "content" ? "secondary" : "outline"
                        }
                    >
                        {template.kind === "content"
                            ? "متنی"
                            : "ظاهر"}
                    </Badge>
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

/**
 * Every name a template may refer to, and the two shapes they come in.
 */
function PlaceholderCheatSheet() {
    const { data: fields } = useWordTemplateFields();

    if (!fields) {
        return null;
    }

    const syntax = `${fields.syntax.open}…${fields.syntax.close}`;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <IconInfoCircle className="size-5" />
                    نام‌های در دسترس در قالب
                </CardTitle>
                <CardDescription>
                    هر نام را داخل دو کروشه در فایل بنویسید، مثل{" "}
                    <span dir="ltr" className="font-medium">
                        {fields.syntax.open}
                        title
                        {fields.syntax.close}
                    </span>
                    . نامی که در فایل نباشد دست‌نخورده چاپ می‌شود، پس غلط املایی
                    را در خروجی می‌بینید.
                </CardDescription>
            </CardHeader>

            <CardContent className="grid gap-5">
                <div className="grid gap-2">
                    <h3 className="text-sm font-medium">نام‌های متنی</h3>
                    <NameList items={fields.inline} />
                </div>

                <div className="grid gap-3">
                    <h3 className="text-sm font-medium">نام‌های سطری</h3>

                    <p className="text-xs text-muted-foreground">
                        هر گروه را داخل <span dir="ltr">یک سطرِ جدول</span>{" "}
                        بگذارید؛ آن سطر به تعداد رکورد تکرار می‌شود و هر کپی
                        مقدار خودش را می‌گیرد. بیرون از جدول بنویسیدشان، همان متن
                        چاپ می‌شود.
                    </p>

                    <ul className="grid gap-3">
                        {fields.rows.map((group) => (
                            <li
                                key={group.group}
                                className="rounded-lg border p-3"
                            >
                                <div className="mb-2 flex items-center justify-between gap-2">
                                    <span
                                        dir="ltr"
                                        className="text-xs text-muted-foreground"
                                    >
                                        {syntax.split("…")[0]}
                                        {group.group}
                                        .
                                        {syntax.split("…")[1]}
                                    </span>
                                    <Badge variant="outline">سطر تکرارشونده</Badge>
                                </div>

                                <NameList items={group.columns} />
                            </li>
                        ))}
                    </ul>
                </div>
            </CardContent>
        </Card>
    );
}

function NameList({
    items,
}: {
    items: { key: string; label: string }[];
}) {
    return (
        <ul className="flex flex-wrap gap-1.5">
            {items.map((item) => (
                <li
                    key={item.key}
                    className="flex items-center gap-1.5 rounded-md bg-muted px-2 py-1 text-xs"
                >
                    <code dir="ltr" className="font-medium">
                        {item.key}
                    </code>
                    <span className="text-muted-foreground">{item.label}</span>
                </li>
            ))}
        </ul>
    );
}

function UploadWordTemplateDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const upload = useUploadWordTemplate();
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
            title="بارگذاری قالب Word"
            description="یک فایل .docx بدون ماکرو. اگر در آن نامی بنویسید، خودِ فایل تصمیم می‌گیرد چه چاپ شود؛ اگر نگویسید، فقط ظاهر و صفحه‌بندی را می‌دهد."
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
                    <Label htmlFor="word-template-file">فایل</Label>
                    <Input
                        id="word-template-file"
                        type="file"
                        ref={input}
                        accept=".docx"
                    />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="word-template-name">نام قالب</Label>
                    <Input
                        id="word-template-name"
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                        placeholder={file ? file.name : "مثلاً نامهٔ رسمی جلسه"}
                    />
                </div>

                <div className="flex items-center gap-2">
                    <Checkbox
                        id="word-template-default"
                        checked={isDefault}
                        onCheckedChange={(checked) =>
                            setIsDefault(checked === true)
                        }
                    />
                    <Label htmlFor="word-template-default">
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
