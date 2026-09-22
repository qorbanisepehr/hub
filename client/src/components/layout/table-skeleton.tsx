import { Skeleton } from "@/components/ui/skeleton";

export function TableSkeleton({ rows = 5 }: { rows?: number }) {
    return (
        <div className="space-y-2">
            {Array.from({ length: rows }).map((_, i) => (
                // oxlint-disable-next-line react/no-array-index-key -- static skeleton placeholders
                <Skeleton key={i} className="h-12 w-full" />
            ))}
        </div>
    );
}
