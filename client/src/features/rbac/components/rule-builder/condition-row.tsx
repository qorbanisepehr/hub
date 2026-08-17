import { IconTrash } from "@tabler/icons-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Field, FieldLabel } from "@/components/ui/field";
import type { RuleBuilderMeta } from "@/features/rbac/types";
import {
    type ConditionDraft,
    SET_OPERATORS,
    attributeTypeFor,
    conditionNeedsValue,
} from "./types";

interface ConditionRowProps {
    index: number;
    condition: ConditionDraft;
    resourceTypeKey: string;
    resourceAttributes: RuleBuilderMeta["resource_types"][number]["attributes"];
    meta: RuleBuilderMeta | undefined;
    operatorOptions: RuleBuilderMeta["operators"];
    valueSourceOptions: RuleBuilderMeta["value_sources"];
    onChange: (index: number, patch: Partial<ConditionDraft>) => void;
    onRemove: () => void;
}

export function ConditionRow({
    index,
    condition,
    resourceTypeKey,
    resourceAttributes,
    meta,
    operatorOptions,
    valueSourceOptions,
    onChange,
    onRemove,
}: ConditionRowProps) {
    const attributeType = attributeTypeFor(meta, resourceTypeKey, condition.attribute);
    const availableOperators =
        resourceAttributes.find((attr) => attr.key === condition.attribute)
            ?.operators ?? [];
    const operatorFiltered =
        availableOperators.length > 0
            ? operatorOptions.filter((operator) =>
                  availableOperators.includes(operator.key),
              )
            : operatorOptions;

    const userAttributes =
        meta?.resource_types.find((rt) => rt.key === "user")?.attributes ?? [];

    const needsValue = conditionNeedsValue(condition.operator);
    const isSetOperator = SET_OPERATORS.has(condition.operator);
    const isActorSource = condition.value_source === "actor";
    const isResourceSource = condition.value_source === "resource";

    return (
        <div className="rounded-lg border p-3 space-y-3">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <Field>
                    <FieldLabel>ویژگی منبع</FieldLabel>
                    <Select
                        value={condition.attribute}
                        onValueChange={(value) =>
                            onChange(index, {
                                attribute: value ?? "",
                                operator: "",
                            })
                        }
                        itemToStringLabel={(val) =>
                            resourceAttributes.find((attr) => attr.key === val)
                                ?.label ?? ""
                        }
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="انتخاب ویژگی..." />
                        </SelectTrigger>
                        <SelectContent>
                            {resourceAttributes.map((attr) => (
                                <SelectItem key={attr.key} value={attr.key}>
                                    {attr.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>

                <Field>
                    <FieldLabel>عملگر</FieldLabel>
                    <Select
                        value={condition.operator}
                        onValueChange={(value) =>
                            onChange(index, { operator: value ?? "" })
                        }
                        itemToStringLabel={(val) =>
                            operatorFiltered.find((op) => op.key === val)?.label ?? ""
                        }
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="انتخاب عملگر..." />
                        </SelectTrigger>
                        <SelectContent>
                            {operatorFiltered.map((operator) => (
                                <SelectItem key={operator.key} value={operator.key}>
                                    {operator.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>

                <Field>
                    <FieldLabel>نوع مقدار</FieldLabel>
                    <Select
                        value={condition.value_source}
                        onValueChange={(value) =>
                            onChange(index, {
                                value_source: (value ?? "") as ConditionDraft["value_source"],
                                value: "",
                            })
                        }
                        itemToStringLabel={(val) =>
                            valueSourceOptions.find((vs) => vs.key === val)?.label ?? ""
                        }
                    >
                        <SelectTrigger className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {valueSourceOptions.map((source) => (
                                <SelectItem key={source.key} value={source.key}>
                                    {source.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>

                <Field>
                    <FieldLabel>مقدار</FieldLabel>
                    {!needsValue ? (
                        <Input value="" disabled placeholder="—" />
                    ) : isActorSource ? (
                        <Select
                            value={condition.value}
                            onValueChange={(value) =>
                                onChange(index, { value: value ?? "" })
                            }
                            itemToStringLabel={(val) =>
                                userAttributes.find((attr) => attr.key === val)
                                    ?.label ?? ""
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="انتخاب ویژگی کاربر..." />
                            </SelectTrigger>
                            <SelectContent>
                                {userAttributes.map((attr) => (
                                    <SelectItem key={attr.key} value={attr.key}>
                                        {attr.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    ) : isResourceSource ? (
                        <Select
                            value={condition.value}
                            onValueChange={(value) =>
                                onChange(index, { value: value ?? "" })
                            }
                            itemToStringLabel={(val) =>
                                resourceAttributes.find((attr) => attr.key === val)
                                    ?.label ?? ""
                            }
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="انتخاب ویژگی منبع..." />
                            </SelectTrigger>
                            <SelectContent>
                                {resourceAttributes.map((attr) => (
                                    <SelectItem key={attr.key} value={attr.key}>
                                        {attr.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    ) : (
                        <Input
                            type={
                                !isSetOperator &&
                                attributeType === "integer"
                                    ? "number"
                                    : "text"
                            }
                            dir={attributeType === "integer" ? "ltr" : undefined}
                            value={condition.value}
                            onChange={(e) =>
                                onChange(index, { value: e.target.value })
                            }
                            placeholder={
                                isSetOperator
                                    ? "مقادیر با ویرگول"
                                    : "مقدار"
                            }
                        />
                    )}
                </Field>
            </div>
            <div className="flex justify-end">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onRemove}
                    className="text-destructive gap-1.5"
                >
                    <IconTrash className="size-4" />
                    حذف شرط
                </Button>
            </div>
        </div>
    );
}
