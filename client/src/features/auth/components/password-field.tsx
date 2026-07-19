import { useState } from "react";
import type { AnyFieldApi } from "@tanstack/react-form";
import { IconEye, IconEyeOff } from "@tabler/icons-react";

import { Field, FieldError, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";

type Props = {
    field: AnyFieldApi;
    label?: string;
    placeholder?: string;
    autoComplete?: string;
    showStrength?: boolean;
};

export function PasswordField({
    field,
    label = "رمز عبور",
    placeholder = "••••••••",
    autoComplete = "new-password",
    showStrength = false,
}: Props) {
    const [visible, setVisible] = useState(false);
    const isInvalid = field.state.meta.isTouched && !field.state.meta.isValid;

    return (
        <Field data-invalid={isInvalid}>
            <FieldLabel htmlFor={field.name}>{label}</FieldLabel>
            <div className="relative">
                <Input
                    id={field.name}
                    name={field.name}
                    type={visible ? "text" : "password"}
                    value={field.state.value}
                    onBlur={field.handleBlur}
                    onChange={(e) => field.handleChange(e.target.value)}
                    placeholder={placeholder}
                    dir="ltr"
                    autoComplete={autoComplete}
                    className="ps-7"
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-xs"
                    className="absolute inset-e-1 top-1 hover:bg-transparent rounded-full"
                    tabIndex={-1}
                    onClick={() => setVisible((v) => !v)}
                >
                    {visible ? (
                        <IconEyeOff className="size-4" />
                    ) : (
                        <IconEye className="size-4" />
                    )}
                </Button>
            </div>
            {showStrength && (
                <div className="mt-2">
                    <PasswordStrengthInline password={field.state.value} />
                </div>
            )}
            {isInvalid && <FieldError errors={field.state.meta.errors} />}
        </Field>
    );
}

function PasswordStrengthInline({ password }: { password: string }) {
    if (!password) return null;

    let score = 0;
    if (/[a-z]/.test(password)) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/\d/.test(password)) score++;
    if (/[^a-zA-Z0-9]/.test(password)) score++;
    if (password.length >= 8) score++;

    const levels = [
        { max: 1, label: "ضعیف", color: "bg-destructive" },
        { max: 2, label: "متوسط", color: "bg-orange-500" },
        { max: 3, label: "خوب", color: "bg-yellow-500" },
        { max: 4, label: "قوی", color: "bg-primary" },
        { max: 5, label: "بسیار قوی", color: "bg-emerald-500" },
    ];

    const level =
        levels.find((l) => score <= l.max) ?? levels[levels.length - 1];

    return (
        <div className="space-y-1.5">
            <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                <div
                    className={`h-full rounded-full transition-all ${level.color}`}
                    style={{ width: `${(score / 5) * 100}%` }}
                />
            </div>
            <p className="text-xs text-muted-foreground">
                قدرت: <span className="font-medium">{level.label}</span>
            </p>
        </div>
    );
}
