import {
    IconBraces,
    IconInfoCircle,
    IconLayoutRows,
    IconList,
    IconNumber,
} from "@tabler/icons-react";
import type { ReactNode } from "react";

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { useWordTemplateFields } from "@/features/board-meetings/hooks/use-word-templates";
import type {
    WordTemplateFieldDto,
    WordTemplateFieldsResponse,
} from "@/features/board-meetings/word-templates-api";

type Fields = WordTemplateFieldsResponse["data"];

/**
 * Every shape a meeting's values can be printed in, and one card per group.
 *
 * REBUILT FROM FOUR LISTS INTO ONE CARD PER GROUP. The old screen showed the same
 * names three times over — once under the section they appear in, once under the
 * inline list, and once as syntax — so the page grew with the domain rather than with
 * what it had to say. A card per group says everything about that group in one place
 * and names nothing twice.
 *
 * THE FOUR SHAPES ARE EXPLAINED ONCE, HERE, rather than repeated inside each group.
 * That was the other half of the repetition: every group used to carry its own copy of
 * the paragraph explaining separators, and five groups meant five copies of prose that
 * could each drift out of date separately.
 *
 * THE SHAPES ARE NOT FOUR SYNTAXES. There is one marker, `{{#group}} … {{/group}}`,
 * and the OUTPUT SHAPE FALLS OUT OF WHAT IS INSIDE IT: a table row repeats, a
 * bulleted paragraph repeats, a numbered paragraph repeats. The examples below are
 * the same three lines written four ways, and the only difference between them is the
 * paragraph they sit in — which is the point an author has to be shown rather than
 * told.
 *
 * NO SAMPLE DATA. The examples use obvious stand-ins («نام حاضر») rather than a real
 * meeting, so nobody's minutes appear on a settings screen and the examples cannot go
 * stale when a meeting changes.
 */
