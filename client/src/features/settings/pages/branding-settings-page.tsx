import { useForm, useStore } from "@tanstack/react-form";
import { z } from "zod";
import { toast } from "sonner";
import {
    IconChecks,
    IconLoader2,
    IconPhoto,
    IconRefresh,
    IconShare,
    IconWorld,
} from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { UnsavedChangesDialog } from "@/components/layout";
import {
    FormColorField,
    FormTextField,
} from "@/components/forms";
import { ImageUpload } from "@/components/shared/image-upload";
import { Logo, LogoType } from "@/components/navigation";
import type { BrandingImageKind } from "@/features/settings/api";
import type { BrandingSettings } from "@/features/settings/types";
import {
    useBranding,
    useBrandingImage,
    useUpdateBranding,
} from "@/features/settings/hooks/use-branding";
import { getApiError } from "@/lib/error-utils";
import { zodFieldValidators } from "@/lib/validation-helpers";
import { COMPANY_NAME } from "@/lib/brand";

const brandingSchema = z.object({
    name: z
        .string()
        .trim()
        .min(1, "نام برند الزامی است")
        .max(100, "حداکثر ۱۰۰ کاراکتر"),
    sub_name: z.string().trim().max(100, "حداکثر ۱۰۰ کاراکتر"),
    primary_color: z
        .string()
        .trim()
        .regex(/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/, "کد رنگ نامعتبر است"),
    secondary_color: z
        .string()
        .trim()
        .regex(/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/, "کد رنگ نامعتبر است"),
});

type BrandingFormValues = z.infer<typeof brandingSchema>;

const IMAGE_SLOTS: {
    kind: BrandingImageKind;
    label: string;
    description: string;
    url: (branding: BrandingSettings) => string | null;
    placeholder: React.ReactNode;
    containerClassName: string;
}[] = [
    {
        kind: "logo",
        label: "آرم",
        description: "نماد برند؛ در کنار لوگوتیپ و کد QR نمایش داده می‌شود.",
        url: (branding) => branding.logo_url,
        placeholder: <Logo className="size-16" />,
        containerClassName: "size-44 rounded-2xl",
    },
    {
        kind: "logotype",
        label: "لوگوتیپ",
        description: "نام برند به‌صورت تصویری؛ در هدر و صفحات عمومی.",
        url: (branding) => branding.logotype_url,
        placeholder: <LogoType className="w-32" />,
        containerClassName: "size-44 rounded-2xl",
    },
    {
        kind: "favicon",
        label: "فاوآیکون",
        description: "آیکون تب مرورگر (ترجیحاً مربعی، PNG یا SVG).",
        url: (branding) => branding.favicon_url,
        placeholder: <IconWorld className="size-12 text-brand" />,
        containerClassName: "size-20 rounded-xl",
    },
    {
        kind: "og_image",
        label: "تصویر اشتراک‌گذاری",
        description: "پیش‌نمایش لینک در شبکه‌های اجتماعی (ترجیحاً ۱۲۰۰×۶۳۰).",
        url: (branding) => branding.og_image_url,
        placeholder: <IconShare className="size-12 text-brand" />,
        containerClassName: "size-44 rounded-2xl md:w-full",
    },
];

type BrandingImageSlotProps = {
    kind: BrandingImageKind;
    label: string;
    description: string;
    url: string | null;
    placeholder: React.ReactNode;
    containerClassName: string;
};

function BrandingImageSlot({
    kind,
    label,
    description,
    url,
    placeholder,
    containerClassName,
}: BrandingImageSlotProps) {
    const { onUpload, onDelete, isPending } = useBrandingImage(kind);

    return (
        <div className="space-y-2">
            <div>
                <p className="text-sm font-medium">{label}</p>
                <p className="text-xs text-muted-foreground">{description}</p>
            </div>
            <ImageUpload
                url={url}
                alt={label}
                isPending={isPending}
                onUpload={onUpload}
                onDelete={onDelete}
                containerClassName={containerClassName}
                fit="contain"
                accept="image/jpeg,image/png,image/webp,image/svg+xml,image/x-icon,.jpg,.jpeg,.png,.webp,.svg,.ico"
                acceptedTypes={[
                    "image/jpeg",
                    "image/png",
                    "image/webp",
                    "image/svg+xml",
                    "image/x-icon",
                    "image/vnd.microsoft.icon",
                ]}
                typeErrorMessage="فقط تصاویر JPG، PNG، WebP، SVG یا ICO مجاز هستند."
                sizeErrorMessage="حجم فایل نباید بیشتر از ۲ مگابایت باشد."
                deleteLabel="حذف تصویر"
                deleteConfirmLabel="حذف"
                hoverOverlay={
                    <>
                        <IconPhoto className="size-5 text-white" />
                        <IconRefresh className="size-4 text-white" />
                    </>
                }
                fallback={
                    <>
                        {placeholder}
                        <span className="text-xs">آپلود تصویر</span>
                    </>
                }
            />
        </div>
    );
}

