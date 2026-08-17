import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { FormTextField } from "@/components/forms";
import { AddressForm } from "@/components/forms";
import { zodFieldValidators } from "@/lib/validation-helpers";
import { optionalEmail } from "@/lib/field-rules";
import { fieldSchemas } from "@/features/questionnaire/schemas/contact-info.schema";
import type { EmployeeFormApi } from "@/features/employees/types";

type SectionProps = {
    form: EmployeeFormApi;
};

/**
 * Employee variant of the contact section: same fields as the questionnaire/CV
 * (email, mobile, landline, emergency phone and address) but WITHOUT the OTP
 * verification inputs — employees persist email/mobile directly to the real
 * columns on save, so the inputs are plain text fields. Email stays optional
 * while drafting; the submit schema enforces it.
 */
export function ContactInfoSection({ form }: SectionProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>اطلاعات تماس</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <form.Field
                    name="email"
                    validators={zodFieldValidators(optionalEmail())}
                >
                    {(field) => (
                        <FormTextField
                            field={field}
                            label="ایمیل"
                            placeholder="email@example.com"
                            dir="ltr"
                        />
                    )}
                </form.Field>

                <form.Field
                    name="mobile"
                    validators={zodFieldValidators(fieldSchemas.mobile)}
                >
                    {(field) => (
                        <FormTextField
                            field={field}
                            label="شماره موبایل"
                            placeholder="09121234567"
                            dir="ltr"
                        />
                    )}
                </form.Field>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <form.Field
                        name="contact_info.phone"
                        validators={zodFieldValidators(fieldSchemas.phone)}
                    >
                        {(field) => (
                            <FormTextField
                                field={field}
                                label="تلفن ثابت"
                                dir="ltr"
                            />
                        )}
                    </form.Field>
                    <form.Field
                        name="contact_info.emergency_phone"
                        validators={zodFieldValidators(
                            fieldSchemas.emergency_phone,
                        )}
                    >
                        {(field) => (
                            <FormTextField
                                field={field}
                                label="تلفن اضطراری"
                                dir="ltr"
                            />
                        )}
                    </form.Field>
                </div>

                <div>
                    <span className="text-sm font-medium mb-3 block">آدرس</span>
                    <AddressForm form={form} prefix="contact_info.address" />
                </div>
            </CardContent>
        </Card>
    );
}
