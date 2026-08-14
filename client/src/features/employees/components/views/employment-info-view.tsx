import type { ReactNode } from "react";

import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { SectionRow } from "@/components/shared/section-row";
import { LinkedUserRolesView } from "./linked-user-roles-view";
import {
    employmentLabels,
    statusLabels,
    statusVariants,
} from "@/features/employees/constants";
import type { Employee } from "@/features/employees/types";

type EmploymentInfoViewProps = {
    data: Record<string, unknown>;
    user?: Employee["user"] | null;
    title?: string;
    action?: ReactNode;
    extra?: ReactNode;
};

export function EmploymentInfoView({
    data,
    user,
    title = "اطلاعات شغلی",
    action,
    extra,
}: EmploymentInfoViewProps) {
    const employmentType = data.employment_type as string | undefined;
    const employmentStatus = data.employment_status as string | undefined;

    return (
        <div className="grid gap-6 md:grid-cols-2">
            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle>{title}</CardTitle>
                    {action}
                </CardHeader>
                <CardContent className="divide-y">
                    <SectionRow label="کد پرسنلی" value={data.personnel_code} />
                    <SectionRow
                        label="نوع استخدام"
                        value={
                            employmentType
                                ? (employmentLabels[employmentType] ??
                                  employmentType)
                                : "—"
                        }
                    />
                    <SectionRow label="تاریخ استخدام" value={data.hire_date} />
                    <SectionRow
                        label="وضعیت اشتغال"
                        value={
                            <Badge
                                variant={
                                    statusVariants[employmentStatus ?? ""] ??
                                    "secondary"
                                }
                            >
                                {statusLabels[employmentStatus ?? ""] ??
                                    employmentStatus}
                            </Badge>
                        }
                    />
                </CardContent>
                {extra}
            </Card>
            {user ? (
                <LinkedUserRolesView user={user} />
            ) : (
                <Card>
                    <CardHeader>
                        <CardTitle>نقش‌های کاربر</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-muted-foreground">
                            کاربری به این کارمند متصل نیست
                        </p>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
