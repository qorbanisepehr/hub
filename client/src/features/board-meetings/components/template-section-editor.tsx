import { useMemo } from "react";
import { IconChevronDown, IconChevronUp } from "@tabler/icons-react";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { cn } from "@/lib/utils";
import type { TemplateSectionDto } from "../section-templates-api";

export type { TemplateSectionDto };

/**
 * Which sections a document contains, in which order.
 *
 * ORDER IS THE TEMPLATE, so the list is edited in place: a section is in or out
 * by its checkbox, and its position by the arrows. There is no separate order
 * control because a list already has an order and a second field describing one
 * thing is two that can disagree.
 *
 * ARROWS, NOT DRAG. Drag-and-drop without a dependency means hand-rolling HTML5
 * DnD, which is unreliable on touch and invisible to a screen reader — so the
 * reordering that matters most would be the one some people cannot do. The app
 * already carries `@dnd-kit`, so this is a choice rather than a missing
 * dependency, and it is a choice worth being able to reverse.
 *
 * FULLY CONTROLLED, with no state of its own. An earlier draft kept the selection
 * locally and seeded it from the prop, which meant the ordering and the
 * checkboxes were reading two different lists and a re-order silently reverted
 * on the next change. The draft being edited belongs to whoever is saving it.
 *
 * Ticking a section back on puts it where it was rather than at the end, because
 * the selection is one ordered list rather than two to reconcile.
 */
export function TemplateSectionEditor({
    available,
    value,
    onChange,
    disabled,
}: {
    available: TemplateSectionDto[];
    /** The chosen keys, in the order they will print. */
    value: string[];
    onChange: (sections: string[]) => void;
    disabled?: boolean;
}) {
    /**
     * The chosen sections first, in their chosen order, then everything else in
     * the order the domain declared. So a person reading the list sees what will
     * actually print, from the top.
     */
    const rows = useMemo(() => {
        const byKey = new Map(available.map((s) => [s.key, s]));
        const chosen = value.filter((key) => byKey.has(key));

        return [
            ...chosen.map((key) => byKey.get(key)!),
            ...available.filter((s) => !value.includes(s.key)),
        ];
    }, [available, value]);

    const toggle = (key: string, on: boolean) =>
        onChange(
            on ? [...value, key] : value.filter((one) => one !== key),
        );

    /** Swaps a chosen section with its neighbour in the printed order. */
    const move = (key: string, direction: -1 | 1) => {
        const index = value.indexOf(key);
        const target = index + direction;

        if (index < 0 || target < 0 || target >= value.length) {
            return;
        }

        const next = [...value];
        [next[index], next[target]] = [next[target], next[index]];
        onChange(next);
    };

    return (
        <ul className="grid gap-1.5">
            {rows.map((section) => {
                const position = value.indexOf(section.key);
                const isChosen = position >= 0;

                return (
                    <li
                        key={section.key}
                        className={cn(
                            "flex items-center gap-2 rounded-lg border px-3 py-2 transition-colors",
                            isChosen ? "bg-accent/40" : "opacity-70",
                        )}
                    >
                        <Checkbox
                            id={`section-${section.key}`}
                            checked={isChosen}
                            disabled={disabled}
                            onCheckedChange={(checked) =>
                                toggle(section.key, checked === true)
                            }
                            aria-label={`بخش ${section.label}`}
                        />

                        <label
                            htmlFor={`section-${section.key}`}
                            className="flex-1 cursor-pointer text-sm select-none"
                        >
                            {section.label}
                        </label>

                        {isChosen && (
                            <span className="flex items-center">
                                {/* Disabled at the ends rather than hidden, so
                                    the row does not shift when the control
                                    cannot act. */}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    disabled={disabled || position === 0}
                                    onClick={() => move(section.key, -1)}
                                    aria-label={`بالا بردن ${section.label}`}
                                >
                                    <IconChevronUp className="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    disabled={
                                        disabled || position === value.length - 1
                                    }
                                    onClick={() => move(section.key, 1)}
                                    aria-label={`پایین بردن ${section.label}`}
                                >
                                    <IconChevronDown className="size-4" />
                                </Button>
                            </span>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
