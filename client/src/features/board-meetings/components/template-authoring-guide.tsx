import {
    IconAlertTriangle,
    IconBraces,
    IconCheck,
    IconInfoCircle,
    IconLayoutRows,
    IconList,
    IconNumber,
    IconSearch,
} from "@tabler/icons-react";
import { useMemo, useState, type ReactNode } from "react";

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { useWordTemplateFields } from "@/features/board-meetings/hooks/use-word-templates";
import {
    validateTemplate,
    type TemplateIssue,
    type TemplatePayload,
} from "@/features/board-meetings/lib/template-syntax";

/**
 * Everything a person needs to write a template, and nothing about meetings.
 *
 * THREE PIECES, IN THE ORDER THEY ARE NEEDED. You paste what you have and are told
 * what is wrong with it; then you are shown the four shapes once; then every
 * placeholder is in one list with its shape on it. The order is the argument — the
 * checker is useless until you know the shapes, and the catalogue is unreadable
 * until you have seen them.
 *
 * TWO OF THE THREE ARE DOMAIN-FREE. {@link SyntaxGuide} takes nothing but the two
 * brace strings, and {@link TemplateTester} reads the same payload the catalogue
 * does, so any domain on this template kernel gets all of it unchanged. That is the
 * point of the second one especially: it knows nothing about meetings, and would work
 * just as well on a domain nobody has written yet.
 *
 * THE OLD SCREEN LISTED FOUR THINGS. Every name appeared under its section AND under
 * the inline list AND again as syntax, so the page grew with the domain rather than
 * with what it had to say, and five groups each carried their own copy of the
 * paragraph explaining separators.
 */
export function TemplateAuthoringGuide() {
    const { data: fields } = useWordTemplateFields();

    if (!fields) {
        return null;
    }

    const payload: TemplatePayload = {
        syntax: fields.syntax,
        general: fields.general ?? [],
        inline: fields.inline ?? [],
        rows: fields.rows ?? [],
        blocks: fields.blocks ?? [],
        listable: fields.listable ?? [],
        dates: fields.dates ?? [],
    };

    return (
        <div className="grid gap-6">
            <TemplateTester payload={payload} syntax={fields.syntax} />
            <SyntaxGuide syntax={fields.syntax} />
            <PlaceholderCatalogue payload={payload} sections={fields.sections ?? []} />
        </div>
    );
}

/**
 * Paste a template; be told what is wrong with it.
 *
 * THE WHOLE POINT OF THIS SCREEN. A placeholder that names nothing is printed
 * exactly as typed — the right rule, because a typo has to be visible where the
 * author will look, which is the document. But "where the author will look" is the
 * document and NOT here, so a typo was found by exporting a meeting and reading the
 * printed macro in the minutes. Three rounds of this happened before the box existed.
 *
 * Nothing is sent anywhere. The names are already in memory and a template is text,
 * so there is no round trip to put between a keystroke and an answer — and no
 * meeting involved at all, which is what lets it be useful before any record exists.
 */
function TemplateTester({
    payload,
    syntax,
}: {
    payload: TemplatePayload;
    syntax: { open: string; close: string };
}) {
    const [text, setText] = useState("");

    const issues = useMemo(() => validateTemplate(text, payload), [text, payload]);
    const { open, close } = syntax;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <IconCheck className="flex size-5 text-emerald-600" />
                    قالب خود را اینجا بچسبانید
                </CardTitle>
                <CardDescription>
                    هرچه بنویسید همین‌جا بررسی می‌شود — بدون اینکه چیزی ذخیره شود و
                    بدون اینکه به جلسه‌ای نیاز باشد. نامی که شناخته نشود قرمز می‌شود.
                </CardDescription>
            </CardHeader>

            <CardContent className="grid gap-3">
                <Textarea
                    value={text}
                    onChange={(event) => setText(event.target.value)}
                    dir="ltr"
                    rows={7}
                    className="font-mono text-xs"
                    placeholder={`مثلا:\n${open}held_on${close}  /  ${open}held_on@jalali${close}\n${open}#attendee${close}\n${open}attendee.name${close}\n${open}/attendee${close}\n${open}@|tag.name|& ${close}`}
                />

                {text.trim() === "" ? (
                    <p className="text-xs text-muted-foreground">
                        خالی است — هرچه بنویسید بررسی می‌شود.
                    </p>
                ) : issues.length === 0 ? (
                    <p className="flex items-center gap-1.5 text-sm text-emerald-700">
                        <IconCheck className="size-4" />
                        همهٔ نام‌ها شناخته شدند.
                    </p>
                ) : (
                    <IssueList issues={issues} open={open} close={close} />
                )}
            </CardContent>
        </Card>
    );
}

