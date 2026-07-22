import { Link } from "@tanstack/react-router";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import {
    IconArrowDownToArc,
    IconClipboard,
    IconFileDescription,
    IconFiles,
    IconFolder,
    IconHeadset,
    IconLayoutDashboard,
    IconLogin,
    IconUserScan,
    IconUsersGroup,
} from "@tabler/icons-react";
import { Logo, LogoType } from "@/components/shared/logo";
import { useAuth } from "@/features/auth/useAuth";

const services = [
    {
        icon: IconFolder,
        title: "مدیریت اسناد",
        desc: "ذخیره، دسته‌بندی و جستجوی پیشرفته انواع اسناد سازمانی",
    },
    {
        icon: IconUsersGroup,
        title: "مدیریت پرسنل",
        desc: "ثبت و نگهداری اطلاعات جامع پرسنل و سوابق کاری",
    },
    {
        icon: IconFileDescription,
        title: "بایگانی دیجیتال",
        desc: "دیجیتال‌سازی و بایگانی هوشمند پرونده‌های فیزیکی",
    },
    {
        icon: IconUserScan,
        title: "احراز هویت امن",
        desc: "ورود امن با رمز یکبار مصرف یا رمز عبور برای کاربران مجاز",
    },
];

const reasons = [
    {
        icon: IconFiles,
        title: "دسترسی سریع",
        desc: "جستجوی پیشرفته و دسترسی آنی به اسناد و پرونده‌ها",
    },
    {
        icon: IconUsersGroup,
        title: "دسترسی سطح‌بندی شده",
        desc: "تعریف نقش‌های دسترسی متفاوت برای کاربران سازمان",
    },
    {
        icon: IconFileDescription,
        title: "بایگانی هوشمند",
        desc: "دسته‌بندی خودکار و بازیابی سریع اطلاعات بایگانی شده",
    },
];

export function HomePage() {
    const { isAuthenticated } = useAuth();

    return (
        <>
            {/* Hero */}
            <section className="relative flex flex-col items-center justify-center min-h-dvh overflow-hidden border-b bg-dot-grid pt-16">
                <div className="absolute inset-0 bg-brand-glow" />
                <div className="absolute inset-0 bg-linear-to-b from-background/0 via-background/0 to-background" />

                <div className="relative z-10 mx-auto max-w-3xl px-6 text-center">
                    <Logo className="mx-auto size-36 mb-6" />
                    <LogoType className="w-72 md:w-40 mx-auto" />

                    <p className="mt-6 text-lg text-muted-foreground max-w-md mx-auto">
                        سامانه مدیریت اسناد و پرونده‌های پرسنلی
                    </p>

                    <p className="mt-3 text-sm text-muted-foreground/70 max-w-lg mx-auto leading-relaxed">
                        بایگانی، دسته‌بندی و جستجوی هوشمند با دسترسی سطح‌بندی
                        شده و امنیت بالا
                    </p>

                    <div className="mt-10 flex flex-wrap items-center justify-center gap-4">
                        {isAuthenticated ? (
                            <Link to="/dashboard">
                                <Button
                                    size="lg"
                                    className="px-8 cursor-pointer"
                                >
                                    <IconLayoutDashboard className="ml-2 size-4" />
                                    ورود به داشبورد
                                </Button>
                            </Link>
                        ) : (
                            <Link to="/login">
                                <Button
                                    size="lg"
                                    className="px-8 cursor-pointer"
                                >
                                    ورود به سامانه
                                    <IconLogin className="mr-2 size-4" />
                                </Button>
                            </Link>
                        )}
                        <Button size="lg" variant="outline" className="px-8">
                            <IconHeadset className="ml-2 size-4" />
                            تماس با ما
                        </Button>
                        <Link to="/questionnaire">
                            <Button
                                size="lg"
                                variant="outline"
                                className="px-8 cursor-pointer"
                            >
                                <IconClipboard className="ml-2 size-4" />
                                پرسشنامه استخدامی
                            </Button>
                        </Link>
                    </div>
                </div>

                <div className="absolute bottom-8 left-1/2 -translate-x-1/2">
                    <IconArrowDownToArc className="size-6 rounded-full border border-muted-foreground/30 text-muted-foreground/50 animate-bounce" />
                </div>
            </section>

            {/* Stats */}
            <section className="border-b bg-muted/30">
                <div className="mx-auto max-w-6xl px-6 py-20">
                    <div className="grid gap-6 md:grid-cols-3">
                        {reasons.map((reason) => (
                            <div
                                key={reason.title}
                                className="flex items-start gap-4"
                            >
                                <div className="flex size-10 shrink-0 items-center justify-center rounded-lg border bg-background text-brand">
                                    <reason.icon className="size-5" />
                                </div>
                                <div>
                                    <p className="font-medium text-sm">
                                        {reason.title}
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground leading-relaxed">
                                        {reason.desc}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Services */}
            <section className="border-b bg-dot-grid">
                <div className="mx-auto max-w-6xl px-6 py-20">
                    <div className="text-center">
                        <p className="font-heading text-3xl font-bold">
                            قابلیت‌های سامانه
                        </p>
                        <p className="mt-3 text-muted-foreground max-w-lg mx-auto">
                            مجموعه‌ای از ابزارهای حرفه‌ای برای مدیریت اسناد و
                            اطلاعات پرسنلی
                        </p>
                    </div>

                    <div className="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        {services.map((service) => (
                            <Card
                                key={service.title}
                                className="group hover:shadow-md transition-all duration-300"
                            >
                                <CardHeader>
                                    <div className="flex size-11 items-center justify-center rounded-lg bg-brand/10 text-brand mb-2 group-hover:bg-brand group-hover:text-white transition-colors">
                                        <service.icon className="size-5" />
                                    </div>
                                    <CardTitle className="text-base">
                                        {service.title}
                                    </CardTitle>
                                    <CardDescription>
                                        {service.desc}
                                    </CardDescription>
                                </CardHeader>
                            </Card>
                        ))}
                    </div>
                </div>
            </section>

            {/* CTA */}
            <section className="border-b bg-muted/30">
                <div className="mx-auto max-w-3xl px-6 py-20 text-center">
                    <div className="rounded-xl bg-background border p-12 shadow-sm">
                        <p className="font-heading text-2xl font-bold">
                            شروع کنیم!
                        </p>
                        <p className="mt-3 text-muted-foreground max-w-md mx-auto">
                            برای دریافت اطلاعات بیشتر با واحد... تماس بگیرید
                        </p>
                        <div className="mt-8 flex flex-wrap items-center justify-center gap-4">
                            {isAuthenticated ? (
                                <Link to="/dashboard">
                                    <Button className="px-8">
                                        <IconLayoutDashboard className="ml-2 size-4" />
                                        ورود به داشبورد
                                    </Button>
                                </Link>
                            ) : (
                                <Link to="/login">
                                    <Button className="px-8">
                                        <IconLogin className="mr-2 size-4" />
                                        ورود به سامانه
                                    </Button>
                                </Link>
                            )}
                            <Button variant="outline" className="px-8">
                                <IconHeadset className="ml-2 size-4" />
                                تماس با ما
                            </Button>
                            <Link to="/questionnaire">
                                <Button variant="outline" className="px-8 cursor-pointer">
                                    <IconClipboard className="ml-2 size-4" />
                                    پرسشنامه استخدامی
                                </Button>
                            </Link>
                        </div>
                    </div>
                </div>
            </section>
        </>
    );
}