export function BrandingSettingsSection() {
    const { data: branding } = useBranding();
    const updateMutation = useUpdateBranding();

    const form = useForm({
        defaultValues: {
            name: branding?.name ?? COMPANY_NAME,
            sub_name: branding?.sub_name ?? "",
            primary_color: branding?.primary_color ?? "#db7868",
            secondary_color: branding?.secondary_color ?? "#1c2538",
        } as BrandingFormValues,
        validators: {
            onSubmit: brandingSchema,
        },
        onSubmit: async ({ value }) => {
            try {
                await updateMutation.mutateAsync(value);
                toast.success("برندینگ با موفقیت ذخیره شد");
                form.reset(value);
            } catch (err) {
                toast.error(getApiError(err));
            }
        },
    });

    const isDirty = useStore(form.store, (state) => state.isDirty);

    return (
        <div className="space-y-6">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    form.handleSubmit();
                }}
                className="space-y-6"
            >
                <UnsavedChangesDialog
                    isDirty={isDirty}
                    isSubmitting={updateMutation.isPending}
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            مشخصات برند
                        </CardTitle>
                        <CardDescription>
                            نام برند در عنوان صفحه و بخش‌های مستقل متن نمایش
                            داده می‌شود؛ زیرنام زیر لوگوتیپ ظاهر می‌شود.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <form.Field
                            name="name"
                            validators={zodFieldValidators(
                                brandingSchema.shape.name,
                            )}
                        >
                            {(field) => (
                                <FormTextField
                                    field={field}
                                    label="نام برند"
                                    placeholder="نام سازمان یا شرکت"
                                />
                            )}
                        </form.Field>

                        <form.Field
                            name="sub_name"
                            validators={zodFieldValidators(
                                brandingSchema.shape.sub_name,
                            )}
                        >
                            {(field) => (
                                <FormTextField
                                    field={field}
                                    label="زیرنام"
                                    placeholder="مثلا: مدیریت اسناد و پرونده‌های پرسنلی"
                                />
                            )}
                        </form.Field>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            رنگ‌های برند
                        </CardTitle>
                        <CardDescription>
                            رنگ اصلی و ثانویه برند در آرم پیش‌فرض، کد QR و
                            بخش‌های مرتبط با برند در سراسر سامانه استفاده
                            می‌شود.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <form.Field
                                name="primary_color"
                                validators={zodFieldValidators(
                                    brandingSchema.shape.primary_color,
                                )}
                            >
                                {(field) => (
                                    <FormColorField
                                        field={field}
                                        label="رنگ اصلی"
                                        hint="کد هگزادسیمال مانند #db7868"
                                    />
                                )}
                            </form.Field>

                            <form.Field
                                name="secondary_color"
                                validators={zodFieldValidators(
                                    brandingSchema.shape.secondary_color,
                                )}
                            >
                                {(field) => (
                                    <FormColorField
                                        field={field}
                                        label="رنگ ثانویه"
                                        hint="کد هگزادسیمال مانند #1c2538"
                                    />
                                )}
                            </form.Field>
                        </div>
                    </CardContent>
                </Card>

                <div className="flex items-center gap-2">
                    <Button
                        type="submit"
                        disabled={updateMutation.isPending || !isDirty}
                    >
                        {updateMutation.isPending ? (
                            <IconLoader2 className="size-4 animate-spin ms-1" />
                        ) : (
                            <IconChecks className="size-4 ms-1" />
                        )}
                        ذخیره تغییرات
                    </Button>
                </div>
            </form>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">تصاویر برند</CardTitle>
                    <CardDescription>
                        آرم، لوگوتیپ، فاوآیکون و تصویر اشتراک‌گذاری را
                        بارگذاری کنید؛ تا زمان بارگذاری، نمونه پیش‌فرض نمایش
                        داده می‌شود.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        {IMAGE_SLOTS.slice(0, 3).map((slot) => (
                            <BrandingImageSlot
                                key={slot.kind}
                                kind={slot.kind}
                                label={slot.label}
                                description={slot.description}
                                url={branding ? slot.url(branding) : null}
                                placeholder={slot.placeholder}
                                containerClassName={slot.containerClassName}
                            />
                        ))}
                    </div>
                    <div className="mt-6">
                        <BrandingImageSlot
                            kind={IMAGE_SLOTS[3].kind}
                            label={IMAGE_SLOTS[3].label}
                            description={IMAGE_SLOTS[3].description}
                            url={branding ? IMAGE_SLOTS[3].url(branding) : null}
                            placeholder={IMAGE_SLOTS[3].placeholder}
                            containerClassName={IMAGE_SLOTS[3].containerClassName}
                        />
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
