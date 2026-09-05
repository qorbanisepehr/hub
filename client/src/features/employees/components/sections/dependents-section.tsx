import { useEffect } from "react";
import { useSelector } from "@tanstack/react-form";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    FormDatePicker,
    FormOptionSelectField,
    FormRepeater,
    FormTextField,
} from "@/components/forms";
import { FileUploadField, MissingDocsBadge, RowDocsPanel } from "@/components/documents";
import type { MissingRowDoc } from "@/features/documents/docs-feedback";
import { dependentRowLabel } from "@/features/employees/dependents-docs";
import { useDependentDocsFeedback } from "@/features/employees/hooks/use-dependent-docs-feedback";
import type { EmployeeFormApi } from "@/features/employees/types";

type SectionProps = {
    form: EmployeeFormApi;
    uuid: string;
    /** Called after repeater add/edit/delete to persist the section */
    onPersist?: () => void;
};

/** Gender implied by the fixed father/mother relationship options. */
const RELATIONSHIP_GENDER: Record<string, string> = {
    father: "male",
    mother: "female",
};

type DependentRow = {
    relationship_type?: string;
    gender?: string;
    first_name?: string;
    last_name?: string;
};

/**
 * One dependent row. Auto-sets gender when the relationship implies it
 * (father → مرد, mother → زن), shows تاریخ ازدواج only for the spouse row,
 * and reveals the free-text نسبت input only for the سایر (other) row.
 */
function DependentRowFields({
    form,
    uuid,
    index,
    missing,
    docsLoading,
}: {
    form: EmployeeFormApi;
    uuid: string;
    index: number;
    missing: MissingRowDoc[];
    docsLoading: boolean;
}) {
    const row = useSelector(
        form.store,
        (state) =>
            ((state.values as Record<string, unknown>)
                ?.dependents as Record<string, unknown> | undefined)
                ?.dependents as DependentRow[] | undefined,
    )?.[index] ?? {};

    const relationshipType =
        typeof row?.relationship_type === "string" ? row.relationship_type : "";

    const gender = typeof row?.gender === "string" ? row.gender : "";

    useEffect(() => {
        const implied = RELATIONSHIP_GENDER[relationshipType];
        if (implied && gender !== implied) {
            form.setFieldValue(
                `dependents.dependents.${index}.gender`,
                implied,
                { dontUpdateMeta: true },
            );
        }
    }, [relationshipType, gender, form, index]);

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                <form.Field
                    name={`dependents.dependents.${index}.relationship_type`}
                >
                    {(f) => (
                        <FormOptionSelectField
                            field={f}
                            label="نسبت"
                            group="relationship_type"
                            placeholder="انتخاب کنید"
                        />
                    )}
                </form.Field>

                {relationshipType === "other" && (
                    <form.Field
                        name={`dependents.dependents.${index}.custom_relationship`}
                    >
                        {(f) => (
                            <FormTextField
                                field={f}
                                label="نسبت (سایر)"
                                placeholder="نسبت را بنویسید"
                            />
                        )}
                    </form.Field>
                )}

                <form.Field
                    name={`dependents.dependents.${index}.first_name`}
                >
                    {(f) => (
                        <FormTextField field={f} label="نام" />
                    )}
                </form.Field>

                <form.Field
                    name={`dependents.dependents.${index}.last_name`}
                >
                    {(f) => (
                        <FormTextField field={f} label="نام خانوادگی" />
                    )}
                </form.Field>

                <form.Field
                    name={`dependents.dependents.${index}.id_number`}
                >
                    {(f) => (
                        <FormTextField
                            field={f}
                            label="کد ملی"
                            dir="ltr"
                        />
                    )}
                </form.Field>

                <form.Field
                    name={`dependents.dependents.${index}.gender`}
                >
                    {(f) => (
                        <FormOptionSelectField
                            field={f}
                            label="جنسیت"
                            group="gender"
                            placeholder="انتخاب کنید"
                            disabled={
                                RELATIONSHIP_GENDER[relationshipType] !==
                                undefined
                            }
                        />
                    )}
                </form.Field>

                <form.Field
                    name={`dependents.dependents.${index}.birth_date`}
                >
                    {(f) => (
                        <FormDatePicker field={f} label="تاریخ تولد" />
                    )}
                </form.Field>

                {relationshipType === "spouse" && (
                    <form.Field
                        name={`dependents.dependents.${index}.marriage_date`}
                    >
                        {(f) => (
                            <FormDatePicker
                                field={f}
                                label="تاریخ ازدواج"
                            />
                        )}
                    </form.Field>
                )}
            </div>

            <RowDocsPanel
                title="مدارک این وابسته"
                isLoading={docsLoading}
                missing={missing}
            >
                <FileUploadField
                    uuid={uuid}
                    entity="employees"
                    categorySlug="national-card"
                    label="کارت ملی"
                    variant="card"
                    multiple
                    fieldKey={`dependent-${index}`}
                    sectionKey="dependents"
                />
                <FileUploadField
                    uuid={uuid}
                    entity="employees"
                    categorySlug="birth-certificate"
                    label="شناسنامه"
                    variant="card"
                    multiple
                    fieldKey={`dependent-${index}`}
                    sectionKey="dependents"
                />
            </RowDocsPanel>
        </div>
    );
}

