import { Skeleton } from "@/components/ui/skeleton";

/**
 * Loading skeleton for public candidate forms (cv / questionnaire). Mirrors
 * the wizard's visual structure: header card, step rail, then the section
 * form card — the same regions the wizard renders once data arrives.
 */
export function FormSkeleton() {
    return (
        <div className="space-y-8" aria-busy="true" aria-label="در حال بارگذاری فرم">
            {/* Header card */}
            <div className="rounded-xl border bg-card p-4 shadow-sm">
                <div className="flex items-start justify-between gap-4">
                    <div className="space-y-2">
                        <Skeleton className="h-6 w-28" />
                        <Skeleton className="h-4 w-40" />
                    </div>
                    <div className="flex items-center gap-2">
                        <Skeleton className="size-8 rounded-lg" />
                        <Skeleton className="size-8 rounded-lg" />
                        <Skeleton className="h-24 w-24 rounded-lg" />
                    </div>
                </div>
            </div>

            {/* Step rail */}
            <div className="grid grid-cols-3 gap-4 md:grid-cols-5 lg:grid-cols-9">
                {Array.from({ length: 9 }).map((_, i) => (
                    <div key={i} className="space-y-2">
                        <Skeleton className="h-1 w-full rounded-full" />
                        <Skeleton className="h-4 w-3/4" />
                    </div>
                ))}
            </div>

            {/* Section form card */}
            <div className="space-y-6 rounded-xl border bg-card p-6 shadow-sm">
                <div className="flex items-center justify-between">
                    <Skeleton className="h-5 w-32" />
                    <Skeleton className="h-8 w-24 rounded-lg" />
                </div>
                <div className="grid gap-6 md:grid-cols-2">
                    {Array.from({ length: 6 }).map((_, i) => (
                        <div key={i} className="space-y-2">
                            <Skeleton className="h-4 w-24" />
                            <Skeleton className="h-9 w-full rounded-lg" />
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
