import { useEffect, useState } from "react";
import { IconPencil, IconPlus, IconStar, IconTrash } from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import {
    useCreateAiModel,
    useDeleteAiModel,
    useMakeAiModelDefault,
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

    const remove = useDeleteAiModel();
    const makeDefault = useMakeAiModelDefault();

    const openCreate = () => {
        setEditing(null);
        setFormOpen(true);
    };

    const openEdit = (model: AiModelDto) => {
        setEditing(model);
        setFormOpen(true);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>مدل‌های «{provider.name}»</DialogTitle>
                    <DialogDescription>
                        نامی که خودِ ارائه‌دهنده مدل را با آن می‌شناسد (مثل
                        gpt-oss:20b). اگر هیچ مدلی ثبت نشود، خودِ ارائه‌دهنده
                        مدل پیش‌فرضش را انتخاب می‌کند.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-2 py-2">
                    {provider.models.length === 0 ? (
                        <p className="text-muted-foreground rounded-md border border-dashed px-3 py-6 text-center text-sm">
                            هنوز مدلی برای این ارائه‌دهنده ثبت نشده است
                        </p>
                    ) : (
                        provider.models.map((model) => (
                            <div
                                key={model.id}
                                className="flex items-center justify-between gap-3 rounded-md border px-3 py-2"
                            >
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span
                                            dir="ltr"
                                            className="font-medium text-sm"
                                        >
                                            {model.model_name}
                                        </span>
                                        {model.label &&
                                            model.label !== model.model_name && (
                                                <span className="text-muted-foreground text-sm">
                                                    {model.label}
                                                </span>
                                            )}
                                        {model.is_default && (
                                            <Badge variant="success">
                                                پیش‌فرض
                                            </Badge>
                                        )}
                                        {!model.is_active && (
                                            <Badge variant="outline">
                                                غیرفعال
                                            </Badge>
                                        )}
                                    </div>
                                    {model.capabilities.length > 0 && (
                                        <div className="mt-1.5 flex flex-wrap gap-1">
                                            {model.capabilities.map((capability) => (
                                                <Badge
                                                    key={capability}
                                                    variant="secondary"
                                                >
                                                    {AI_CAPABILITIES.find(
                                                        (c) =>
                                                            c.value === capability,
                                                    )?.label ?? capability}
                                                </Badge>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                <div className="flex shrink-0 items-center gap-1">
                                    {!model.is_default && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon-sm"
                                            onClick={() =>
                                                makeDefault.mutate({
                                                    providerId: provider.id,
                                                    modelId: model.id,
                                                })
                                            }
                                            disabled={makeDefault.isPending}
                                            title="مدل پیش‌فرض این ارائه‌دهنده"
                                        >
                                            <IconStar className="size-4" />
                                        </Button>
                                    )}
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        onClick={() => openEdit(model)}
                                    >
                                        <IconPencil className="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        onClick={() =>
                                            remove.mutate({
                                                providerId: provider.id,
                                                modelId: model.id,
                                            })
                                        }
                                        disabled={remove.isPending}
                                    >
                                        <IconTrash className="size-4" />
                                    </Button>
                                </div>
                            </div>
                        ))
                    )}
                </div>

                <DialogFooter>
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
                </DialogFooter>

                {formOpen && (
                    <AiModelFormDialog
                        provider={provider}
                        model={editing}
                        onOpenChange={setFormOpen}
                    />
                )}
            </DialogContent>
        </Dialog>
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
            update.mutate(
                { providerId: provider.id, modelId: model.id, payload },
                { onSuccess: () => onOpenChange(false) },
            );

            return;
        }

        create.mutate(
            { providerId: provider.id, payload },
            { onSuccess: () => onOpenChange(false) },
        );
    };

    return (
        <Dialog open onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {model ? "ویرایش مدل" : "افزودن مدل"}
                    </DialogTitle>
                    <DialogDescription>
                        قابلیت‌ها ثبت می‌شوند تا اتصال یک عامل به مدلی که آن
                        قابلیت را ندارد، پیش از اولین درخواست واقعی گرفته شود.
                    </DialogDescription>
                </DialogHeader>

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

                <DialogFooter>
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
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