/**
 * Dependents (بستگان و افراد تحت تکفل) section. Each row carries its own
 * document uploads placed under `section_key="dependents"` with
 * `field_key="dependent-{index}"`; page counts come from the requirements
 * endpoint, never hardcoded here.
 */
export function DependentsSection({ form, uuid, onPersist }: SectionProps) {
    const { isLoading: docsLoading, relationshipOptions, getMissing } =
        useDependentDocsFeedback(uuid, []);

    return (
        <Card>
            <CardHeader>
                <CardTitle>بستگان و افراد تحت تکفل</CardTitle>
            </CardHeader>

            <CardContent className="space-y-6">
                <form.Field name="dependents.dependents">
                    {(field) => (
                        <FormRepeater
                            defaultMode="table"
                            field={field}
                            label="بستگان"
                            columns={[
                                { key: "relationship_type", label: "نسبت" },
                                { key: "first_name", label: "نام" },
                                { key: "last_name", label: "نام خانوادگی" },
                                { key: "id_number", label: "کد ملی" },
                                { key: "birth_date", label: "تاریخ تولد", type: "date" },
                                {
                                    key: "_docs_status",
                                    label: "وضعیت مدارک",
                                    render: (_value, _item, index) => (
                                        <MissingDocsBadge
                                            missing={getMissing(index)}
                                        />
                                    ),
                                },
                            ]}
                            emptyMessage="هنوز وابسته‌ای اضافه نشده است."
                            onPersist={onPersist}
                            getSummary={(item) => ({
                                relationship_type:
                                    relationshipOptions?.find(
                                        (option) =>
                                            option.value ===
                                            item.relationship_type,
                                    )?.label ?? item.relationship_type,
                                first_name: item.first_name,
                                last_name: item.last_name,
                                id_number: item.id_number,
                                birth_date: item.birth_date,
                            })}
                            renderHeader={(item, index) => (
                                <span className="flex items-center gap-2 font-medium">
                                    {dependentRowLabel(
                                        item.relationship_type,
                                        index,
                                        relationshipOptions,
                                    )}
                                    {item.first_name || item.last_name
                                        ? `: ${String(item.first_name ?? "")} ${String(item.last_name ?? "")}`.trim()
                                        : ""}
                                </span>
                            )}
                            renderItem={(index) => (
                                <DependentRowFields
                                    form={form}
                                    uuid={uuid}
                                    index={index}
                                    missing={getMissing(index)}
                                    docsLoading={docsLoading}
                                />
                            )}
                        />
                    )}
                </form.Field>
            </CardContent>
        </Card>
    );
}