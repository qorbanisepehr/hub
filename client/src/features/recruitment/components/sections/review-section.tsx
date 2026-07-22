import type { ReactFormExtendedApi } from "@tanstack/react-form";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    FormTextField,
    FormTextarea,
} from "@/components/shared/form-fields";

type SectionProps = {
    form: ReactFormExtendedApi<any, any, any, any, any, any, any, any, any, any, any, any>;
};

export function ReviewSection({ form }: SectionProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>تأیید و ارسال</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>خلاصه اطلاعات وارد شده</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <span className="font-medium">نام:</span>{" "}
                                <span className="text-muted-foreground">
                                    {form.state.values.first_name}{" "}
                                    {form.state.values.last_name}
                                </span>
                            </div>
                            <div>
                                <span className="font-medium">کد ملی:</span>{" "}
                                <span className="text-muted-foreground">
                                    {form.state.values.personal_info?.national_id}
                                </span>
                            </div>
                            <div>
                                <span className="font-medium">جنسیت:</span>{" "}
                                <span className="text-muted-foreground">
                                    {form.state.values.personal_info?.gender}
                                </span>
                            </div>
                            <div>
                                <span className="font-medium">وضعیت تأهل:</span>{" "}
                                <span className="text-muted-foreground">
                                    {form.state.values.personal_info?.marital_status}
                                </span>
                            </div>
                            <div>
                                <span className="font-medium">تلفن:</span>{" "}
                                <span className="text-muted-foreground">
                                    {form.state.values.personal_info?.phone}
                                </span>
                            </div>
                            <div>
                                <span className="font-medium">تلفن اضطراری:</span>{" "}
                                <span className="text-muted-foreground">
                                    {form.state.values.personal_info?.emergency_phone}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>نظرات بررسی‌کننده</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <form.Field name="review.review_department">
                                {(field) => <FormTextField field={field} label="واحد بررسی" />}
                            </form.Field>
                            <form.Field name="review.reviewer_name">
                                {(field) => <FormTextField field={field} label="نام بررسی‌کننده" />}
                            </form.Field>
                            <form.Field name="review.reviewer_title">
                                {(field) => <FormTextField field={field} label="سمت بررسی‌کننده" />}
                            </form.Field>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <form.Field name="review.review_date">
                                {(field) => <FormTextField field={field} label="تاریخ بررسی" />}
                            </form.Field>
                        </div>
                        <form.Field name="review.initial_review_result">
                            {(field) => <FormTextarea field={field} label="نتیجه اولیه بررسی" />}
                        </form.Field>
                    </CardContent>
                </Card>
            </CardContent>
        </Card>
    );
}
