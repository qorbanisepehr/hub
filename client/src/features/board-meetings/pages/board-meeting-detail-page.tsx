import { useState } from "react";
import { useParams } from "@tanstack/react-router";
import { getRouteApi } from "@tanstack/react-router";
import {
    IconFileDescription,
    IconFileText,
    IconPencil,
    IconRobot,
    IconTag,
    IconUsers,
} from "@tabler/icons-react";

import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from "@/components/ui/collapsible";
import { Skeleton } from "@/components/ui/skeleton";
import { Spinner } from "@/components/ui/spinner";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ErrorPage, PageHeader, PageLayout } from "@/components/layout";
import { toPersianDate } from "@/lib/date-format";
import { PERMISSIONS } from "@/lib/permissions";
import { MeetingDocumentsTab } from "../components/meeting-documents-tab";
import { MeetingEditDialog } from "../components/meeting-edit-dialog";
import { MeetingMinutesPreview } from "../components/meeting-minutes-preview";
import { MeetingStats } from "../components/meeting-stats";
import {
    useRunProgressChannel,
    useRunStates,
} from "@/features/realtime/use-run-progress-channel";

import { MeetingSummarySection } from "../components/meeting-summary-section";
import { MeetingTagChips } from "../components/meeting-tag-chips";
import { hasContent, isRunInFlight } from "../summary-read";
import {
    useAcceptMeetingSummary,
    useBoardMeeting,
    useDismissMeetingSummary,
    useGenerateMeetingSummary,
    useMeetingDocuments,
} from "../hooks";
import type {
    BoardMeetingDetailResponse,
    MeetingSummaryFieldName,
} from "../types";

const route = getRouteApi("/protected/board-meetings/$meetingId");

/**
 * The tab names, mirrored by the route's zod enum. Kept as a closed set here
 * too so the page can narrow `?tab=` to a value it knows how to render — the
 * route drops an unknown value, and a tab list that grew a name the enum forgot
 * would render an empty panel rather than fall back.
 */
const TABS = [
    "preview",
    "analysis",
    "agenda",
    "attendees",
    "documents",
] as const;

type Tab = (typeof TABS)[number];

function isTab(value: unknown): value is Tab {
    return TABS.includes(value as Tab);
}

/**
 * Board meeting detail.
 *
 * Reading order, top to bottom: who this meeting is, what is in it, then the
 * content. The identity bar and the stat row answer the first two without a
 * click, and only the content lives behind tabs — because tabs are for choosing
 * between *kinds* of content, and a reader who has to open each one to find out
 * whether it holds anything is doing the work the stat row should have done.
 *
 * Content-driven tabs, with the analysis tab as the deliberate exception: it is
 * the only one whose empty state is an invitation rather than an absence,
 * because asking is how its content arrives.
 *
 * Approvals and documents are one tab, not two, and it is always rendered:
 * a مصوبه's number is written onto its scan's usage metadata, so the
 * documents tab is where the meeting's registry numbers are read — and it is
 * the only place an upload can start (vault idea 567).
 *
 * Tags have no tab. A tag is a facet of the meeting rather than a section of
 * it — it answers "what is this about", which is the same question the title
 * answers — so the tags sit in the header as links into the filtered list, and
 * editing them happens in the edit dialog. A section of the page devoted to
 * one facet made a reader hunt for a control.
 *
 * The active tab rides the URL rather than component state, so a tab is
 * linkable, survives a refresh, and is what the back button steps through.
 */
