import { useEffect, useState } from "react";
import { IconChevronDown, IconPencil, IconPlus, IconPower } from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { RowActions } from "@/components/shared/row-actions";
import {
    useCreateAiModel,
    useDeleteAiModel,
    useUpdateAiModel,
} from "@/features/ai/hooks";
import {
    AI_CAPABILITIES,
    type AiCapability,
    type AiModelDto,
    type AiProviderDto,
} from "@/features/ai/types";

/**
 * Manage one provider's models.
 *
 * A separate dialog rather than a section on the tab because a model has no
 * life outside its provider: the API addresses it under the provider that
 * owns it, and an administrator thinking about a model is always thinking
 * about which gateway serves it.
 *
 * The list is a plain list, not a table. There is no pagination (a provider has
 * a handful of models, and the registry is small by design), and a table here
 * would be a table with one column of interest.
 */

/** A capability's Persian label, falling back to its key for an unknown one. */
function capabilityLabel(capability: string): string {
    return (
        AI_CAPABILITIES.find((c) => c.value === capability)?.label ?? capability
    );
}

interface AiProviderModelsDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    provider: AiProviderDto;
}

export function AiProviderModelsDialog({
    open,
    onOpenChange,
    provider,
}: AiProviderModelsDialogProps) {
    const [editing, setEditing] = useState<AiModelDto | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    // Which rows are open. Closed by default: a list of models is scanned by
    // name, and capabilities are what you open a row to check.
    const [expanded, setExpanded] = useState<ReadonlySet<string>>(new Set());

    const remove = useDeleteAiModel();
    const toggle = useUpdateAiModel();

    const toggleExpanded = (modelName: string) =>
        setExpanded((current) => {
            const next = new Set(current);

            if (next.has(modelName)) {
                next.delete(modelName);
            } else {
                next.add(modelName);
            }

            return next;
        });

    const openCreate = () => {
        setEditing(null);
        setFormOpen(true);
    };

    const openEdit = (model: AiModelDto) => {
        setEditing(model);
        setFormOpen(true);
    };

    return (
        <ResponsiveDialog
            open={open} onOpenChange={onOpenChange}
            size="2xl"
            title={<>مدل‌های «{provider.name}»</>}
            description={<>
                نامی که خودِ ارائه‌دهنده مدل را با آن می‌شناسد (مثل
                gpt-oss:20b). اگر هیچ مدلی ثبت نشود، خودِ ارائه‌دهنده
                مدل پیش‌فرضش را انتخاب می‌کند.
            </>}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        بستن
                    </Button>
                    <Button type="button" onClick={openCreate}>
                        <IconPlus className="size-4" />
                        افزودن مدل
                    </Button>
                </>
            }
        >
            <div className="grid gap-2 py-2">
                {provider.models.length === 0 ? (
                    <p className="text-muted-foreground rounded-md border border-dashed px-3 py-6 text-center text-sm">
                        هنوز مدلی برای این ارائه‌دهنده ثبت نشده است
                    </p>
                ) : (
                    provider.models.map((model) => {
                        const detailsOpen = expanded.has(model.model_name);

                        return (
                            <div
                                key={model.model_name}
                                className="rounded-md border"
                            >
                                <div className="flex items-start justify-between gap-2 px-3 py-2">
                                    <button
                                        type="button"
                                        className="flex min-w-0 flex-1 items-start gap-2 text-start"
                                        aria-expanded={detailsOpen}
                                        onClick={() =>
                                            toggleExpanded(model.model_name)
                                        }
                                    >
                                        <IconChevronDown
                                            className={`text-muted-foreground mt-0.5 size-4 shrink-0 transition-transform ${
                                                detailsOpen ? "rotate-180" : ""
                                            }`}
                                        />
                                        <span className="min-w-0">
                                            <span className="flex flex-wrap items-center gap-2">
                                                <span
                                                    dir="ltr"
                                                    className="font-medium text-sm"
                                                >
                                                    {model.model_name}
                                                </span>
                                                {model.label &&
                                                    model.label !==
                                                        model.model_name && (
                                                        <span className="text-muted-foreground text-sm">
                                                            {model.label}
                                                        </span>
                                                    )}
                                                {!model.is_active && (
                                                    <Badge variant="outline">
                                                        غیرفعال
                                                    </Badge>
                                                )}
                                            </span>
                                            {/* Collapsed rows still say what
                                                the model can do: it is the one
                                                fact a person scans for. */}
                                            {!detailsOpen &&
                                                model.capabilities.length >
                                                    0 && (
                                                    <span className="text-muted-foreground mt-0.5 block text-xs">
                                                        {model.capabilities
                                                            .map(capabilityLabel)
                                                            .join(" · ")}
                                                    </span>
                                                )}
                                        </span>
                                    </button>

                                    <RowActions
                                        variant="menu"
                                        actions={[
                                            {
                                                icon: (
                                                    <IconPencil className="size-4" />
                                                ),
                                                label: "ویرایش",
                                                onClick: () => openEdit(model),
                                            },
                                            {
                                                icon: (
                                                    <IconPower className="size-4" />
                                                ),
                                                label: model.is_active
                                                    ? "غیرفعال کردن"
                                                    : "فعال کردن",
                                                onClick: () =>
                                                    toggle.mutate({
                                                        providerName:
                                                            provider.name,
                                                        modelName:
                                                            model.model_name,
                                                        payload: {
                                                            model_name:
                                                                model.model_name,
                                                            label: model.label,
                                                            capabilities:
                                                                model.capabilities,
                                                            is_active:
                                                                !model.is_active,
                                                        },
                                                    }),
                                                disabled: toggle.isPending,
                                            },
                                            {
                                                type: "confirm-delete",
                                                label: "حذف مدل",
                                                message: `آیا از حذف مدل «${model.model_name}» اطمینان دارید؟ هر عاملی که به آن متصل است، به مدل پیش‌فرض ارائه‌دهنده برمی‌گردد.`,
                                                onConfirm: () =>
                                                    remove.mutate({
                                                        providerName:
                                                            provider.name,
                                                        modelName:
                                                            model.model_name,
                                                    }),
                                                isPending: remove.isPending,
                                            },
                                        ]}
                                    />
                                </div>

                                {detailsOpen && (
                                    <div className="text-muted-foreground grid gap-2 border-t px-3 py-2 text-xs">
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <span>قابلیت‌ها:</span>
                                            {model.capabilities.length === 0 ? (
                                                <span>
                                                    ثبت نشده — یعنی هیچ
                                                    قابلیتی ادعا نشده است.
                                                </span>
                                            ) : (
                                                model.capabilities.map(
                                                    (capability) => (
                                                        <Badge
                                                            key={capability}
                                                            variant="secondary"
                                                        >
                                                            {capabilityLabel(
                                                                capability,
                                                            )}
                                                        </Badge>
                                                    ),
                                                )
                                            )}
                                        </div>
                                        <div>
                                            وضعیت:{" "}
                                            {model.is_active
                                                ? "فعال — در انتخاب‌های اتصال عامل‌ها دیده می‌شود."
                                                : "غیرفعال — ثبت است، اما به هیچ اتصال تازه‌ای پیشنهاد نمی‌شود."}
                                        </div>
                                    </div>
                                )}
                            </div>
                        );
                    })
                )}
            </div>

            {formOpen && (
                <AiModelFormDialog
                    provider={provider}
                    model={editing}
                    onOpenChange={setFormOpen}
                />
            )}
        </ResponsiveDialog>
    );
}

