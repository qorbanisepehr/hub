import { Link } from "@tanstack/react-router";
import { IconCheck, IconHome } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { ViewSkeleton } from "@/components/shared/view-skeleton";
import { CvResumeView } from "@/features/cv/components/cv-resume-view";
import { useCvDocuments } from "@/features/cv/hooks/use-cv-documents";
import type { Cv } from "@/features/cv/types";
import { useState } from "react";

export function CvSuccessPage({ cv }: { cv: Cv }) {
    const { documents, isLoading: documentsLoading } = useCvDocuments(cv.uuid);
    const [successMessageOpen, setSuccessMessageOpen] = useState(false);

    return (
        <div className="min-h-screen bg-background pt-16">
            <div className="mx-auto max-w-4xl px-4 py-12">
                {successMessageOpen && (
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-center space-y-4">
                                <div className="mx-auto flex size-16 items-center justify-center rounded-full bg-primary/10">
                                    <IconCheck className="size-8 text-primary" />
                                </div>
                                <h1 className="text-2xl font-bold">
                                    رزومه ارسال شد
                                </h1>
                                <p className="text-muted-foreground">
                                    رزومه شما با موفقیت ثبت شد. کارشناسان ما پس
                                    از بررسی با شما تماس خواهند گرفت.
                                </p>
                                <div className="flex flex-col items-center justify-center gap-2 sm:flex-row">
                                    <Button
                                        nativeButton={false}
                                        render={<Link to="/" />}
                                    >
                                        <IconHome className="size-4" />
                                        <span>صفحه اصلی</span>
                                    </Button>
                                    <Button
                                        onClick={() =>
                                            setSuccessMessageOpen(false)
                                        }
                                    >
                                        بستن
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="mt-8">
                    <h2 className="mb-4 text-lg font-bold">پیش‌نمایش رزومه</h2>
                    {documentsLoading ? (
                        <ViewSkeleton columns={1} />
                    ) : (
                        <CvResumeView cv={cv} documents={documents} />
                    )}
                </div>
            </div>
        </div>
    );
}