export function BoardMeetingDetailPage() {
    const { meetingId } = useParams({
        from: "/protected/board-meetings/$meetingId",
    });
    const search = route.useSearch();
    const navigate = route.useNavigate();

    const [isEditing, setIsEditing] = useState(false);

    const { data: meeting, isLoading, isError } = useBoardMeeting(meetingId);

    // The document count is the meeting's scanned مصوبات — their numbers sit
    // on each usage's `metadata`, not on the row — so the stats tile and the
    // tab badge read the documents query, the same cache upload/delete
    // invalidate.
    const { data: documentsData } = useMeetingDocuments(String(meetingId));
    const documentCount = documentsData?.data.length ?? 0;

    const generate = useGenerateMeetingSummary(meetingId);
    const accept = useAcceptMeetingSummary(meetingId);
    const dismiss = useDismissMeetingSummary(meetingId);

    // The live run states for every meeting this user has in flight, so the
    // summary tab can be told which run is *this* one. Tracked per subject
    // rather than kept as a single "something is running" flag: the same user
    // may have a bulk going and come back to one meeting, and the two must not
    // be confused for each other.
    const { states: runStates, track: trackRun } = useRunStates();

    // Compared as a string because the route hands the id over as text while
    // the event carries whatever type the server serialised - the two are the
    // same meeting, and a strict `===` would silently never match.
    const run = runStates.get(`board-meeting:${meetingId}`) ?? null;

    // Live progress for the runs this user started. The channel hook only
    // invalidates the queries an event names; the states hook files them by
    // subject so this page can pick out its own meeting. `track` is stable
    // across renders, so passing it straight through does not tear the
    // subscription down and rebuild it on every render. Mounted here rather
    // than inside the summary section because this is the page that owns the
    // meeting's cache key, and the section receives the meeting as a prop and
    // has no business knowing it came from a query.
    useRunProgressChannel(trackRun);

    if (isLoading) {
        return (
            <PageLayout>
                <div className="flex flex-col gap-4">
                    <Skeleton className="h-10 w-64" />
                    <Skeleton className="h-16 w-full" />
                    <Skeleton className="h-40 w-full" />
                </div>
            </PageLayout>
        );
    }

    if (isError || !meeting) {
        return (
            <ErrorPage
                title="جلسه یافت نشد"
                homeTo="/board-meetings"
                homeLabel="بازگشت به جلسات"
            />
        );
    }

    const activeTab: Tab = isTab(search.tab) ? search.tab : "preview";

    function selectTab(tab: string) {
        if (!isTab(tab) || tab === activeTab) {
            return;
        }

        void navigate({ search: (prev) => ({ ...prev, tab }) });
    }

    return (
        <PageLayout>
            <PageHeader
                title={`جلسه ${toPersianDate(meeting.held_on)}`}
                description={meeting.title ?? meeting.registry_label}
                backTo="/board-meetings"
                actions={[
                    {
                        icon: <IconPencil />,
                        label: "ویرایش",
                        onClick: () => setIsEditing(true),
                        // The dialog itself also checks this, but the button
                        // must not offer an action the page will refuse.
                        permission: PERMISSIONS.BOARD_MEETING_UPDATE,
                    },
                ]}
            />

            <MeetingIdentity meeting={meeting} />

            <MeetingStats
                meeting={meeting}
                documentCount={documentCount}
                isLoading={false}
            />

            <Tabs value={activeTab} onValueChange={selectTab} className="gap-4">
                <TabsList
                    variant="line"
                    className="w-full justify-start overflow-x-auto overflow-y-clip"
                >
                    <TabsTrigger value="preview">
                        <IconFileText />
                        نمای کلی
                    </TabsTrigger>
                    <TabsTrigger value="analysis">
                        <IconRobot />
                        تحلیل هوشمند
                        {isRunInFlight(meeting.summary) && (
                            <Spinner className="size-3" />
                        )}
                    </TabsTrigger>
                    {/*
                    The documents tab is always rendered even when empty:
                    it is the only entry point for an upload.
                */}
                    <TabsTrigger value="documents">
                        <IconFileText />
                        اسناد و مصوبات ({documentCount.toLocaleString("fa-IR")})
                    </TabsTrigger>
                    {meeting.agenda.length > 0 && (
                        <TabsTrigger value="agenda">
                            <IconFileDescription />
                            دستور جلسه (
                            {meeting.agenda.length.toLocaleString("fa-IR")})
                        </TabsTrigger>
                    )}
                    {meeting.attendees.length > 0 && (
                        <TabsTrigger value="attendees">
                            <IconUsers />
                            حاضرین (
                            {meeting.attendee_count.toLocaleString("fa-IR")})
                        </TabsTrigger>
                    )}
                </TabsList>

                {/*
                    The overview carries a short read of the minutes rather than
                    all of them, and the full text lives in the documents tab
                    below as its own card — so the minutes are on the page
                    without being the page.
                */}
                <TabsContent value="preview">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">
                                خلاصهٔ صورتجلسه
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <MeetingMinutesPreview minutes={meeting.minutes} />
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="analysis">
                    <Card>
                        <CardContent className="pt-6">
                            <MeetingSummarySection
                                summary={meeting.summary}
                                tags={meeting.tags.map((tag) => tag.slug)}
                                run={run}
                                isGenerating={generate.isPending}
                                isAccepting={accept.isPending}
                                isDismissing={dismiss.isPending}
                                onGenerate={(payload) =>
                                    generate.mutate(payload)
                                }
                                onDismiss={() => dismiss.mutate()}
                                onAccept={(
                                    fields: MeetingSummaryFieldName[],
                                    tags: string[],
                                ) =>
                                    accept.mutate({
                                        accepted_fields: fields,
                                        tag_slugs: tags,
                                    })
                                }
                            />
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="documents">
                    <MeetingDocumentsTab meetingId={String(meetingId)} />
                </TabsContent>

                {meeting.agenda.length > 0 && (
                    <TabsContent value="agenda">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">
                                    دستور جلسه
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ol className="flex list-decimal flex-col gap-2 ps-5">
                                    {meeting.agenda.map((item) => (
                                        <li
                                            // Position alone is not an
                                            // identity: the archive files
                                            // sub-items under their parent's
                                            // number, so a real meeting can
                                            // have three items and two
                                            // positions. Position plus text is
                                            // stable across a re-order, which
                                            // an index is not.
                                            key={`${item.position}-${item.title}`}
                                            className="text-sm leading-7"
                                        >
                                            {item.title}
                                        </li>
                                    ))}
                                </ol>
                            </CardContent>
                        </Card>
                    </TabsContent>
                )}

                {meeting.attendees.length > 0 && (
                    <TabsContent value="attendees">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">
                                    حاضرین
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-wrap gap-2">
                                {meeting.attendees.map((attendee, index) => (
                                    // oxlint-disable-next-line react/no-array-index-key -- attendance entries have no stable identity
                                    <Badge key={index} variant="outline">
                                        {attendee.name}
                                    </Badge>
                                ))}
                            </CardContent>
                        </Card>
                    </TabsContent>
                )}
            </Tabs>

            <MeetingProvenance meeting={meeting} />

            <MeetingEditDialog
                open={isEditing}
                onOpenChange={setIsEditing}
                meetingId={String(meetingId)}
                title={meeting.title}
                minutes={meeting.minutes}
                tags={meeting.tags}
            />
        </PageLayout>
    );
}

