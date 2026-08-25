import { Fragment } from "react";

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from "@/components/ui/breadcrumb";
import { resolveBreadcrumbs } from "@/lib/breadcrumbs";
import { Link } from "@tanstack/react-router";
import { useRouterState } from "@tanstack/react-router";

/**
 * Location trail for the current route, derived from the shared nav
 * registry and the static page-crumb map — pages need no per-page wiring.
 */
export function AppBreadcrumb() {
    const pathname = useRouterState({
        select: (state) => state.location.pathname,
    });
    const crumbs = resolveBreadcrumbs(pathname);

    if (crumbs.length === 0) {
        return null;
    }

    return (
        <Breadcrumb>
            <BreadcrumbList>
                {crumbs.map((crumb, index) => {
                    const isLast = index === crumbs.length - 1;

                    return (
                        <Fragment
                            key={crumb.to ?? crumb.label}
                        >
                            {index > 0 && <BreadcrumbSeparator />}
                            <BreadcrumbItem>
                                {isLast || crumb.to === undefined ? (
                                    <BreadcrumbPage>
                                        {crumb.label}
                                    </BreadcrumbPage>
                                ) : (
                                    <BreadcrumbLink render={<Link to={crumb.to} />}>
                                        {crumb.label}
                                    </BreadcrumbLink>
                                )}
                            </BreadcrumbItem>
                        </Fragment>
                    );
                })}
            </BreadcrumbList>
        </Breadcrumb>
    );
}
