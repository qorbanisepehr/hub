import { Link } from "@tanstack/react-router";
import { IconCheck } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";

export function QuestionnaireSuccessPage() {
    return (
        <div className="min-h-screen bg-background">
            <div className="mx-auto max-w-2xl px-4 py-12">
                <Card>
                    <CardContent className="pt-6">
                        <div className="text-center space-y-4">
                            <div className="mx-auto flex size-16 items-center justify-center rounded-full bg-primary/10">
                                <IconCheck className="size-8 text-primary" />
                            </div>
                            <h1 className="text-2xl font-bold">پرسشنامه ثبت شد</h1>
                            <p className="text-muted-foreground">
                                پرسشنامه شما با موفقیت ثبت شد. کارشناسان ما پس از بررسی با شما تماس خواهند گرفت.
                            </p>
                            <Button render={<Link to="/" />}>
                                بازگشت به صفحه اصلی
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