/** Import provenance, collapsed: which source produced this row, and when. */
function MeetingProvenance({
    meeting,
}: {
    meeting: BoardMeetingDetailResponse;
}) {
    const provenance = meeting.provenance ?? {};
    const hasProvenance = Object.keys(provenance).length > 0;

    return (
        <Collapsible>
            <CollapsibleTrigger className="text-muted-foreground text-sm">
                منبع و جزئیات ایمپورت
            </CollapsibleTrigger>
            <CollapsibleContent>
                <div className="text-muted-foreground mt-2 grid gap-2 text-sm sm:grid-cols-3">
                    <div>
                        <p>منبع</p>
                        <p className="text-foreground" dir="ltr">
                            {meeting.origin}
                        </p>
                    </div>
                    <div>
                        <p>زمان ایمپورت</p>
                        <p className="text-foreground">
                            {meeting.imported_at ?? "—"}
                        </p>
                    </div>
                    <div>
                        <p>منبعِ داده</p>
                        <p className="text-foreground text-xs break-all">
                            {hasProvenance ? JSON.stringify(provenance) : "—"}
                        </p>
                    </div>
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}

/**
 * The meeting's identity, above everything and outside the tabs.
 *
 * Everything here answers "which meeting is this" rather than "what is in it",
 * which is why it stays put while the tabs change — the one thing a person must
 * never lose track of is which record they are reading. The source chips are
 * here rather than inside a tab because they answer "will the next sync touch
 * this row", which is a property of the record rather than of its contents.
 *
 * The tags are here, and they are links. They are the meeting's own facets, so
 * they belong beside its identity rather than in a section of their own, and
 * each one answers "which other meetings are about this?" in a click.
 */
function MeetingIdentity({ meeting }: { meeting: BoardMeetingDetailResponse }) {
    return (
        <div className="bg-muted/50- flex flex-wrap items-center gap-2 rounded-lg px-4 py-3 text-sm">
            <div className="flex w-full flex-wrap items-center justify-between gap-1">
                <div className="flex gap-1 items-center">
                    {/* <Badge variant="outline">
                        {meeting.registry_label}
                        </Badge>
                        <span className="text-muted-foreground" dir="ltr">
                        {meeting.origin}
                        </span> */}
                </div>
                <div className="flex gap-1 items-center">
                    {meeting.source_locked ? (
                        <Badge variant="outline">ویرایش‌شده توسط کاربر</Badge>
                    ) : (
                        <Badge variant="secondary">همگام با منبع</Badge>
                    )}
                    {isRunInFlight(meeting.summary) && (
                        <Badge variant="secondary">در حال تولید خلاصه</Badge>
                    )}
                    {hasContent(meeting.summary) && (
                        <Badge variant="success">خلاصه دارد</Badge>
                    )}
                </div>
            </div>

            <div className="flex w-full flex-wrap items-center justify-between gap-1 border-t border-dashed py-2">
                <div className="flex gap-1 items-center">
                    <IconTag className="text-muted-foreground size-4" />
                    <span className="text-muted-foreground text-xs">
                        برچسب‌ها
                    </span>
                </div>
                <MeetingTagChips tags={meeting.tags} />
            </div>
        </div>
    );
}