function IssueList({
    issues,
    open,
    close,
}: {
    issues: TemplateIssue[];
    open: string;
    close: string;
}) {
    return (
        <ul className="grid gap-2">
            {issues.map((issue) => (
                <li
                    key={issue.macro + issue.problem}
                    className="rounded-lg border border-destructive/30 bg-destructive/5 p-2.5"
                >
                    <div className="flex flex-wrap items-center gap-2">
                        <IconAlertTriangle className="size-4 text-destructive" />

                        <code dir="ltr" className="text-xs font-medium">
                            {open}
                            {issue.macro}
                            {close}
                        </code>
                    </div>

                    <p className="mt-1 text-xs">{issue.problem}</p>

                    {issue.suggestion && (
                        <p className="mt-1 text-xs text-muted-foreground">
                            شاید:{" "}
                            <code dir="ltr" className="font-medium">
                                {issue.suggestion}
                            </code>
                        </p>
                    )}
                </li>
            ))}
        </ul>
    );
}

/**
 * The four shapes, once, and about no domain in particular.
 *
 * TAKES NOTHING BUT THE TWO BRACE STRINGS, which is the whole point: this renders
 * unchanged for any domain on the template kernel, including one nobody has written
 * yet. A guide that needed to know about meetings could not be that, and every
 * future domain would need its own copy of the same four paragraphs.
 */
function SyntaxGuide({ syntax }: { syntax: { open: string; close: string } }) {
    const { open, close } = syntax;

    const value = `${open}attendee.name${close}`;
    const group = `${open}attendee${close}`;
    const groupEnd = `${open}/attendee${close}`;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <IconInfoCircle className="size-5" />
                    چهار شکل، با یک نشانه
                </CardTitle>
                <CardDescription>
                    همه از یک نشانه می‌آیند. آنچه میان دو نشانه بنویسید به تعداد
                    رکورد تکرار می‌شود، و شکل خروجی از همان بستگی دارد — نه از خودِ
                    نشانه.
                </CardDescription>
            </CardHeader>

            <CardContent className="grid gap-3">
                <Shape
                    icon={<IconLayoutRows className="size-4" />}
                    title="سطر جدول"
                    note="نشانه‌ها داخل یک سطر جدول بنشینند؛ آن سطر به تعداد رکورد تکرار می‌شود."
                    lines={[group, value, groupEnd]}
                />

                <Shape
                    icon={<IconList className="size-4" />}
                    title="فهرست گلوله‌ای"
                    note="یک بند فهرست‌دار در Word بنویسید و آن را میان دو نشانه بگذارید؛ گلوله را خود Word می‌کشد."
                    lines={[group, value, groupEnd]}
                />

                <Shape
                    icon={<IconNumber className="size-4" />}
                    title="فهرست شماره‌دار"
                    note="مانند قبلی، با بند شماره‌دار. شماره‌ها را Word می‌گذارد، پس با حذف یک بند خودش جابه‌جا می‌شوند."
                    lines={[group, value, groupEnd]}
                />

                <Shape
                    icon={<IconBraces className="size-4" />}
                    title="درون یک جمله"
                    note="وقتی فهرست داخل جمله می‌خواهید، نشانه‌ها را بردارید و جداکننده را بعد از خط عمودی بگذارید."
                    lines={[`${open}attendee.name|؛ ${close}`]}
                />

                <Shape
                    icon={<IconBraces className="size-4" />}
                    title="با نشانه‌گذاری دور هر مورد"
                    note="جداکننده میانِ موارد می‌نشیند و نمی‌تواند بگوید یک مورد کجا تمام شد — این شکل هر مورد را جدا می‌کند."
                    lines={[`${open}@|tag.name|& ${close}   →   @بودجه& @مالی&`]}
                />
            </CardContent>
        </Card>
    );
}

function Shape({
    icon,
    title,
    note,
    lines,
}: {
    icon: ReactNode;
    title: string;
    note: string;
    lines: string[];
}) {
    return (
        <div className="grid gap-2 rounded-lg border p-3 sm:grid-cols-[11rem_1fr]">
            <div className="flex flex-col gap-1">
                <span className="flex items-center gap-1.5 text-sm font-medium">
                    {icon}
                    {title}
                </span>
                <p className="text-xs text-muted-foreground">{note}</p>
            </div>

            <div
                dir="ltr"
                className="grid content-start gap-1 self-center rounded-md bg-muted px-2 py-1.5 text-xs"
            >
                {lines.map((line) => (
                    <div key={line} className="font-mono">
                        {line}
                    </div>
                ))}
            </div>
        </div>
    );
}

/**
 * Every placeholder, once, with what it can do attached to it.
 *
 * ONE LIST, NOT FOUR. The old screen showed the same names under their section, under
 * the inline list, and again as syntax — so it grew with the domain rather than with
 * what it had to say, and every group repeated the same paragraph about separators.
 * Here each name appears exactly once, and its SHAPE is a badge on it.
 */
