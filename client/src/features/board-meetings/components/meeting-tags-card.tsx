import { useState } from "react";
import { IconTag } from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxInputGroup,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxList,
} from "@/components/ui/combobox";
import { Skeleton } from "@/components/ui/skeleton";
import { usePermission } from "@/features/auth/components/permission-guard";
import { PERMISSIONS } from "@/lib/permissions";
import { useAttachTags, useTagOptions } from "@/features/tags/hooks";
import type { BoardMeetingTagDto } from "../types";

/**
 * The meeting's tags (K3): chip multi-select backed by the tag options
 * endpoint. Saving posts the full slug set (sync semantics — tags removed
 * from the chip list are detached on the server).
 */
export function MeetingTagsCard({
    meetingId,
    tags,
}: {
    meetingId: string;
    tags: BoardMeetingTagDto[];
}) {
    const canManage = usePermission([PERMISSIONS.TAG_MANAGE]);
    const attach = useAttachTags();

    const [selected, setSelected] = useState<string[]>(
        tags.map((tag) => tag.slug),
    );
    const [search, setSearch] = useState("");

    const { data: options = [], isLoading: optionsLoading } =
        useTagOptions(undefined);

    // Keep newly-entered slugs that are not yet in the options list visible.
    const knownBySlug = new Map(options.map((tag) => [tag.slug, tag]));
    for (const tag of tags) {
        if (!knownBySlug.has(tag.slug)) {
            knownBySlug.set(tag.slug, {
                id: 0,
                name: tag.name,
                slug: tag.slug,
            });
        }
    }
    const allOptions = [...knownBySlug.values()];

    const filtered = search.trim() === ""
        ? allOptions
        : allOptions.filter((tag) => tag.name.includes(search.trim()));

    const nameBySlug = new Map(allOptions.map((tag) => [tag.slug, tag.name]));

    function toggle(slug: string) {
        setSelected((prev) =>
            prev.includes(slug)
                ? prev.filter((s) => s !== slug)
                : [...prev, slug],
        );
    }

    function save(slugs: string[]) {
        setSelected(slugs);
        attach.mutate({
            taggable_type: "board-meeting",
            taggable_id: meetingId,
            slugs,
        });
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-lg">
                    <IconTag className="size-5" />
                    برچسب‌ها
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                {canManage ? (
                    <>
                        <Combobox
                            multiple
                            value={selected}
                            onValueChange={(value: string[]) => save(value)}
                        >
                            <ComboboxInputGroup>
                                <ComboboxInput
                                    onChange={(e) =>
                                        setSearch(e.target.value)
                                    }
                                    placeholder="افزودن برچسب…"
                                />
                            </ComboboxInputGroup>
                            <ComboboxContent>
                                <ComboboxList>
                                    {optionsLoading ? (
                                        <div className="space-y-1 p-1">
                                            <Skeleton className="h-6 w-full" />
                                            <Skeleton className="h-6 w-2/3" />
                                        </div>
                                    ) : (
                                        filtered.map((tag) => (
                                            <ComboboxItem
                                                key={tag.slug}
                                                value={tag.slug}
                                                onSelect={(event) => {
                                                    event.preventDefault();
                                                    toggle(tag.slug);
                                                }}
                                            >
                                                {tag.name}
                                                <ComboboxItemIndicator />
                                            </ComboboxItem>
                                        ))
                                    )}
                                    <ComboboxEmpty>
                                        برچسبی یافت نشد
                                    </ComboboxEmpty>
                                </ComboboxList>
                            </ComboboxContent>
                        </Combobox>

                        <div className="flex flex-wrap items-center gap-1.5">
                            {selected.length > 0 ? (
                                selected.map((slug) => (
                                    <Badge
                                        key={slug}
                                        variant="outline"
                                    >
                                        {nameBySlug.get(slug) ?? slug}
                                    </Badge>
                                ))
                            ) : (
                                <span className="text-sm text-muted-foreground">
                                    برچسبی انتخاب نشده است
                                </span>
                            )}
                        </div>

                        {attach.isPending && (
                            <p className="text-xs text-muted-foreground">
                                در حال ذخیره…
                            </p>
                        )}
                    </>
                ) : (
                    <div className="flex flex-wrap gap-1">
                        {tags.length > 0 ? (
                            tags.map((tag) => (
                                <Badge key={tag.slug} variant="outline">
                                    {tag.name}
                                </Badge>
                            ))
                        ) : (
                            <span className="text-sm text-muted-foreground">
                                —
                            </span>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
