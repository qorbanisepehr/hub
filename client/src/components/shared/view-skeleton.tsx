import { Skeleton } from "@/components/ui/skeleton";
import { PageLayout } from "@/components/shared/page-layout";

interface ViewSkeletonProps {
    columns?: 1 | 2;
    leftRows?: number;
    rightRows?: number;
}

export function ViewSkeleton({
    columns = 2,
    leftRows = 6,
    rightRows = 4,
}: ViewSkeletonProps) {
    return (
        <PageLayout>
            <div className="flex items-center justify-between">
                <div className="space-y-2">
                    <Skeleton className="h-8 w-56" />
                    <Skeleton className="h-4 w-32" />
                </div>
                <div className="flex items-center gap-2">
                    <Skeleton className="h-8 w-8 rounded-lg" />
                    <Skeleton className="h-8 w-24 rounded-lg" />
                </div>
            </div>

            {columns === 2 ? (
                <div className="grid gap-6 md:grid-cols-2">
                    <SkeletonCard rows={leftRows} />
                    <SkeletonCard rows={rightRows} />
                </div>
            ) : (
                <SkeletonCard rows={leftRows} />
            )}
        </PageLayout>
    );
}

function SkeletonCard({ rows }: { rows: number }) {
    return (
        <div className="rounded-lg border bg-card text-card-foreground shadow-sm">
            <div className="flex flex-col space-y-1.5 p-6">
                <Skeleton className="h-5 w-28" />
            </div>
            <div className="p-6 pt-0 space-y-0 divide-y">
                {Array.from({ length: rows }).map((_, i) => (
                    <div key={i} className="flex items-baseline gap-2 py-2">
                        <Skeleton className="h-4 w-24 shrink-0" />
                        <Skeleton className="h-4 w-32" />
                    </div>
                ))}
            </div>
        </div>
    );
}
