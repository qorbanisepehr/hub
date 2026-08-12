import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import {
    FormTextField,
    FormSelectField,
    FormDatePicker,
} from "@/components/shared/form-fields";
import { FormOptionSelectField } from "@/components/shared/form-option-fields";
import { FileUploadField } from "@/components/shared/file-upload-field";
import { repeaterAttachmentColumn } from "@/components/shared/repeater-attachment-cell";
import { FormRepeater } from "@/components/shared/form-repeater";
import type { TableColumn } from "@/components/shared/form-repeater";
import {
    LANGUAGE_LEVEL_OPTIONS,
    SOFTWARE_LEVEL_OPTIONS,
    DOC_CATEGORY_SLUGS,
} from "@/features/questionnaire/constants";
import { useFormOptionsByGroup } from "@/features/form-options/hooks/use-form-options";
import { useEntityDocuments } from "@/hooks/use-entity-documents";
import { zodFieldValidators } from "@/lib/validation-helpers";
import { fieldSchemas } from "@/features/questionnaire/schemas/skills.schema";
import type { QuestionnaireFormApi } from "@/features/questionnaire/types";

type SectionProps = {
    form: QuestionnaireFormApi;
    uuid?: string;
    onPersist?: () => void;
    /** Grant entity the section's documents belong to. Defaults to "questionnaire". */
    entity?: string;
};

const LANGUAGE_COLUMNS: TableColumn[] = [
    { key: "language", label: "زبان" },
    { key: "reading", label: "خواندن" },
    { key: "writing", label: "نوشتن" },
    { key: "speaking", label: "صحبت کردن" },
    { key: "comprehension", label: "درک مطلب" },
];

const SOFTWARE_COLUMNS: TableColumn[] = [
    { key: "name", label: "نرم‌افزار" },
    {
        key: "level",
        label: "سطح مهارت",
        render: (v: unknown) => {
            const labels: Record<string, string> = {
                "1": "مبتدی",
                "2": "متوسط",
                "3": "خوب",
                "4": "عالی",
            };
            return labels[String(v)] ?? String(v ?? "—");
        },
    },
];

const CERTIFICATE_COLUMNS: TableColumn[] = [
    { key: "title", label: "عنوان" },
    { key: "expire_at", label: "تاریخ انقضا", type: "date" },
];

function SoftwareItem({
    form,
    index,
    prefix,
}: {
    form: QuestionnaireFormApi;
    index: number;
    prefix: string;
}) {
    return (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <form.Field
                name={`${prefix}.${index}.name`}
                validators={zodFieldValidators(
                    fieldSchemas.software_skill_name,
                )}
            >
                {(f) => <FormTextField field={f} label="نام نرم‌افزار" />}
            </form.Field>
            <form.Field name={`${prefix}.${index}.level`}>
                {(f) => (
                    <FormSelectField
                        field={f}
                        label="سطح مهارت"
                        options={SOFTWARE_LEVEL_OPTIONS}
                    />
                )}
            </form.Field>
        </div>
    );
}

