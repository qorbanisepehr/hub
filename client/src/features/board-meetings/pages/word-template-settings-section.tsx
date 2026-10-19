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

/**
 * Every name a template may refer to, and the shapes they come in.
 *
 * FOUR LISTS, because a template has four ways of asking and a cheat sheet that
 * shows one teaches three of them by omission: the general names every domain has,
 * the meeting's own names grouped by the section they appear in, the repeating rows,
 * and the joined form for prose.
 */
function PlaceholderCheatSheet() {
    const { data: fields } = useWordTemplateFields();

    if (!fields) {
        return null;
    }

    const syntax = `${fields.syntax.open}…${fields.syntax.close}`;

    const listable = fields.listable ?? [];
    const blocks = fields.blocks ?? [];

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
                {/* The general names first, because they are the ones that work on
                    every domain: a template written once should not stop being
                    correct when it is pointed at something else. */}
                <div className="grid gap-2">
                    <h3 className="text-sm font-medium">
                        نام‌های عمومی — در همهٔ حوزه‌ها کار می‌کنند
                    </h3>

                    <NameList items={fields.general ?? []} />

                    <p className="text-xs text-muted-foreground">
                        نشانگر{" "}
                        <span dir="ltr" className="font-medium">
                            {"{{content}}"}
                        </span>{" "}
                        تنها نامی است که سند تولیدی را در خود جای می‌دهد؛ بدون آن،
                        فایل خودش سند است.
                    </p>
                </div>

                {/* Grouped by section, because laying a template out is deciding
                    what goes where, and a flat list of twenty-two keys does not
                    answer that. */}
                <div className="grid gap-3">
                    <h3 className="text-sm font-medium">
                        نام‌های جلسه — بر پایهٔ بخشی که در آن می‌آیند
                    </h3>

                    <ul className="grid gap-3">
                        {(fields.sections ?? []).map((section) => (
                            <li key={section.key} className="rounded-lg border p-3">
                                <div className="mb-2 flex items-center justify-between gap-2">
                                    <span className="text-sm font-medium">
                                        {section.label}
                                    </span>
                                    <span
                                        dir="ltr"
                                        className="text-xs text-muted-foreground"
                                    >
                                        {section.key}
                                    </span>
                                </div>

                                <NameList
                                    listable={listable}
                                    items={section.fields.map((key) => ({
                                        key,
                                        label:
                                            fields.inline.find(
                                                (field) => field.key === key,
                                            )?.label ?? "",
                                    }))}
                                />
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="grid gap-2">
                    <h3 className="text-sm font-medium">نام‌های متنی</h3>
                    <NameList items={fields.inline} listable={listable} />

                    {listable.length > 0 && (
                        <p className="text-xs text-muted-foreground">
                            نشان{" "}
                            <code dir="ltr" className="font-medium text-primary">
                                |؛
                            </code>{" "}
                            یعنی این نام فهرستی است و جداکنندهٔ خودتان را می‌پذیرد:{" "}
                            <code dir="ltr" className="font-medium">
                                {"{{summary.topics|؛ }}"}
                            </code>
                            . فاصله‌ها بخشی از جداکننده‌اند.
                        </p>
                    )}
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

                                {/* The third shape, and the one a table cannot
                                    produce: a whole list inside a sentence. */}
                                <p className="mt-3 text-xs text-muted-foreground">
                                    برای نوشتنِ کلِ فهرست در یک جمله، جداکننده را
                                    بعد از خط عمودی بگذارید؛ فاصله‌ها بخشی از آن
                                    است:{" "}
                                    <span dir="ltr" className="font-medium">
                                        {"{{attendee.name|؛ }}"}
                                    </span>{" "}
                                    ← «الف؛ ب؛ ج». بدون جداکننده، ویرگول فارسی
                                    به کار می‌رود.
                                </p>
                            </li>
                        ))}
                    </ul>
                </div>

                {/* The bullet list, and the shape none of the others reach. A row
                    repeats a TABLE ROW and a range repeats a BLOCK, which in Word
                    means one paragraph per record — the only way to get a real list
                    outside a table. A separator cannot do it: it joins a list into a
                    sentence, and several items in one paragraph wrap into one line. */}
                {blocks.length > 0 && (
                    <div className="grid gap-3">
                        <h3 className="text-sm font-medium">
                            فهرست گلوله‌ای — یک بند، به تعداد رکورد
                        </h3>

                        <p className="text-xs text-muted-foreground">
                            دو نشانه، هر کدام در سطر خودش. آنچه میانشان باشد برای
                            هر رکورد یک بار کپی می‌شود؛ در Word هر کپی بندهای
                            خودش را دارد، پس هر رکورد یک سطر جدا می‌افتد. اگر
                            رکوردی نباشد، بند کاملاً حذف می‌شود.
                        </p>

                        <ul className="grid gap-2">
                            {blocks.map((block) => (
                                <li
                                    key={block.group}
                                    className="rounded-lg border p-3"
                                >
                                    <div
                                        dir="ltr"
                                        className="mb-2 whitespace-pre rounded-md bg-muted px-2 py-1.5 text-xs"
                                    >
                                        {block.open}
                                        {"\n  • "}
                                        {`{{${block.group}.column}}`}
                                        {"\n"}
                                        {block.close}
                                    </div>

                                    <span className="text-xs text-muted-foreground">
                                        {(fields.rows.find(
                                            (row) => row.group === block.group,
                                        )?.columns ?? [])
                                            .map((column) => column.key)
                                            .join("، ")}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

/**
 * Stable empty, because a default prop that is an array literal is rebuilt on every
 * render and makes the list compare unequal to itself.
 */
const NO_LISTABLE: string[] = [];

/**
 * The names, each with its Persian label.
 *
 * `listable` names are marked, because they are the ones that accept a separator of
 * the author's own. It is marked HERE rather than written into the section prose
 * because it is a property of the name, and a name appears in two places on this
 * screen — under its section and under the inline list — so a note attached to one
 * would leave the other quietly wrong.
 */
function NameList({
    items,
    listable = NO_LISTABLE,
}: {
    items: { key: string; label: string }[];
    listable?: string[];
}) {
    const separators = new Set(listable);

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

                    {/* The separator goes here and nowhere else: the author cannot
                        type one, because the closing brace has to come after it and
                        that is exactly what makes it ambiguous. */}
                    {separators.has(item.key) && (
                        <code
                            dir="ltr"
                            title="جداکنندهٔ دلخواه"
                            className="text-[10px] text-primary"
                        >
                            |؛
                        </code>
                    )}

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
                    <Label htmlFor="word-template-file">فایل</Label>
                    <Input
                        id="word-template-file"
                        type="file"
                        ref={input}
                        accept=".docx,.md,.json"
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
