import { IconHash } from "@tabler/icons-react";
import { useNavigate } from "@tanstack/react-router";

import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";
import type { BoardMeetingTagDto } from "../types";

/**
 * The meeting's tags, each one a link into the filtered list.
 *
 * Clickable because "which other meetings are about this?" is the question a tag
 * raises, and the answer is one click away rather than a trip to the filter bar
 * to retype a slug. The target is the list's own URL state, so it is a real
 * link: middle-click and open-in-new-tab work, and the back button steps out of
 * the filter the way a person expects.
 *
 * Read-only by design. Editing a meeting's tagging happens in the edit dialog,
 * where the change is submitted deliberately; a chip that silently rewrote the
 * record on click would make a navigation into an edit, and there is no undo
 * on a tag list.
 */
export function MeetingTagChips({
    tags,
    className,
}: {
    tags: BoardMeetingTagDto[];
    className?: string;
}) {
    const navigate = useNavigate();

    if (tags.length === 0) {
        return (
            <span className="text-muted-foreground text-sm">بدون برچسب</span>
        );
    }

    return (
        <div className={cn("flex flex-wrap items-center gap-1.5", className)}>
            {tags.map((tag) => (
                // A real <button> around the badge rather than the badge's
                // `render` prop: the badge is a span, and a button inside a
                // button is invalid HTML that a screen reader and a keyboard
                // both handle badly.
                <button
                    key={tag.slug}
                    type="button"
                    // Replace rather than push: arriving at a filtered list
                    // and then needing two presses of back — once out of the
                    // list, once out of the meeting — is the wrong number of
                    // steps for "show me more of these".
                    onClick={() => {
                        void navigate({
                            to: "/board-meetings",
                            search: { tags: [tag.slug] },
                        });
                    }}
                    title={`نمایش جلسات دیگر با برچسب ${tag.name}`}
                    className="cursor-pointer h-6"
                >
                    <Badge
                        variant="outline"
                        className="rounded px-0! py-0! h-6 relative [&>svg]:size-6! pe-2! overflow-clip border-b-2 gap-0"
                    >
                        <IconHash
                            // data-icon="inline-start"
                            className="p-1.5 text-primary/50"
                        />
                        {/* <span className="absolute rounded bg-primary h-4 w-1 top-1 right-1 inline-flex" /> */}
                        {tag.name}
                    </Badge>
                </button>
            ))}
        </div>
    );
}
