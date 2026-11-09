import { useState } from "react";
import {
    IconBolt,
    IconShieldCheck,
    IconSparkles,
    IconTargetArrow,
} from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ResponsiveDialog } from "@/components/ui/responsive-dialog";
import { Spinner } from "@/components/ui/spinner";
import { cn } from "@/lib/utils";
import type { ConsolidationStrategy, TagMergeFocus } from "../types";

const STRATEGIES: Array<{
    value: ConsolidationStrategy;
    label: string;
    hint: string;
    icon: typeof IconShieldCheck;
}> = [
    {
        value: "conservative",
        label: "محافظه‌کارانه",
        hint: "فقط برچسب‌هایی که بی‌شک یک واژه‌اند: تفاوت املایی، خط فارسی/لاتین، فاصله یا یک نام مستعار.",
        icon: IconShieldCheck,
    },
    {
        value: "balanced",
        label: "متعادل",
        hint: "هم‌معناهایی که با اطمینان یک موضوع‌اند؛ پیشنهاد پیش‌فرض.",
        icon: IconTargetArrow,
    },
    {
        value: "aggressive",
        label: "پرخاشگرانه",
        hint: "همچنین فاز، توصیف‌گر یا اختصار یک موضوع؛ برای واژه‌نامهٔ آشفته.",
        icon: IconBolt,
    },
];

interface TagMergeAskDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    isProposing: boolean;
    onSubmit: (focus: TagMergeFocus) => void;
}

/**
 * The question before the proposal: how boldly the model may merge, and
 * whether it looks at the whole registry or one word of it.
 *
 * This exists because the answer is a judgement about *this* hub that no
 * constant gets right: a registry of 338 tags reads differently from one of
 * 20, and "look at the Sonngon family" is a question a person can hold in
 * their head in a way that forty cross-cutting groups are not. Asking first
 * also makes the run's cost visible before it is spent.
 */
export function TagMergeAskDialog({
    open,
    onOpenChange,
    isProposing,
    onSubmit,
}: TagMergeAskDialogProps) {
    const [strategy, setStrategy] =
        useState<ConsolidationStrategy>("balanced");
    const [term, setTerm] = useState("");

    const submit = () => {
        onSubmit({
            strategy,
            term: term.trim() === "" ? null : term.trim(),
        });
    };

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            size="lg"
            title="ادغام هوشمند برچسب‌ها"
            description="میزان جسارت مدل در ادغام و دامنهٔ بررسی را انتخاب کنید؛ پیشنهاد پیش از اعمال برای بازبینی نشان داده می‌شود."
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={isProposing}
                    >
                        انصراف
                    </Button>
                    <Button
                        type="button"
                        onClick={submit}
                        disabled={isProposing}
                    >
                        {isProposing ? (
                            <Spinner />
                        ) : (
                            <IconSparkles className="size-4" />
                        )}
                        بررسی و پیشنهاد
                    </Button>
                </>
            }
        >
            <div className="grid gap-5 py-2">
                <fieldset className="grid gap-2">
                    <legend className="mb-2 text-sm font-medium">
                        میزان جسارت
                    </legend>
                    <div
                        role="radiogroup"
                        aria-label="میزان جسارت"
                        className="grid gap-2 sm:grid-cols-3"
                    >
                        {STRATEGIES.map((option) => {
                            const active = strategy === option.value;
                            return (
                                <button
                                    key={option.value}
                                    type="button"
                                    role="radio"
                                    aria-checked={active}
                                    onClick={() => setStrategy(option.value)}
                                    className={cn(
                                        "rounded-lg border p-3 text-start transition-colors outline-none focus-visible:ring-3 focus-visible:ring-ring/50",
                                        active
                                            ? "border-ring bg-accent/60"
                                            : "hover:border-ring/60",
                                    )}
                                >
                                    <span className="flex items-center gap-2 text-sm font-medium">
                                        <option.icon className="size-4" />
                                        {option.label}
                                    </span>
                                    <span className="text-muted-foreground mt-1 block text-xs leading-relaxed">
                                        {option.hint}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </fieldset>

                <div className="grid gap-2">
                    <Label htmlFor="tag-merge-term">
                        محدود به یک واژه{" "}
                        <span className="text-muted-foreground font-normal">
                            (اختیاری)
                        </span>
                    </Label>
                    <Input
                        id="tag-merge-term"
                        value={term}
                        onChange={(event) => setTerm(event.target.value)}
                        placeholder="مثلاً سونگون — خالی بگذارید تا کل واژه‌نامه بررسی شود"
                        disabled={isProposing}
                        onKeyDown={(event) => {
                            if (event.key === "Enter") {
                                event.preventDefault();
                                submit();
                            }
                        }}
                    />
                    <p className="text-muted-foreground text-xs">
                        با وارد کردن یک واژه، فقط برچسب‌های مرتبط با آن به مدل
                        داده می‌شوند؛ برای واژه‌نامه‌های بزرگ سریع‌تر و دقیق‌تر
                        است.
                    </p>
                </div>
            </div>
        </ResponsiveDialog>
    );
}
