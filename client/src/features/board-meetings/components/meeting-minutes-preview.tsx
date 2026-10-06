import { useState } from "react";
import { IconFileText } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/layout";

/**
 * The first lines of the minutes, with a way into the rest.
 *
 * The minutes are the reason the meeting exists, and they run to thousands of
 * words — which meant a reader who only wanted the gist had to scroll a tab to
 * find out there was anything there. A short read answers "is this worth my
 * time" in place, and the length next to it makes a suspiciously short import
 * obvious: an OCR scan that half-failed and a genuinely brief record look
 * identical on screen until you count the characters.
 *
 * The cut is on a paragraph boundary rather than a character count, so the
 * preview never ends mid-sentence — a preview that stops halfway through a
 * clause reads as the whole record being that clause.
 */
const PREVIEW_PARAGRAPHS = 2;

export function MeetingMinutesPreview({ minutes }: { minutes: string | null }) {
    const [expanded, setExpanded] = useState(false);

    const text = minutes?.trim() ?? "";

    if (text === "") {
        return (
            <EmptyState
                icon={IconFileText}
                message="صورتجلسه‌ای برای این جلسه ثبت نشده است"
            />
        );
    }

    const paragraphs = text
        .split(/\n{2,}/)
        .map((paragraph) => paragraph.trim())
        .filter((paragraph) => paragraph !== "");

    const preview = paragraphs.slice(0, PREVIEW_PARAGRAPHS).join("\n\n");
    const isTruncated = paragraphs.length > PREVIEW_PARAGRAPHS;

    return (
        <div className="flex flex-col gap-3">
            {/*
                No fade under the preview. The design system's scroll-fade
                utility is not installed in this project, and a hand-rolled mask
                gradient over Persian justified text hides exactly the last line
                a reader needs to judge the tone. A button that says what it
                does beats a decoration that hints at it.
            */}
            <p className="text-sm leading-7 whitespace-pre-line">
                {expanded ? text : preview}
            </p>

            {isTruncated && (
                <div className="text-muted-foreground flex items-center gap-2 text-xs">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setExpanded((open) => !open)}
                    >
                        {expanded ? "بستن به نمای کوتاه" : "خواندن کل صورتجلسه"}
                    </Button>
                    <span>
                        {(text.length - preview.length).toLocaleString("fa-IR")}{" "}
                        کاراکتر دیگر
                    </span>
                </div>
            )}
        </div>
    );
}
