import { useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import {
    useCreateAiProvider,
    useUpdateAiProvider,
} from "@/features/ai/hooks";
import { AI_DRIVERS, type AiDriver, type AiProviderDto } from "@/features/ai/types";
import { getApiError } from "@/lib/error-utils";
import { toast } from "sonner";

/**
 * Create or edit one provider row.
 *
 * Two things drive the shape of this form:
 *
 * 1. The credential is write-only. The server never sends the key back, so
 *    editing starts from an empty box labelled with the fact that one is
 *    stored. That is why the key is *not* pre-filled: a form that showed a
 *    masked key and resubmitted it on every save would quietly overwrite a
 *    real credential with whatever the mask decoded to.
 * 2. `name` is frozen after creation, so the field is disabled rather than
 *    hidden on edit — hiding it would look like the provider has no name, and
 *    disabling it says what is true: this is its identity and it is fixed.
 */

interface AiProviderDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Null means "create". */
    provider: AiProviderDto | null;
}

const NAME_PATTERN = /^[a-z0-9][a-z0-9_-]*$/;

export function AiProviderDialog({
    open,
    onOpenChange,
    provider,
}: AiProviderDialogProps) {
    const isEdit = provider !== null;

    const create = useCreateAiProvider();
    const update = useUpdateAiProvider();

    const [name, setName] = useState("");
    const [driver, setDriver] = useState<AiDriver>("ollama");
    const [baseUrl, setBaseUrl] = useState("");
    const [apiKey, setApiKey] = useState("");
    const [clearApiKey, setClearApiKey] = useState(false);
    const [isActive, setIsActive] = useState(true);
    const [isDefault, setIsDefault] = useState(false);

    // Re-seed from the row each time the dialog opens, so a cancelled edit
    // never leaks its half-typed values into the next one.
    useEffect(() => {
        if (!open) {
            return;
        }

        setName(provider?.name ?? "");
        setDriver(provider?.driver ?? "ollama");
        setBaseUrl(provider?.base_url ?? "");
        setApiKey("");
        setClearApiKey(false);
        setIsActive(provider?.is_active ?? true);
        setIsDefault(provider?.is_default ?? false);
    }, [open, provider]);

    const driverMeta = AI_DRIVERS.find((d) => d.value === driver);

    const nameValid = isEdit || NAME_PATTERN.test(name);
    // A key is mandatory for every driver except the local one. Ollama is the
    // zero-credential path (D24), and requiring a key there would make the
    // screen that offers it the one screen that cannot reach it.
    const keySatisfied = driverMeta?.keyless ? true : apiKey !== "" || (isEdit && provider?.has_api_key === true && !clearApiKey);
    const baseUrlValid = baseUrl === "" || /^https?:\/\/\S+$/i.test(baseUrl);
    const pending = create.isPending || update.isPending;

    const handleSave = () => {
        if (isEdit && provider) {
            update.mutate(
                {
                    id: provider.id,
                    payload: {
                        driver,
                        base_url: baseUrl === "" ? null : baseUrl.trim(),
                        ...(apiKey === "" ? {} : { api_key: apiKey.trim() }),
                        ...(clearApiKey ? { clear_api_key: true } : {}),
                        is_active: isActive,
                        is_default: isDefault,
                    },
                },
                {
                    onSuccess: () => onOpenChange(false),
                    onError: (error) => toast.error(getApiError(error)),
                },
            );

            return;
        }

        create.mutate(
            {
                name: name.trim(),
                driver,
                base_url: baseUrl === "" ? null : baseUrl.trim(),
                ...(apiKey === "" ? {} : { api_key: apiKey.trim() }),
                is_active: isActive,
                is_default: isDefault,
            },
            {
                onSuccess: () => onOpenChange(false),
                onError: (error) => toast.error(getApiError(error)),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? "ویرایش ارائه‌دهنده" : "افزودن ارائه‌دهنده"}
                    </DialogTitle>
                    <DialogDescription>
                        {isEdit
                            ? "نام ارائه‌دهنده پس از ایجاد تغییر نمی‌کند؛ کلید ذخیره‌شده هم هرگز به مرورگر بازگردانده نمی‌شود."
                            : "نام، شناسه پایدار ارائه‌دهنده است و در گزارش اجراها ثبت می‌شود. فقط حروف کوچک انگلیسی، عدد، خط تیره و زیرخط."}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 py-2">
                    <div className="grid gap-2">
                        <Label htmlFor="ai-provider-name">نام</Label>
                        <Input
                            id="ai-provider-name"
                            dir="ltr"
                            value={isEdit ? (provider?.name ?? "") : name}
                            onChange={(e) => setName(e.target.value)}
                            disabled={isEdit}
                            placeholder="local"
                        />
                        {!isEdit && name !== "" && !nameValid && (
                            <p className="text-destructive text-xs">
                                فقط حروف کوچک انگلیسی، عدد، خط تیره و زیرخط مجاز
                                است
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="ai-provider-driver">نوع درگاه</Label>
                        <Select
                            value={driver}
                            onValueChange={(value) => setDriver(value as AiDriver)}
                        >
                            <SelectTrigger id="ai-provider-driver" dir="ltr">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {AI_DRIVERS.map((d) => (
                                    <SelectItem key={d.value} value={d.value}>
                                        {d.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="ai-provider-url">نشانی درگاه</Label>
                        <Input
                            id="ai-provider-url"
                            dir="ltr"
                            value={baseUrl}
                            onChange={(e) => setBaseUrl(e.target.value)}
                            placeholder={
                                driver === "ollama"
                                    ? "http://localhost:11434"
                                    : "https://api.example.com/v1"
                            }
                        />
                        <p className="text-muted-foreground text-xs">
                            {driver === "ollama" && !baseUrl
                                ? "خالی گذاشتن یعنی نشانی پیش‌فرض Ollama محلی استفاده می‌شود."
                                : "برای درگاه‌های میزبانی‌شده خالی بگذارید؛ نشانی پیش‌فرض خودشان استفاده می‌شود."}
                        </p>
                        {baseUrl !== "" && !baseUrlValid && (
                            <p className="text-destructive text-xs">
                                نشانی باید با http:// یا https:// شروع شود
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="ai-provider-key">کلید دسترسی</Label>
                        <Input
                            id="ai-provider-key"
                            dir="ltr"
                            type="password"
                            autoComplete="off"
                            value={apiKey}
                            onChange={(e) => {
                                setApiKey(e.target.value);
                                // Typing a new key and asking to clear the old
                                // one at the same time is a contradiction; the
                                // typed value wins because it is the newer
                                // intent.
                                if (e.target.value !== "") {
                                    setClearApiKey(false);
                                }
                            }}
                            disabled={clearApiKey}
                            placeholder={
                                isEdit && provider?.has_api_key
                                    ? "•••••••• (ذخیره شده — برای تغییر، مقدار جدید بنویسید)"
                                    : driverMeta?.keyless
                                      ? "برای Ollama لازم نیست"
                                      : "sk-…"
                            }
                        />
                        {isEdit && provider?.has_api_key && !clearApiKey && (
                            <p className="text-muted-foreground text-xs">
                                خالی گذاشتن این کادر یعنی کلید فعلی دست‌نخورده بماند.
                            </p>
                        )}
                        {isEdit && provider?.has_api_key && (
                            <div className="flex items-center justify-between gap-2 rounded-md border px-3 py-2">
                                <Label
                                    htmlFor="ai-provider-clear-key"
                                    className="text-xs font-normal"
                                >
                                    حذف کلید ذخیره‌شده
                                </Label>
                                <Switch
                                    id="ai-provider-clear-key"
                                    checked={clearApiKey}
                                    onCheckedChange={setClearApiKey}
                                />
                            </div>
                        )}
                    </div>

                    <div className="grid gap-3 rounded-md border px-3 py-3">
                        <div className="flex items-center justify-between gap-2">
                            <Label
                                htmlFor="ai-provider-active"
                                className="font-normal"
                            >
                                فعال
                            </Label>
                            <Switch
                                id="ai-provider-active"
                                checked={isActive}
                                onCheckedChange={setIsActive}
                            />
                        </div>
                        <p className="text-muted-foreground -mt-1 text-xs">
                            ارائه‌دهنده غیرفعال در اجرای عامل‌ها استفاده نمی‌شود؛
                            عامل‌های متصل به آن به ارائه‌دهنده پیش‌فرض برمی‌گردند.
                        </p>

                        {!isEdit && (
                            <div className="flex items-center justify-between gap-2">
                                <Label
                                    htmlFor="ai-provider-default"
                                    className="font-normal"
                                >
                                    ارائه‌دهنده پیش‌فرض
                                </Label>
                                <Switch
                                    id="ai-provider-default"
                                    checked={isDefault}
                                    onCheckedChange={setIsDefault}
                                />
                            </div>
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
                        disabled={
                            pending ||
                            (isEdit ? false : name.trim() === "" || !nameValid) ||
                            !keySatisfied ||
                            !baseUrlValid
                        }
                    >
                        ذخیره
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
