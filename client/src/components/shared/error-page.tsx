import { Link } from "@tanstack/react-router";
import {
    IconAlertTriangle,
    IconLock,
    IconSearch,
    IconServerOff,
} from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { PageLayout } from "@/components/shared/page-layout";

const STATUS_CONFIG: Record<
    number,
    {
        icon: typeof IconAlertTriangle;
        title: string;
        description: string;
    }
> = {
    403: {
        icon: IconLock,
        title: "دسترسی غیرمجاز",
        description:
            "شما مجوز کافی برای مشاهده این صفحه را ندارید. در صورت نیاز با مدیر سامانه تماس بگیرید.",
    },
    404: {
        icon: IconSearch,
        title: "صفحه یافت نشد",
        description: "آدرس وارد شده صحیح نیست یا صفحه مورد نظر حذف شده است.",
    },
    500: {
        icon: IconServerOff,
        title: "خطای سرور",
        description: "خطایی در سرور رخ داده است. لطفاً بعداً دوباره تلاش کنید.",
    },
};

const DEFAULT_CONFIG = {
    icon: IconAlertTriangle,
    title: "خطای ناشناخته",
    description: "خطایی رخ داده است. لطفاً بعداً دوباره تلاش کنید.",
};

interface ErrorPageProps {
    status?: number;
    title?: string;
    description?: string;
    homeTo?: string;
    homeLabel?: string;
}

export function ErrorPage({
    status,
    title: customTitle,
    description: customDescription,
    homeTo = "/dashboard",
    homeLabel = "بازگشت",
}: ErrorPageProps) {
    const config = status
        ? (STATUS_CONFIG[status] ?? DEFAULT_CONFIG)
        : DEFAULT_CONFIG;
    const Icon = config.icon;
    const title = customTitle ?? config.title;
    const description = customDescription ?? config.description;

    return (
        <PageLayout>
            <div className="flex flex-1 flex-col items-center justify-center gap-4 text-center py-16">
                <Icon className="size-16 text-muted-foreground/30" />
                <div className="space-y-2">
                    {status && (
                        <p className="text-sm font-medium text-muted-foreground">
                            {status}
                        </p>
                    )}
                    <h1 className="text-2xl font-bold tracking-tight">
                        {title}
                    </h1>
                    <p className="text-sm text-muted-foreground max-w-md">
                        {description}
                    </p>
                </div>
                <Button nativeButton={false} render={<Link to={homeTo} />}>
                    {homeLabel}
                </Button>
            </div>
        </PageLayout>
    );
}
