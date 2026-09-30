import { useEffect, useMemo, useState } from "react";

import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { useUpdateAiAgentBinding } from "@/features/ai/hooks";
import type { AiAgentDto, AiProviderDto } from "@/features/ai/types";

/**
 * Point one agent at a provider and a model — or at neither.
 *
 * "Neither" is a first-class choice, not the absence of one: an agent with no
 * binding inherits the active default provider, so that row follows a change
 * of default while a pinned one does not. The select therefore has an explicit
 * «ارائه‌دهنده پیش‌فرض» option, and the copy says which behaviour it buys,
 * because the two run identically and mean different things.
 *
 * Only active providers and models are offered. A binding to a disabled row is
 * legal and the runner falls back from it (A2), but offering it here would be
 * offering a setting that does not do what it looks like it does.
 *
 * The model list is narrowed to the selected provider's own models: the server
 * rejects a cross-provider pair, so showing one would only produce an error
 * the user cannot act on.
 */

interface AiAgentDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    agent: AiAgentDto;
    providers: AiProviderDto[];
}

const INHERIT = "__inherit__";

export function AiAgentDialog({
    open,
    onOpenChange,
    agent,
    providers,
}: AiAgentDialogProps) {
    const update = useUpdateAiAgentBinding();

    const [providerId, setProviderId] = useState<string>(INHERIT);
    const [modelId, setModelId] = useState<string>(INHERIT);

    useEffect(() => {
        if (!open) {
            return;
        }

        setProviderId(agent.provider_id ? String(agent.provider_id) : INHERIT);
        setModelId(agent.model_id ? String(agent.model_id) : INHERIT);
    }, [open, agent]);

    const activeProviders = useMemo(
        () => providers.filter((p) => p.is_active),
        [providers],
    );

    const availableModels = useMemo(() => {
        if (providerId === INHERIT) {
            return [];
        }

        return (
            providers
                .find((p) => p.id === Number(providerId))
                ?.models.filter((m) => m.is_active) ?? []
        );
    }, [providers, providerId]);

    const handleSave = () => {
        update.mutate(
            {
                id: agent.id,
                payload: {
                    provider_id: providerId === INHERIT ? null : Number(providerId),
                    model_id: modelId === INHERIT ? null : Number(modelId),
                },
            },
            { onSuccess: () => onOpenChange(false) },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>اتصال عامل</DialogTitle>
                    <DialogDescription>
                        <span dir="ltr" className="font-medium">
                            {agent.key}
                        </span>
                        {agent.label ? ` — ${agent.label}` : ""}
                        <br />
                        کلاس عامل و کلید آن متعلق به دامنه مصرف‌کننده است و از
                        اینجا تغییر نمی‌کنند.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 py-2">
                    <div className="grid gap-2">
                        <Label htmlFor="ai-agent-provider">ارائه‌دهنده</Label>
                        <Select
                            value={providerId}
                            onValueChange={(value) => {
                                setProviderId(value ?? INHERIT);
                                // Switching provider invalidates the model: the
                                // old one belongs to a gateway the agent can no
                                // longer reach, and leaving it selected would
                                // submit a pair the server rejects.
                                setModelId(INHERIT);
                            }}
                        >
                            <SelectTrigger id="ai-agent-provider">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={INHERIT}>
                                    ارائه‌دهنده پیش‌فرض (بدون اتصال)
                                </SelectItem>
                                {activeProviders.map((provider) => (
                                    <SelectItem
                                        key={provider.id}
                                        value={String(provider.id)}
                                    >
                                        {provider.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-muted-foreground text-xs">
                            {providerId === INHERIT
                                ? "با تغییر ارائه‌دهنده پیش‌فرض، این عامل هم دنبال آن می‌رود."
                                : "این عامل همیشه همین ارائه‌دهنده را به کار می‌برد، حتی وقتی پیش‌فرض عوض شود."}
                        </p>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="ai-agent-model">مدل</Label>
                        <Select
                            value={modelId}
                            onValueChange={(value) =>
                                // Base UI can report null on a cleared
                                // selection; there is no cleared state here,
                                // so it means "inherit" like the empty option.
                                setModelId(value ?? INHERIT)
                            }
                            disabled={availableModels.length === 0}
                        >
                            <SelectTrigger id="ai-agent-model" dir="ltr">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={INHERIT}>
                                    مدل پیش‌فرض ارائه‌دهنده
                                </SelectItem>
                                {availableModels.map((model) => (
                                    <SelectItem
                                        key={model.id}
                                        value={String(model.id)}
                                    >
                                        {model.display_label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {providerId === INHERIT && (
                            <p className="text-muted-foreground text-xs">
                                برای انتخاب مدل، اول یک ارائه‌دهنده انتخاب کنید.
                            </p>
                        )}
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
                        disabled={update.isPending}
                    >
                        ذخیره
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