export function TemplateShapeCheatSheet() {
    const { data: fields } = useWordTemplateFields();

    if (!fields) {
        return null;
    }

    const repeating = fields.rows ?? [];
    const listable = new Set(fields.listable ?? []);

    // The single values are what is left once the groups and the lists have taken
    // their names. Listing only the remainder is what stops a name appearing twice.
    const repeated = new Set<string>([
        ...repeating.flatMap((group) => group.columns.map((c) => c.key)),
        ...listable,
    ]);

    const singles = [
        ...fields.general.map((field) => ({ ...field, section: null })),
        ...fields.inline
            .filter((field) => !repeated.has(field.key))
            .map((field) => ({
                key: field.key,
                label: field.label,
                section:
                    fields.sections.find((section) =>
                        section.fields.includes(field.key),
                    )?.label ?? null,
            })),
    ];

    return (
        <div className="grid gap-6">
            <ShapesCard fields={fields} />

            {repeating.map((group) => {
                const block = fields.blocks.find(
                    (candidate) => candidate.group === group.group,
                );

                return (
                    <GroupCard
                        key={group.group}
                        marker={block ? `${block.open} … ${block.close}` : null}
                        title={group.label}
                        syntax={fields.syntax}
                        columns={group.columns}
                    />
                );
            })}

            {listable.size > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <IconList className="size-5" />
                            فهرست‌ها — چند مقدار در یک نام
                        </CardTitle>
                        <CardDescription>
                            این نام‌ها چند مقدار دارند، پس دو شکل دارند: در جمله با
                            جداکننده، و بندی بندی با بلوک.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="grid gap-3">
                        {fields.inline
                            .filter((field) => listable.has(field.key))
                            .map((field) => {
                                const block = fields.blocks.find(
                                    (candidate) =>
                                        candidate.group === field.key,
                                );

                                return (
                                    <SingleShape
                                        key={field.key}
                                        field={field}
                                        marker={
                                            block
                                                ? `${block.open} … ${block.close}`
                                                : null
                                        }
                                    />
                                );
                            })}
                    </CardContent>
                </Card>
            )}

            {singles.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <IconBraces className="size-5" />
                            نام‌های تکی
                        </CardTitle>
                        <CardDescription>
                            هر نام را داخل دو کروشه بنویسید. نامی که در فایل نباشد
                            دست‌نخورده چاپ می‌شود، پس غلط املایی را در خروجی می‌بینید.
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <NameList
                            items={singles}
                            footnote={
                                <>
                                    نشانگر{" "}
                                    <code dir="ltr" className="font-medium">
                                        {"{{content}}"}
                                    </code>{" "}
                                    تنها نامی است که سند تولیدی را در خود جای
                                    می‌دهد؛ بدون آن، فایل خودش سند است.
                                </>
                            }
                        />
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

/**
 * The four shapes, once.
 *
 * EXPLAINED HERE RATHER THAN PER GROUP, and every example is the SAME range with the
 * same name in it. The only thing that changes is the paragraph the block sits in —
 * which is the single fact that makes one syntax able to produce a table, a bullet
 * list and a numbered list.
 */
function ShapesCard({ fields }: { fields: Fields }) {
    const { open, close } = fields.syntax;

    const range = `${open}attendee${close}`;
    const end = `${open}/attendee${close}`;
    const value = `${open}attendee.name${close}`;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <IconInfoCircle className="size-5" />
                    چهار شکل، با یک نشانه
                </CardTitle>
                <CardDescription>
                    برای هر گروه تکرارشونده دو نشانه می‌نویسید:{" "}
                    <code dir="ltr" className="font-medium">
                        {range}
                    </code>{" "}
                    و{" "}
                    <code dir="ltr" className="font-medium">
                        {end}
                    </code>
                    . آنچه میانشان باشد به تعداد رکورد کپی می‌شود — و شکل خروجی از
                    همین بستگی دارد، نه از نشانه.
                </CardDescription>
            </CardHeader>

            <CardContent className="grid gap-3">
                <Shape
                    icon={<IconLayoutRows className="size-4" />}
                    title="سطر جدول"
                    note="نشانه‌ها داخل یک سطر جدول بنشینند؛ آن سطر به تعداد رکورد تکرار می‌شود."
                >
                    <Sample>{range}</Sample>
                    <Sample>{`${open}attendee.name${close}`}</Sample>
                    <Sample>{end}</Sample>
                </Shape>

                <Shape
                    icon={<IconList className="size-4" />}
                    title="فهرست گلوله‌ای"
                    note="یک بند فهرست‌دار در Word بنویسید و آن را میان دو نشانه بگذارید؛ گلوله را خود Word می‌کشد."
                >
                    <Sample>{range}</Sample>
                    <Sample>{value}</Sample>
                    <Sample>{end}</Sample>
                </Shape>

                <Shape
                    icon={<IconNumber className="size-4" />}
                    title="فهرست شماره‌دار"
                    note="مانند قبلی، با بند شماره‌دار. شماره‌ها را Word می‌گذارد، پس با حذف یک بند خودش جابه‌جا می‌شوند."
                >
                    <Sample>{range}</Sample>
                    <Sample>{value}</Sample>
                    <Sample>{end}</Sample>
                </Shape>

                <Shape
                    icon={<IconBraces className="size-4" />}
                    title="درون یک جمله"
                    note="وقتی فهرست را داخل جمله می‌خواهید، نشانه‌ها را بردارید و جداکننده را بعد از خط عمودی بگذارید."
                >
                    <Sample>{`حاضران: ${open}attendee.name|؛ ${close}`}</Sample>
                </Shape>
            </CardContent>
        </Card>
    );
}

function Shape({
    icon,
    title,
    note,
    children,
}: {
    icon: ReactNode;
    title: string;
    note: string;
    children: ReactNode;
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
                {children}
            </div>
        </div>
    );
}

function Sample({ children }: { children: string }) {
    return <div className="font-mono">{children}</div>;
}

/** One repeating group, its names, and nothing about any other group. */
function GroupCard({
    title,
    marker,
    columns,
}: {
    title: string;
    marker: string | null;
    columns: WordTemplateFieldDto[];
    syntax: Fields["syntax"];
}) {
    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <CardTitle>{title}</CardTitle>

                    {marker && (
                        <code
                            dir="ltr"
                            className="rounded-md bg-muted px-2 py-1 text-xs"
                        >
                            {marker}
                        </code>
                    )}
                </div>

                <CardDescription>
                    هر سطر یا بندی که میان دو نشانه بنویسید، برای هر{" "}
                    {title === "حاضران" ? "حاضر" : "رکورد"} یک بار ساخته می‌شود.
                </CardDescription>
            </CardHeader>

            <CardContent>
                <NameList items={columns} />
            </CardContent>
        </Card>
    );
}

/** A listable list, which is the one shape with two different spellings. */
function SingleShape({
    field,
    marker,
}: {
    field: WordTemplateFieldDto;
    marker: string | null;
}) {
    return (
        <div className="grid gap-2 rounded-lg border p-3">
            <div className="flex flex-wrap items-center gap-2">
                <code dir="ltr" className="text-sm font-medium">
                    {field.key}
                </code>
                <span className="text-xs text-muted-foreground">
                    {field.label}
                </span>
            </div>

            <div
                dir="ltr"
                className="grid content-start gap-1 rounded-md bg-muted px-2 py-1.5 text-xs"
            >
                <div className="font-mono">
                    {`در جمله: ${field.key}|؛ `}
                </div>

                {marker && (
                    <>
                        <div className="font-mono">{marker}</div>
                        <div className="font-mono">{field.key}</div>
                    </>
                )}
            </div>
        </div>
    );
}

/**
 * The names, each with its label and — for a single value — the section it appears in.
 *
 * THE SECTION IS A BADGE ON THE NAME rather than a second list of names. "Where does
 * this show up" is a real question when laying a template out, and the old answer was
 * to list every name again under its section, which is exactly the repetition this
 * screen was rebuilt to remove.
 */
function NameList({
    items,
    footnote,
}: {
    items: { key: string; label: string; section?: string | null }[];
    footnote?: ReactNode;
}) {
    return (
        <div className="grid gap-3">
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

                        {item.section && (
                            <Badge variant="outline" className="text-[10px]">
                                {item.section}
                            </Badge>
                        )}
                    </li>
                ))}
            </ul>

            {footnote && (
                <p className="text-xs text-muted-foreground">{footnote}</p>
            )}
        </div>
    );
}