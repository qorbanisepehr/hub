import { Link } from "@tanstack/react-router";
import { Button } from "@/components/ui/button";
import { IconLayoutDashboard, IconLogin, IconHeadset, IconClipboard } from "@tabler/icons-react";

type LandingCTAProps = {
    isAuthenticated: boolean;
    variant?: "hero" | "bottom";
};

export function LandingCTA({ isAuthenticated, variant = "bottom" }: LandingCTAProps) {
    if (variant === "hero") {
        return (
            <div className="mt-10 flex flex-wrap items-center justify-center gap-4">
                {isAuthenticated ? (
                    <Link to="/dashboard">
                        <Button size="lg" className="px-8 cursor-pointer">
                            <IconLayoutDashboard className="ml-2 size-4" />
                            ورود به داشبورد
                        </Button>
                    </Link>
                ) : (
                    <Link to="/login">
                        <Button size="lg" className="px-8 cursor-pointer">
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
                    <Button size="lg" variant="outline" className="px-8 cursor-pointer">
                        <IconClipboard className="ml-2 size-4" />
                        پرسشنامه استخدامی
                    </Button>
                </Link>
            </div>
        );
    }

    return (
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
    );
}
