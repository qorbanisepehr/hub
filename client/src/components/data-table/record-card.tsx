import type { ReactNode } from "react";
import { Link } from "@tanstack/react-router";
import type { Icon } from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { cn } from "@/lib/utils";
import { RowActions, type RowAction } from "@/components/shared/row-actions";

type RecordCardBadge = {
    label: ReactNode;
    tone?: "default" | "secondary" | "outline" | "destructive" | "success" | "warning" | "info";
};

type RecordCardItem = {
    icon?: Icon;
    label: ReactNode;
};

/** Where a card leads. A card without a destination is a card that lies. */
export type RecordCardLink =
    | { to: string; params?: Record<string, string> }
    | { href: string };

type RecordCardProps = {
    title: ReactNode;
    subtitle?: ReactNode;
    icon?: Icon;
    badges?: RecordCardBadge[];
    items?: RecordCardItem[];
    link?: RecordCardLink;
    /** The row's own commands as data. Rendered behind a «...» menu so the
     *  card never offers less than the table row it stands for. */
    actions?: RowAction[];
    /** The menu itself, when the commands own state (a dialog). Built from
     *  the shared `RowActions` with `variant="menu"` by the page. */
    actionsMenu?: ReactNode;
    className?: string;
};

/**
 * The shared «کارتی» rendering of one list row (D15). Layout lives here, not
 * per page: a page supplies what the record says, never how the card looks,
 * so every list that offers a card view reads as the same product.
 *
 * The whole card is the affordance when `link` is set — no second control
 * that says the same thing. Row commands ride a «...» menu in the header so
 * the card carries the same operations as the table row.
 */
export function RecordCard({
    title,
    subtitle,
    icon: Icon,
    badges,
    items,
    link,
    actions,
    actionsMenu,
    className,
}: RecordCardProps) {
    const card = (
        <Card
            className={cn(
                "h-full gap-3",
                link && "transition-colors hover:border-ring",
                className,
            )}
        >
            <CardHeader className="gap-1.5">
                {/* Title and menu share one row and the title gives way; the
                    badges get a row of their own below. A long name or a user
                    with five roles must never push the «...» out of reach. */}
                <div className="flex items-start justify-between gap-2">
                    <CardTitle className="flex min-w-0 items-center gap-1.5 text-sm">
                        {Icon ? (
                            <Icon className="size-4 shrink-0 text-muted-foreground" />
                        ) : null}
                        <span className="truncate">{title}</span>
                    </CardTitle>
                    {actionsMenu ? (
                        actionsMenu
                    ) : actions?.length ? (
                        <RowActions
                            actions={actions}
                            variant="menu"
                            stopPropagation={Boolean(link)}
                        />
                    ) : null}
                </div>
                {badges?.length ? (
                    <div className="flex flex-wrap items-center gap-1">
                        {badges.map((badge, index) => (
                            <Badge
                                // oxlint-disable-next-line react/no-array-index-key -- static badge config; list never reorders
                                key={index}
                                variant={badge.tone ?? "outline"}
                            >
                                {badge.label}
                            </Badge>
                        ))}
                    </div>
                ) : null}
                {subtitle ? (
                    <p className="line-clamp-2 text-sm text-muted-foreground">
                        {subtitle}
                    </p>
                ) : null}
            </CardHeader>
            {items?.length ? (
                <CardContent className="mt-auto flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                    {items?.map((item, index) => (
                        <span
                            // oxlint-disable-next-line react/no-array-index-key -- static item config; list never reorders
                            key={index}
                            className="inline-flex items-center gap-1"
                        >
                            {item.icon ? <item.icon className="size-3.5" /> : null}
                            {item.label}
                        </span>
                    ))}
                </CardContent>
            ) : null}
        </Card>
    );

    if (!link) {
        return card;
    }

    const wrapperClass =
        "block rounded-xl outline-none focus-visible:ring-3 focus-visible:ring-ring/50";

    if ("href" in link) {
        return (
            <a href={link.href} className={wrapperClass}>
                {card}
            </a>
        );
    }

    return (
        <Link to={link.to} params={link.params} className={wrapperClass}>
            {card}
        </Link>
    );
}

/** The grid the shell expects inside `renderView` for a card mode. */
export function RecordCardGrid({ children }: { children: ReactNode }) {
    return (
        <div className="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
            {children}
        </div>
    );
}