export function SkillsSection({ form, uuid, onPersist, entity = "questionnaire" }: SectionProps) {
    const { getDocumentsBySlug } = useEntityDocuments(entity, uuid);

    const { data: languageOptions } = useFormOptionsByGroup("language");
    const languageLabel = (value: string | undefined) =>
        languageOptions?.find((option) => option.value === value)?.label ??
        value;

    const languageColumns: TableColumn[] = [
        ...LANGUAGE_COLUMNS,
        repeaterAttachmentColumn({
            categorySlug: DOC_CATEGORY_SLUGS.LANGUAGE_CERTIFICATE,
            recordKeyPrefix: "lang-",
            getDocumentsBySlug,
        }),
    ];
    const specializedColumns: TableColumn[] = [
        ...SOFTWARE_COLUMNS,
        repeaterAttachmentColumn({
            categorySlug: DOC_CATEGORY_SLUGS.SKILL_CERTIFICATE,
            recordKeyPrefix: "sw-spec-",
            getDocumentsBySlug,
        }),
    ];
    const generalColumns: TableColumn[] = [
        ...SOFTWARE_COLUMNS,
        repeaterAttachmentColumn({
            categorySlug: DOC_CATEGORY_SLUGS.SKILL_CERTIFICATE,
            recordKeyPrefix: "sw-gen-",
            getDocumentsBySlug,
        }),
    ];
    const certificateColumns: TableColumn[] = [
        ...CERTIFICATE_COLUMNS,
        repeaterAttachmentColumn({
            categorySlug: DOC_CATEGORY_SLUGS.COURSE_CERTIFICATES,
            recordKeyPrefix: "cert-",
            getDocumentsBySlug,
        }),
    ];
    const specialSkillColumns: TableColumn[] = [
        { key: "value", label: "مهارت" },
        repeaterAttachmentColumn({
            categorySlug: DOC_CATEGORY_SLUGS.SKILL_CERTIFICATE,
            recordKeyPrefix: "spc-",
            getDocumentsBySlug,
        }),
    ];

    return (
        <Card>
            <CardHeader>
                <CardTitle>مهارت‌ها</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
                {/* Languages */}
                <form.Field name="skills.languages">
                    {(field) => (
                        <FormRepeater
                            field={field}
                            label="زبان‌ها"
                            columns={languageColumns}
                            onPersist={onPersist}
                            getSummary={(item) => ({
                                language: languageLabel(item.language as string | undefined),
                                reading: item.reading,
                                writing: item.writing,
                                speaking: item.speaking,
                                comprehension: item.comprehension,
                            })}
                            renderItem={(index) => (
                                <div className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
                                        <form.Field
                                            name={`skills.languages.${index}.language`}
                                            validators={zodFieldValidators(
                                                fieldSchemas.language,
                                            )}
                                        >
                                            {(f) => (
                                                <FormOptionSelectField
                                                    field={f}
                                                    label="زبان"
                                                    group="language"
                                                    placeholder="انتخاب زبان"
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name={`skills.languages.${index}.reading`}
                                        >
                                            {(f) => (
                                                <FormSelectField
                                                    field={f}
                                                    label="خواندن"
                                                    options={
                                                        LANGUAGE_LEVEL_OPTIONS
                                                    }
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name={`skills.languages.${index}.writing`}
                                        >
                                            {(f) => (
                                                <FormSelectField
                                                    field={f}
                                                    label="نوشتن"
                                                    options={
                                                        LANGUAGE_LEVEL_OPTIONS
                                                    }
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name={`skills.languages.${index}.speaking`}
                                        >
                                            {(f) => (
                                                <FormSelectField
                                                    field={f}
                                                    label="صحبت کردن"
                                                    options={
                                                        LANGUAGE_LEVEL_OPTIONS
                                                    }
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name={`skills.languages.${index}.comprehension`}
                                        >
                                            {(f) => (
                                                <FormSelectField
                                                    field={f}
                                                    label="درک مطلب"
                                                    options={
                                                        LANGUAGE_LEVEL_OPTIONS
                                                    }
                                                />
                                            )}
                                        </form.Field>
                                    </div>
                                    {uuid && (
                                        <FileUploadField
                                            uuid={uuid}
                                            entity={entity}
                                            categorySlug={
                                                DOC_CATEGORY_SLUGS.LANGUAGE_CERTIFICATE
                                            }
                                            label="گواهینامه زبان"
                                            recordKey={`lang-${index}`}
                                        />
                                    )}
                                </div>
                            )}
                        />
                    )}
                </form.Field>

                <Separator />

                {/* Software Skills — grouped under one heading */}
                <div className="space-y-4">
                    <span className="text-sm font-medium">
                        مهارت‌های نرم‌افزاری
                    </span>

                    <form.Field name="skills.software_skills.specialized">
                        {(field) => (
                            <FormRepeater
                                field={field}
                                label="تخصصی"
                                columns={specializedColumns}
                                maxItems={8}
                                onPersist={onPersist}
                                getSummary={(item) => ({
                                    name: item.name,
                                    level: item.level,
                                })}
                                renderItem={(index) => (
                                    <div className="space-y-4">
                                        <SoftwareItem
                                            form={form}
                                            index={index}
                                            prefix="skills.software_skills.specialized"
                                        />
                                        {uuid && (
                                            <FileUploadField
                                                uuid={uuid}
                                                entity={entity}
                                                categorySlug={
                                                    DOC_CATEGORY_SLUGS.SKILL_CERTIFICATE
                                                }
                                                label="گواهی مهارت نرم‌افزاری"
                                                recordKey={`sw-spec-${index}`}
                                            />
                                        )}
                                    </div>
                                )}
                            />
                        )}
                    </form.Field>

                    <form.Field name="skills.software_skills.general">
                        {(field) => (
                            <FormRepeater
                                field={field}
                                label="عمومی"
                                columns={generalColumns}
                                maxItems={4}
                                onPersist={onPersist}
                                getSummary={(item) => ({
                                    name: item.name,
                                    level: item.level,
                                })}
                                renderItem={(index) => (
                                    <div className="space-y-4">
                                        <SoftwareItem
                                            form={form}
                                            index={index}
                                            prefix="skills.software_skills.general"
                                        />
                                        {uuid && (
                                            <FileUploadField
                                                uuid={uuid}
                                                entity={entity}
                                                categorySlug={
                                                    DOC_CATEGORY_SLUGS.SKILL_CERTIFICATE
                                                }
                                                label="گواهی مهارت نرم‌افزاری"
                                                recordKey={`sw-gen-${index}`}
                                            />
                                        )}
                                    </div>
                                )}
                            />
                        )}
                    </form.Field>
                </div>

                <Separator />

                {/* Certificates */}
                <form.Field name="skills.certificates">
                    {(field) => (
                        <FormRepeater
                            field={field}
                            label="گواهینامه‌ها"
                            columns={certificateColumns}
                            onPersist={onPersist}
                            getSummary={(item) => ({
                                title: item.title,
                                expire_at: item.expire_at,
                            })}
                            renderItem={(index) => (
                                <div className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <form.Field
                                            name={`skills.certificates.${index}.title`}
                                            validators={zodFieldValidators(
                                                fieldSchemas.certificate_title,
                                            )}
                                        >
                                            {(f) => (
                                                <FormTextField
                                                    field={f}
                                                    label="عنوان"
                                                />
                                            )}
                                        </form.Field>
                                        <form.Field
                                            name={`skills.certificates.${index}.expire_at`}
                                        >
                                            {(f) => (
                                                <FormDatePicker
                                                    field={f}
                                                    label="تاریخ انقضا"
                                                />
                                            )}
                                        </form.Field>
                                    </div>
                                    {uuid && (
                                        <FileUploadField
                                            uuid={uuid}
                                            entity={entity}
                                            categorySlug={
                                                DOC_CATEGORY_SLUGS.COURSE_CERTIFICATES
                                            }
                                            label="فایل گواهینامه"
                                            recordKey={`cert-${index}`}
                                        />
                                    )}
                                </div>
                            )}
                        />
                    )}
                </form.Field>

                <Separator />

                {/* Special Skills */}
                <form.Field name="skills.special_skills">
                    {(field) => (
                        <FormRepeater
                            field={field}
                            label="مهارت‌های خاص"
                            columns={specialSkillColumns}
                            onPersist={onPersist}
                            getSummary={(item) => ({
                                value: typeof item === "string" ? item : "",
                            })}
                            renderItem={(index) => (
                                <div className="space-y-4">
                                    <form.Field
                                        name={`skills.special_skills.${index}`}
                                        validators={zodFieldValidators(
                                            fieldSchemas.special_skill_item,
                                        )}
                                    >
                                        {(f) => (
                                            <FormTextField
                                                field={f}
                                                label={`مهارت ${index + 1}`}
                                                placeholder="نام مهارت"
                                            />
                                        )}
                                    </form.Field>
                                    {uuid && (
                                        <FileUploadField
                                            uuid={uuid}
                                            entity={entity}
                                            categorySlug={
                                                DOC_CATEGORY_SLUGS.SKILL_CERTIFICATE
                                            }
                                            label="گواهی مهارت"
                                            recordKey={`spc-${index}`}
                                        />
                                    )}
                                </div>
                            )}
                        />
                    )}
                </form.Field>
            </CardContent>
        </Card>
    );
}