interface AiModelFormDialogProps {
    provider: AiProviderDto;
    model: AiModelDto | null;
    onOpenChange: (open: boolean) => void;
}

/**
 * Nested inside the models dialog, so it is a separate component with its own
 * state rather than more state in the list above — two forms' worth of fields
 * sharing one component is how an edit ends up writing into a create.
 */
function AiModelFormDialog({
    provider,
    model,
    onOpenChange,
}: AiModelFormDialogProps) {
    const create = useCreateAiModel();
    const update = useUpdateAiModel();

    const [modelName, setModelName] = useState(model?.model_name ?? "");
    const [label, setLabel] = useState(model?.label ?? "");
    const [capabilities, setCapabilities] = useState<AiCapability[]>(
        model?.capabilities ?? [],
    );
    const [isActive, setIsActive] = useState(model?.is_active ?? true);

    // Re-seed when the target changes so a cancelled edit does not bleed into
    // the next row.
    useEffect(() => {
        setModelName(model?.model_name ?? "");
        setLabel(model?.label ?? "");
        setCapabilities(model?.capabilities ?? []);
        setIsActive(model?.is_active ?? true);
    }, [model]);

    const toggleCapability = (capability: AiCapability, checked: boolean) => {
        setCapabilities((current) =>
            checked
                ? [...current, capability]
                : current.filter((c) => c !== capability),
        );
    };

    const handleSave = () => {
        const payload = {
            model_name: modelName.trim(),
            label: label.trim() === "" ? null : label.trim(),
            capabilities,
            is_active: isActive,
        };

        if (model) {
            // Addressed by its *old* name: the name is the key the entry is
            // stored under inside the provider's document, so renaming a model
            // is a move rather than an edit and the row has to say which entry
            // it is moving.
            update.mutate(
                {
                    providerName: provider.name,
                    modelName: model.model_name,
                    payload,
                },
                { onSuccess: () => onOpenChange(false) },
            );

            return;
        }

        create.mutate(
            { providerName: provider.name, payload },
            { onSuccess: () => onOpenChange(false) },
        );
    };

    return (
        <ResponsiveDialog
            open onOpenChange={onOpenChange}
            size="md"
            title={model ? "ویرایش مدل" : "افزودن مدل"}
            description={<>
                قابلیت‌ها ثبت می‌شوند تا اتصال یک عامل به مدلی که آن
                قابلیت را ندارد، پیش از اولین درخواست واقعی گرفته شود.
            </>}
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
                        onClick={handleSave}
                        disabled={
                            modelName.trim() === "" ||
                            create.isPending ||
                            update.isPending
                        }
                    >
                        ذخیره
                    </Button>
                </>
            }
        >
            <div className="grid gap-4 py-2">
                <div className="grid gap-2">
                    <Label htmlFor="ai-model-name">نام مدل</Label>
                    <Input
                        id="ai-model-name"
                        dir="ltr"
                        value={modelName}
                        onChange={(e) => setModelName(e.target.value)}
                        placeholder="gpt-oss:20b"
                    />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="ai-model-label">نام نمایشی</Label>
                    <Input
                        id="ai-model-label"
                        value={label}
                        onChange={(e) => setLabel(e.target.value)}
                        placeholder="اختیاری"
                    />
                </div>

                <fieldset className="grid gap-2">
                    <legend className="text-sm font-medium">
                        قابلیت‌ها
                    </legend>
                    <div className="grid gap-2">
                        {AI_CAPABILITIES.map((capability) => (
                            <div
                                key={capability.value}
                                className="flex items-center gap-2"
                            >
                                <Checkbox
                                    id={`ai-cap-${capability.value}`}
                                    checked={capabilities.includes(
                                        capability.value,
                                    )}
                                    onCheckedChange={(checked) =>
                                        toggleCapability(
                                            capability.value,
                                            checked === true,
                                        )
                                    }
                                />
                                <Label
                                    htmlFor={`ai-cap-${capability.value}`}
                                    className="font-normal"
                                >
                                    {capability.label}
                                </Label>
                            </div>
                        ))}
                    </div>
                </fieldset>

                <div className="flex items-center justify-between gap-2 rounded-md border px-3 py-2">
                    <Label
                        htmlFor="ai-model-active"
                        className="font-normal"
                    >
                        فعال
                    </Label>
                    <Switch
                        id="ai-model-active"
                        checked={isActive}
                        onCheckedChange={setIsActive}
                    />
                </div>
            </div>
        </ResponsiveDialog>
    );
}