function PlaceholderCatalogue({
    payload,
    sections,
}: {
    payload: TemplatePayload;
    sections: { key: string; label: string; fields: string[] }[];
}) {
    const [filter, setFilter] = useState("");
    const [shape, setShape] = useState<"all" | "single" | "repeat" | "list">("all");

    const rows = useMemo(() => buildCatalogue(payload, sections), [payload, sections]);

    const shown = rows.filter((row) => {
        if (shape !== "all" && row.kind !== shape) {
            return false;
        }

        const needle = filter.trim().toLowerCase();

        return (
            needle === "" ||
            row.key.toLowerCase().includes(needle) ||
            row.label.toLowerCase().includes(needle)
        );
    });

    const counts = {
        all: rows.length,
        single: rows.filter((r) => r.kind === "single").length,
        repeat: rows.filter((r) => r.kind === "repeat").length,
        list: rows.filter((r) => r.kind === "list").length,
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <IconBraces className="size-5" />
                    همهٔ نام‌ها، یک‌جا
                </CardTitle>
                <CardDescription>
                    هر نام یک بار، و کنارش آنچه می‌تواند بکند. نامی که در فایل نباشد
                    دست‌نخورده چاپ می‌شود، پس غلط املایی در خروجی می‌بینید — و در
                    جعبهٔ بالا زودتر پیدایش می‌کنید.
                </CardDescription>
            </CardHeader>

            <CardContent className="grid gap-3">
                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative">
                        <IconSearch className="absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={filter}
                            onChange={(event) => setFilter(event.target.value)}
                            className="w-56 pl-8"
                            placeholder="جست‌وجوی نام یا برچسب"
                        />
                    </div>

                    {(
                        [
                            ["all", "همه"],
                            ["single", "تکی"],
                            ["repeat", "تکرارشونده"],
                            ["list", "فهرست"],
                        ] as const
                    ).map(([key, label]) => (
                        <Buttonish
                            key={key}
                            active={shape === key}
                            onClick={() => setShape(key)}
                        >
                            {label}
                            <span className="text-xs text-muted-foreground">
                                {counts[key]}
                            </span>
                        </Buttonish>
                    ))}
                </div>

                <ul className="grid gap-1.5">
                    {shown.map((row) => (
                        <li
                            key={row.key}
                            className="flex flex-wrap items-center gap-2 rounded-md bg-muted px-2 py-1.5 text-xs"
                        >
                            <code dir="ltr" className="font-medium">
                                {row.key}
                            </code>

                            <span className="text-muted-foreground">
                                {row.label}
                            </span>

                            <Badge variant={badgeVariant(row.kind)}>
                                {row.kind === "single" && "تکی"}
                                {row.kind === "repeat" && "تکرارشونده"}
                                {row.kind === "list" && "فهرست"}
                            </Badge>

                            {row.isDate && (
                                <Badge variant="outline" dir="ltr">
                                    {"@jalali"}
                                </Badge>
                            )}

                            {row.section && (
                                <Badge variant="outline" className="text-[10px]">
                                    {row.section}
                                </Badge>
                            )}
                        </li>
                    ))}

                    {shown.length === 0 && (
                        <li className="py-6 text-center text-sm text-muted-foreground">
                            نامی با این نشانه پیدا نشد.
                        </li>
                    )}
                </ul>
            </CardContent>
        </Card>
    );
}

type CatalogueRow = {
    key: string;
    label: string;
    kind: "single" | "repeat" | "list";
    isDate: boolean;
    section: string | null;
};

function buildCatalogue(
    payload: TemplatePayload,
    sections: { key: string; label: string; fields: string[] }[],
): CatalogueRow[] {
    const labels = new Map<string, string>();

    for (const field of [...payload.inline, ...payload.general]) {
        labels.set(field.key, field.label);
    }

    for (const row of payload.rows) {
        for (const column of row.columns) {
            labels.set(column.key, column.label);
        }
    }

    // WHICH SECTION A NAME BELONGS TO, looked up once and carried as a badge. The old
    // screen answered "where does this show up" by listing every name again under its
    // section, which is the repetition this catalogue exists to remove.
    const sectionOf = new Map<string, string>();

    for (const section of sections) {
        for (const key of section.fields) {
            if (!sectionOf.has(key)) {
                sectionOf.set(key, section.label);
            }
        }
    }

    const rows: CatalogueRow[] = [];
    const seen = new Set<string>();

    const push = (key: string, kind: CatalogueRow["kind"]) => {
        if (seen.has(key)) {
            return;
        }

        seen.add(key);

        rows.push({
            key,
            label: labels.get(key) ?? "",
            kind,
            isDate: payload.dates.includes(key),
            section: sectionOf.get(key) ?? null,
        });
    };

    for (const row of payload.rows) {
        for (const column of row.columns) {
            push(column.key, "repeat");
        }
    }

    for (const key of payload.listable) {
        push(key, "list");
    }

    for (const field of payload.general) {
        push(field.key, "single");
    }

    for (const field of payload.inline) {
        push(field.key, "single");
    }

    return rows;
}

function badgeVariant(kind: CatalogueRow["kind"]) {
    if (kind === "repeat") return "info" as const;
    if (kind === "list") return "warning" as const;

    return "outline" as const;
}

function Buttonish({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={`inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs transition-colors ${
                active
                    ? "border-primary bg-primary/10 font-medium"
                    : "border-border hover:bg-muted"
            }`}
        >
            {children}
        </button>
    );
}