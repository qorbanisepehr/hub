import { Skeleton } from "@/components/ui/skeleton";
import { PageLayout } from "@/components/shared/page-layout";

export function PageSkeleton() {
    return (
        <PageLayout>
            <Skeleton className="h-8 w-48" />
            <Skeleton className="h-64 w-full" />
        </PageLayout>
    );
}
