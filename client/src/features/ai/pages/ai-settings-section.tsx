import type { ReactNode } from "react";
import { useMemo, useState } from "react";
import { getRouteApi } from "@tanstack/react-router";
import type { ColumnDef, Row, StockFeatures } from "@tanstack/react-table";
import {
    IconActivity,
    IconBrain,
    IconCheck,
    IconCircleOff,
    IconCpu,
    IconKey,
    IconPencil,
    IconPlugConnected,
    IconPlus,
    IconRefresh,
    IconRobot,
    IconSearch,
    IconSparkles,
    IconStar,
} from "@tabler/icons-react";
import { toast } from "sonner";

import {
    DataTablePage,
    RecordCard,
    RecordCardGrid,
    TABLE_AND_CARD_VIEWS,
    useListView,
} from "@/components/data-table";
import { RowActions, type RowAction } from "@/components/shared/row-actions";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
    useDataTable,
    useDataTableUrlState,
} from "@/hooks/use-data-table-page";
import {
    useAiAgents,
    useAiModels,
    useAiProviders,
    useDeleteAiProvider,
    useMakeAiProviderDefault,
    useTestAiProvider,
    useUpdateAiAgentBinding,
} from "@/features/ai/hooks";
import { AgentRunBadge } from "@/features/ai/components/agent-run-badge";
import { AiActivityDialog } from "@/features/ai/components/ai-activity-dialog";
import { AiAgentDialog } from "@/features/ai/pages/ai-agent-dialog";
import { AiProviderDialog } from "@/features/ai/pages/ai-provider-dialog";
import { AiProviderModelsDialog } from "@/features/ai/pages/ai-provider-models-dialog";
import {
    AI_CAPABILITIES,
    AI_DRIVERS,
    type AiAgentDto,
    type AiModelDto,
    type AiProviderDto,
} from "@/features/ai/types";
import { usePermission } from "@/features/auth/components/permission-guard";
import { useRunProgressChannel } from "@/features/realtime/use-run-progress-channel";
import { useAgentActivity } from "@/lib/run-activity";
import { getApiError } from "@/lib/error-utils";
import { PERMISSIONS } from "@/lib/permissions";

const aiRoute = getRouteApi("/protected/settings/ai");

/** The tab a person is looking at, kept in the URL so a refresh stays put. */
type AiTab = "providers" | "models" | "agents";

const isAiTab = (value: unknown): value is AiTab =>
    value === "providers" || value === "models" || value === "agents";

const driverLabel = (driver: string): string =>
    AI_DRIVERS.find((d) => d.value === driver)?.label ?? driver;

const isKeyless = (driver: string): boolean =>
    AI_DRIVERS.find((d) => d.value === driver)?.keyless === true;

const capabilityLabel = (capability: string): string =>
    AI_CAPABILITIES.find((c) => c.value === capability)?.label ?? capability;

/**
 * The AI console (R3): bring-your-own-key providers, their models, and what
 * each registered agent is bound to.
 *
 * Three tabs rather than one long page, because these are three questions a
 * person arrives with — "is my gateway reachable", "which models may I pick",
 * "what does each agent use" — and a single scroll mixed them. The tab lives
 * in the URL (`?tab=`), so a link into a problem keeps pointing at it.
 *
 * The models tab is the flat, registry-wide list rather than only a
 * per-provider drawer. A model's identity is the (provider, name) pair, so
 * "where is model X" is a question the list has to be able to answer; the
 * drawer remains the place a provider's set is edited.
 *
 * Each table owns its own search keys, since they share the URL: with the
 * default `page`/`filter` both tables would write to the same parameters and
 * page one while paging the other.
 */
export function AiSettingsSection() {
    const canManage = usePermission([PERMISSIONS.AI_PROVIDER_MANAGE]);

    const search = aiRoute.useSearch() as Record<string, unknown> & {
        tab?: unknown;
    };
    const navigate = aiRoute.useNavigate();

    const tab: AiTab = isAiTab(search.tab) ? search.tab : "providers";
    const selectTab = (next: string) => {
        if (!isAiTab(next) || next === tab) {
            return;
        }

        void navigate({ search: (prev) => ({ ...prev, tab: next }) });
    };

    const providersUrl = useDataTableUrlState({
        search,
        navigate,
        urlState: {
            globalFilter: { enabled: true, key: "filter" },
        },
    });

    const providersView = useListView({ search, navigate, key: "view" });
    const agentsView = useListView({ search, navigate, key: "ai_view" });

    const agentsUrl = useDataTableUrlState({
        search,
        navigate,
        urlState: {
            pagination: { pageKey: "ai_page", pageSizeKey: "ai_per_page" },
            sorting: { sortKey: "ai_sort", orderKey: "ai_order" },
            globalFilter: { enabled: true, key: "ai_filter" },
        },
    });

    const {
        data: providersData,
        isLoading: providersLoading,
        isError: providersError,
        isFetching: providersFetching,
        refetch: refetchProviders,
    } = useAiProviders({
        page: providersUrl.pagination.pageIndex + 1,
        per_page: providersUrl.pagination.pageSize,
        sort: providersUrl.activeSort?.id,
        order: providersUrl.activeSort?.desc ? "desc" : "asc",
        filter: providersUrl.globalFilter || undefined,
    });

    // The flat list, for the models tab only: the providers tab already
    // carries its models nested, and a list nobody is looking at is not worth
    // a request.
    const {
        data: modelsData,
        isLoading: modelsLoading,
        isError: modelsError,
        isFetching: modelsFetching,
        refetch: refetchModels,
    } = useAiModels(tab === "models");

    const {
        data: agentsData,
        isLoading: agentsLoading,
        isError: agentsError,
        isFetching: agentsFetching,
        refetch: refetchAgents,
    } = useAiAgents({
        page: agentsUrl.pagination.pageIndex + 1,
        per_page: agentsUrl.pagination.pageSize,
        sort: agentsUrl.activeSort?.id,
        order: agentsUrl.activeSort?.desc ? "desc" : "asc",
        filter: agentsUrl.globalFilter || undefined,
    });

    const removeProvider = useDeleteAiProvider();
    const makeDefault = useMakeAiProviderDefault();
    const bindAgent = useUpdateAiAgentBinding();
    const test = useTestAiProvider();

    const [providerDialog, setProviderDialog] = useState<{
        open: boolean;
        provider: AiProviderDto | null;
    }>({ open: false, provider: null });
    const [modelsProvider, setModelsProvider] = useState<AiProviderDto | null>(
        null,
    );
    const [bindingAgent, setBindingAgent] = useState<AiAgentDto | null>(null);

    const openCreateProvider = () =>
        setProviderDialog({ open: true, provider: null });

    const openEditProvider = (provider: AiProviderDto) =>
        setProviderDialog({ open: true, provider });

    // The run each row is waiting on, so the row keeps its spinner until the
    // *answer* arrives and a second press on it is refused while one is out.
    const [testingRuns, setTestingRuns] = useState<
        ReadonlyMap<string, string>
    >(new Map());

    const settleRun = (provider: string) =>
        setTestingRuns((previous) => {
            const next = new Map(previous);

            next.delete(provider);

            return next;
        });

    // The probe's outcome comes back as a run event, not as the POST's
    // response - the POST only says the job was queued. Reporting success on
    // it is what once made an unreachable gateway look reachable.
    useRunProgressChannel((progress) => {
        if (
            progress.channel !== "ai" ||
            progress.subject?.type !== "ai-provider"
        ) {
            return;
        }

        const provider = String(progress.subject.id);

        if (testingRuns.get(provider) !== progress.run_id) {
            return;
        }

        const reason = progress.context["reason"];
        const model = progress.context["model"];

        if (progress.state === "finished") {
            toast.success(
                `ارتباط برقرار شد${typeof model === "string" ? ` — مدل ${model}` : ""}`,
            );
            settleRun(provider);
        } else if (progress.state === "failed") {
            // The vendor's own reason is the whole point of the button, so it
            // is shown verbatim rather than replaced with a generic
            // "connection failed". The queued job falls back to its own copy
            // when the worker died without one.
            toast.error(
                typeof reason === "string" && reason !== ""
                    ? reason
                    : "ارتباط با ارائه‌دهنده برقرار نشد.",
            );
            settleRun(provider);
        }
    }, "ai");

    const handleTest = (provider: AiProviderDto) => {
        test.mutate(
            { name: provider.name },
            {
                onSuccess: (runId) =>
                    setTestingRuns((previous) =>
                        new Map(previous).set(provider.name, runId),
                    ),
                onError: (error) => toast.error(getApiError(error)),
            },
        );
    };

    const isTesting = (provider: AiProviderDto): boolean =>
        (test.isPending && test.variables?.name === provider.name) ||
        testingRuns.has(provider.name);

    // One declaration per row's commands: the table cell and the card menu
    // are the same menu, so a command cannot exist in one and not the other.
    const providerRowActions = (provider: AiProviderDto): RowAction[] => {
        const testing = isTesting(provider);

        return [
            {
                icon: <IconActivity className="size-4" />,
                label: "تست اتصال",
                onClick: () => handleTest(provider),
                disabled: testing,
                permission: PERMISSIONS.AI_PROVIDER_MANAGE,
            },
            {
                icon: <IconCpu className="size-4" />,
                label: "مدیریت مدل‌ها",
                onClick: () => setModelsProvider(provider),
                permission: PERMISSIONS.AI_PROVIDER_MANAGE,
            },
            {
                icon: <IconPencil className="size-4" />,
                label: "ویرایش",
                onClick: () => openEditProvider(provider),
                permission: PERMISSIONS.AI_PROVIDER_MANAGE,
            },
            ...(provider.is_default
                ? []
                : [
                      {
                          icon: <IconStar className="size-4" />,
                          label: "پیش‌فرض کردن",
                          onClick: () => makeDefault.mutate(provider.name),
                          disabled: makeDefault.isPending,
                          permission: PERMISSIONS.AI_PROVIDER_MANAGE,
                      } satisfies RowAction,
                  ]),
            {
                type: "confirm-delete",
                label: "حذف ارائه‌دهنده",
                message: `آیا از حذف ارائه‌دهنده «${provider.name}» اطمینان دارید؟`,
                onConfirm: () => removeProvider.mutate(provider.name),
                isPending: removeProvider.isPending || testing,
                permission: PERMISSIONS.AI_PROVIDER_MANAGE,
            },
        ];
    };

    // One declaration per agent row's commands, for the same reason as the
    // providers above: the card menu and the table cell are one menu.
    const agentRowActions = (agent: AiAgentDto): RowAction[] => [
        {
            icon: <IconPlugConnected className="size-4" />,
            label: "تغییر اتصال",
            onClick: () => setBindingAgent(agent),
            permission: PERMISSIONS.AI_PROVIDER_MANAGE,
        },
        ...(agent.is_bound
            ? [
                  {
                      icon: <IconCircleOff className="size-4" />,
                      label: "بازگشت به پیش‌فرض",
                      onClick: () =>
                          bindAgent.mutate({
                              key: agent.key,
                              payload: { provider: null, model: null },
                          }),
                      disabled: bindAgent.isPending,
                      permission: PERMISSIONS.AI_PROVIDER_MANAGE,
                  } satisfies RowAction,
              ]
            : []),
    ];

    const providers = providersData?.data ?? [];
    const modelRows = modelsData?.data ?? [];
    const agents = agentsData?.data ?? [];

    const defaultProvider = providers.find((provider) => provider.is_default);
    const activeProviders = providers.filter((provider) => provider.is_active);
    const modelCount = providers.reduce(
        (total, provider) => total + provider.models.length,
        0,
    );
    const boundAgents = agents.filter((agent) => agent.is_bound).length;
    const agentActivity = useAgentActivity();
    const runningAgents = Object.keys(agentActivity).length;
    const [activityOpen, setActivityOpen] = useState(false);

    const providerColumns: ColumnDef<StockFeatures, AiProviderDto>[] = [
        {
            accessorKey: "name",
            header: "نام",
            cell: ({ row }: { row: Row<StockFeatures, AiProviderDto> }) => (
                <div className="flex flex-wrap items-center gap-1.5">
                    <span dir="ltr" className="font-medium">
                        {row.original.name}
                    </span>
                    {row.original.is_default && (
                        <Badge variant="info">
                            <IconStar className="size-3" />
                            پیش‌فرض
                        </Badge>
                    )}
                    {!row.original.is_active && (
                        <Badge variant="outline">غیرفعال</Badge>
                    )}
                </div>
            ),
        },
        {
            accessorKey: "driver",
            header: "نوع",
            cell: ({ row }: { row: Row<StockFeatures, AiProviderDto> }) => (
                <span dir="ltr" className="text-muted-foreground text-sm">
                    {driverLabel(row.original.driver)}
                </span>
            ),
        },
        {
            id: "endpoint",
            header: "نشانی",
            enableSorting: false,
            cell: ({ row }: { row: Row<StockFeatures, AiProviderDto> }) => (
                <span
                    dir="ltr"
                    className="text-muted-foreground block max-w-56 truncate text-sm"
                >
                    {row.original.base_url ?? "پیش‌فرض"}
                </span>
            ),
        },
        {
            id: "credential",
            header: "کلید",
            enableSorting: false,
            cell: ({ row }: { row: Row<StockFeatures, AiProviderDto> }) =>
                // Only a missing key on a driver that NEEDS one is worth a
                // word: the zero-credential path (Ollama) is a working setup,
                // and a "بدون کلید" badge there read as a fault.
                row.original.has_api_key ? (
                    <span className="text-muted-foreground inline-flex items-center gap-1 text-sm">
                        <IconKey className="size-3.5" />
                        ذخیره شده
                    </span>
                ) : isKeyless(row.original.driver) ? (
                    <span className="text-muted-foreground text-sm">
                        لازم نیست
                    </span>
                ) : (
                    <Badge variant="warning">کلید ندارد</Badge>
                ),
        },
        {
            id: "models",
            header: "مدل‌ها",
            enableSorting: false,
            cell: ({ row }: { row: Row<StockFeatures, AiProviderDto> }) => (
                <button
                    type="button"
                    className="text-muted-foreground text-sm underline-offset-4 hover:underline"
                    onClick={() => setModelsProvider(row.original)}
                >
                    {row.original.models.length.toLocaleString("fa-IR")}
                </button>
            ),
        },
        ...(canManage
            ? [
                  {
                      id: "actions",
                      header: "",
                      enableSorting: false,
                      cell: ({
                          row,
                      }: {
                          row: Row<StockFeatures, AiProviderDto>;
                      }) => (
                          <RowActions
                              actions={providerRowActions(row.original)}
                          />
                      ),
                  } satisfies ColumnDef<StockFeatures, AiProviderDto>,
              ]
            : []),
    ];

    const agentColumns: ColumnDef<StockFeatures, AiAgentDto>[] = [
        {
            accessorKey: "key",
            header: "کلید",
            cell: ({ row }: { row: Row<StockFeatures, AiAgentDto> }) => (
                <div>
                    <div className="flex flex-wrap items-center gap-1.5">
                        <span dir="ltr" className="font-medium">
                            {row.original.key}
                        </span>
                        {!row.original.is_bound && (
                            <Badge variant="outline">بدون اتصال</Badge>
                        )}
                    </div>
                    {row.original.label && (
                        <span className="text-muted-foreground text-sm">
                            {row.original.label}
                        </span>
                    )}
                </div>
            ),
        },
        {
            id: "provider",
            header: "ارائه‌دهنده",
            enableSorting: false,
            cell: ({ row }: { row: Row<StockFeatures, AiAgentDto> }) => (
                <span dir="ltr" className="text-muted-foreground text-sm">
                    {row.original.provider ?? "پیش‌فرض"}
                </span>
            ),
        },
        {
            id: "model",
            header: "مدل",
            enableSorting: false,
            cell: ({ row }: { row: Row<StockFeatures, AiAgentDto> }) => (
                <span dir="ltr" className="text-muted-foreground text-sm">
                    {row.original.model ?? "پیش‌فرض"}
                </span>
            ),
        },
        {
            id: "activity",
            header: "الان",
            enableSorting: false,
            // The one column that comes from the live channel rather than the
            // registry: it says which agent is being asked *right now*, so an
            // administrator opening Settings with no run of their own still
            // sees the nightly summary they started in another tab.
            cell: ({ row }: { row: Row<StockFeatures, AiAgentDto> }) => {
                const activity = agentActivity[row.original.key];

                return activity === undefined ? (
                    <span className="text-muted-foreground text-xs">—</span>
                ) : (
                    <AgentRunBadge activity={activity} />
                );
            },
        },
        ...(canManage
            ? [
                  {
                      id: "actions",
                      header: "",
                      enableSorting: false,
                      cell: ({
                          row,
                      }: {
                          row: Row<StockFeatures, AiAgentDto>;
                      }) => (
                          <RowActions
                              actions={agentRowActions(row.original)}
                          />
                      ),
                  } satisfies ColumnDef<StockFeatures, AiAgentDto>,
              ]
            : []),
    ];

    const providerTable = useDataTable({
        columns: providerColumns,
        data: providers,
        meta: providersData?.meta,
        url: providersUrl,
    });

    const agentTable = useDataTable({
        columns: agentColumns,
        data: agents,
        meta: agentsData?.meta,
        url: agentsUrl,
    });

    return (
        <div className="grid gap-6">
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <StatusTile
                    icon={IconStar}
                    label="ارائه‌دهنده پیش‌فرض"
                    value={
                        defaultProvider ? (
                            <span dir="ltr">{defaultProvider.name}</span>
                        ) : (
                            "تعیین نشده"
                        )
                    }
                    hint={
                        defaultProvider
                            ? driverLabel(defaultProvider.driver)
                            : "هر عاملی که اتصال ندارد به این ارائه‌دهنده نیاز دارد."
                    }
                    tone={defaultProvider ? "success" : "warning"}
                />
                <StatusTile
                    icon={IconBrain}
                    label="ارائه‌دهنده‌های فعال"
                    value={`${activeProviders.length.toLocaleString("fa-IR")} از ${providers.length.toLocaleString("fa-IR")}`}
                    hint="ارائه‌دهندهٔ غیرفعال از انتخاب‌های تازه کنار گذاشته می‌شود."
                />
                <StatusTile
                    icon={IconCpu}
                    label="مدل‌های ثبت‌شده"
                    value={modelCount.toLocaleString("fa-IR")}
                    hint="نام مدل را همان طور که ارائه‌دهنده می‌شناسد ثبت کنید."
                />
                <StatusTile
                    icon={IconRobot}
                    label="عامل‌های متصل"
                    value={`${boundAgents.toLocaleString("fa-IR")} از ${agents.length.toLocaleString("fa-IR")}`}
                    hint={
                        runningAgents > 0
                            ? `${runningAgents.toLocaleString("fa-IR")} اجرا همین حالا در جریان است.`
                            : "بقیه ارائه‌دهنده و مدل پیش‌فرض را به ارث می‌برند."
                    }
                    onClick={runningAgents > 0 ? () => setActivityOpen(true) : undefined}
                />
            </div>

            <Tabs value={tab} onValueChange={selectTab}>
                <TabsList>
                    <TabsTrigger value="providers">
                        <IconBrain className="size-4" />
                        ارائه‌دهنده‌ها
                    </TabsTrigger>
                    <TabsTrigger value="models">
                        <IconCpu className="size-4" />
                        مدل‌ها
                    </TabsTrigger>
                    <TabsTrigger value="agents">
                        <IconRobot className="size-4" />
                        عامل‌ها
                        {runningAgents > 0 && (
                            <Badge variant="info" className="ms-1">
                                {runningAgents.toLocaleString("fa-IR")}
                            </Badge>
                        )}
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="providers">
                    <Card>
                        <CardHeader className="flex flex-wrap items-start justify-between gap-3">
                            <div className="grid gap-1">
                                <CardTitle>درگاه‌ها و کلیدهای دسترسی</CardTitle>
                                <CardDescription>
                                    هر عاملی که اتصال مشخصی ندارد از
                                    ارائه‌دهنده پیش‌فرض استفاده می‌کند؛ خالی بودن
                                    کلید برای Ollama محلی حالت درست است، نه
                                    نقص.
                                </CardDescription>
                            </div>
                            {canManage && (
                                <Button
                                    type="button"
                                    onClick={openCreateProvider}
                                >
                                    <IconPlus className="size-4" />
                                    افزودن ارائه‌دهنده
                                </Button>
                            )}
                        </CardHeader>
                        <CardContent className="p-0">
                            <DataTablePage
                                table={providerTable}
                                meta={providersData?.meta}
                                isLoading={providersLoading}
                                isError={providersError}
                                title=""
                                icon={IconBrain}
                                embedded
                                searchPlaceholder="جستجوی نام یا نشانی…"
                                globalFilter={providersUrl.globalFilter}
                                onGlobalFilterChange={
                                    providersUrl.onGlobalFilterChange
                                }
                                actions={[
                                    {
                                        id: "refresh",
                                        label: "بازخوانی",
                                        icon: IconRefresh,
                                        onClick: () => void refetchProviders(),
                                        disabled: providersFetching,
                                    },
                                ]}
                                onRetry={() => void refetchProviders()}
                                colSpan={providerColumns.length}
                                emptyMessage="هنوز ارائه‌دهنده‌ای ثبت نشده است"
                                emptyAction={
                                    canManage ? (
                                        <Button
                                            type="button"
                                            onClick={openCreateProvider}
                                        >
                                            <IconPlus className="size-4" />
                                            افزودن ارائه‌دهنده
                                        </Button>
                                    ) : undefined
                                }
                                views={TABLE_AND_CARD_VIEWS}
                                view={providersView.view}
                                onViewChange={providersView.setView}
                                renderView={(mode) =>
                                    mode === "card" ? (
                                        <ProviderCardGrid
                                            providers={providers}
                                            defaultProvider={defaultProvider}
                                            actions={providerRowActions}
                                            isTesting={isTesting}
                                            onTest={handleTest}
                                            onOpenModels={(provider) =>
                                                setModelsProvider(provider)
                                            }
                                        />
                                    ) : null
                                }
                            />
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="models">
                    <ModelsTab
                        models={modelRows}
                        providers={providers}
                        isLoading={modelsLoading}
                        isError={modelsError}
                        isFetching={modelsFetching}
                        canManage={canManage}
                        onRetry={() => void refetchModels()}
                        onRefresh={() => void refetchModels()}
                        onManage={(provider) => setModelsProvider(provider)}
                    />
                </TabsContent>

                <TabsContent value="agents">
                    <Card>
                        <CardHeader>
                            <CardTitle>عامل‌ها</CardTitle>
                            <CardDescription>
                                عامل‌ها را دامنه‌های مصرف‌کننده تعریف می‌کنند و
                                اینجا فقط به آن‌ها متصل می‌شوند. عامل بدون اتصال،
                                ارائه‌دهنده و مدل پیش‌فرض را به ارث می‌برد
                                {defaultProvider
                                    ? ` — اکنون «${defaultProvider.name}».`
                                    : " — و هنوز پیش‌فرضی تعیین نشده است."}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            <DataTablePage
                                table={agentTable}
                                meta={agentsData?.meta}
                                isLoading={agentsLoading}
                                isError={agentsError}
                                title=""
                                icon={IconRobot}
                                embedded
                                searchPlaceholder="جستجوی کلید، عنوان یا کلاس…"
                                globalFilter={agentsUrl.globalFilter}
                                onGlobalFilterChange={
                                    agentsUrl.onGlobalFilterChange
                                }
                                actions={[
                                    {
                                        id: "refresh",
                                        label: "بازخوانی",
                                        icon: IconRefresh,
                                        onClick: () => void refetchAgents(),
                                        disabled: agentsFetching,
                                    },
                                ]}
                                onRetry={() => void refetchAgents()}
                                colSpan={agentColumns.length}
                                emptyMessage="هنوز عاملی ثبت نشده است"
                                views={TABLE_AND_CARD_VIEWS}
                                view={agentsView.view}
                                onViewChange={agentsView.setView}
                                renderView={(mode) =>
                                    mode === "card" ? (
                                        <RecordCardGrid>
                                            {agents.map((agent) => (
                                                <RecordCard
                                                    key={agent.key}
                                                    title={
                                                        agent.label ?? agent.key
                                                    }
                                                    subtitle={agent.agent_class}
                                                    icon={IconRobot}
                                                    badges={[
                                                        {
                                                            label: agent.is_bound
                                                                ? "متصل"
                                                                : "ارث‌بری",
                                                            tone: agent.is_bound
                                                                ? "info"
                                                                : "outline",
                                                        },
                                                    ]}
                                                    items={[
                                                        ...(agent.provider
                                                            ? [
                                                                  {
                                                                      icon: IconBrain,
                                                                      label: agent.provider,
                                                                  },
                                                              ]
                                                            : []),
                                                        ...(agent.model
                                                            ? [
                                                                  {
                                                                      icon: IconCpu,
                                                                      label: agent.model,
                                                                  },
                                                              ]
                                                            : []),
                                                        ...(agentActivity[
                                                            agent.key
                                                        ] !== undefined
                                                            ? [
                                                                  {
                                                                      icon: IconActivity,
                                                                      label: "در حال اجرا",
                                                                  },
                                                              ]
                                                            : []),
                                                    ]}
                                                    actions={agentRowActions(
                                                        agent,
                                                    )}
                                                />
                                            ))}
                                        </RecordCardGrid>
                                    ) : null
                                }
                            />
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>

            <AiActivityDialog
                open={activityOpen}
                onOpenChange={setActivityOpen}
                agents={agents}
                activity={agentActivity}
            />

            <AiProviderDialog
                open={providerDialog.open}
                onOpenChange={(open) =>
                    setProviderDialog((s) => ({ ...s, open }))
                }
                provider={providerDialog.provider}
            />

            {modelsProvider && (
                <AiProviderModelsDialog
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setModelsProvider(null);
                        }
                    }}
                    // The list is the source of truth for the models, not the
                    // row captured when the dialog opened: a model added or
                    // deleted elsewhere must be visible here too.
                    provider={
                        providers.find((p) => p.name === modelsProvider.name) ??
                        modelsProvider
                    }
                />
            )}

            {bindingAgent && (
                <AiAgentDialog
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setBindingAgent(null);
                        }
                    }}
                    // Re-read through the list so a binding made in this dialog
                    // is reflected the next time it opens.
                    agent={
                        agents.find((a) => a.key === bindingAgent.key) ??
                        bindingAgent
                    }
                    providers={providers}
                />
            )}
        </div>
    );
}

/**
 * The models tab: every registered model, whichever gateway it belongs to.
 *
 * It is the one list that answers "where is model X", so it reads across
 * providers instead of nesting them. One page of models is small (the
 * registry caps a provider at a few dozen), so search and paging run here
 * rather than through the URL — the providers and agents tables already own
 * the shared query parameters, and a third writer would make paging one
 * table page the others.
 */
function ModelsTab({
    models,
    providers,
    isLoading,
    isError,
    isFetching,
    canManage,
    onRetry,
    onRefresh,
    onManage,
}: {
    models: AiModelDto[];
    providers: AiProviderDto[];
    isLoading: boolean;
    isError: boolean;
    isFetching: boolean;
    canManage: boolean;
    onRetry: () => void;
    onRefresh: () => void;
    onManage: (provider: AiProviderDto) => void;
}) {
    const [query, setQuery] = useState("");
    const [providerFilter, setProviderFilter] = useState<string>("all");

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return models.filter((model) => {
            if (
                providerFilter !== "all" &&
                (model.provider_name ?? "") !== providerFilter
            ) {
                return false;
            }

            if (needle === "") {
                return true;
            }

            return [model.model_name, model.label ?? "", model.provider_name ?? ""]
                .join(" ")
                .toLowerCase()
                .includes(needle);
        });
    }, [models, query, providerFilter]);

    const providerNames = useMemo(
        () =>
            Array.from(
                new Set(
                    models
                        .map((model) => model.provider_name)
                        .filter((name): name is string => Boolean(name)),
                ),
            ),
        [models],
    );

    return (
        <Card>
            <CardHeader className="flex flex-wrap items-start justify-between gap-3">
                <div className="grid gap-1">
                    <CardTitle>مدل‌های ثبت‌شده</CardTitle>
                    <CardDescription>
                        یک نام مدل فقط در محدوده ارائه‌دهنده خودش یکتاست؛ پس هر
                        ردیف می‌گوید متعلق به کدام درگاه است. افزودن، ویرایش و
                        حذف از کنار همان ارائه‌دهنده انجام می‌شود.
                    </CardDescription>
                </div>
            </CardHeader>
            <CardContent className="grid gap-3">
                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative min-w-56 flex-1">
                        <IconSearch className="text-muted-foreground pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2" />
                        <Input
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="جستجوی نام مدل یا ارائه‌دهنده…"
                            className="ps-8"
                        />
                    </div>
                    {providerNames.length > 1 && (
                        <div className="flex flex-wrap gap-1">
                            <Button
                                type="button"
                                size="sm"
                                variant={
                                    providerFilter === "all"
                                        ? "secondary"
                                        : "ghost"
                                }
                                onClick={() => setProviderFilter("all")}
                            >
                                همه
                            </Button>
                            {providerNames.map((name) => (
                                <Button
                                    key={name}
                                    type="button"
                                    size="sm"
                                    variant={
                                        providerFilter === name
                                            ? "secondary"
                                            : "ghost"
                                    }
                                    onClick={() => setProviderFilter(name)}
                                >
                                    <span dir="ltr">{name}</span>
                                </Button>
                            ))}
                        </div>
                    )}
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        disabled={isFetching}
                        onClick={onRefresh}
                    >
                        <IconRefresh className="size-3.5" />
                        بازخوانی
                    </Button>
                </div>

                {isError ? (
                    <div className="grid place-items-center gap-2 py-10 text-center">
                        <p className="text-muted-foreground text-sm">
                            فهرست مدل‌ها خوانده نشد.
                        </p>
                        <Button type="button" variant="outline" onClick={onRetry}>
                            تلاش دوباره
                        </Button>
                    </div>
                ) : isLoading ? (
                    <div className="bg-muted/40 h-40 animate-pulse rounded-lg" />
                ) : filtered.length === 0 ? (
                    <p className="text-muted-foreground py-10 text-center text-sm">
                        {models.length === 0
                            ? "هنوز مدلی ثبت نشده است. از تب ارائه‌دهنده‌ها، «مدیریت مدل‌ها» را باز کنید."
                            : "مدلی با این جستجو پیدا نشد."}
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>مدل</TableHead>
                                <TableHead>ارائه‌دهنده</TableHead>
                                <TableHead>قابلیت‌ها</TableHead>
                                <TableHead className="w-0" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {filtered.map((model) => {
                                const owner = providers.find(
                                    (provider) =>
                                        provider.name === model.provider_name,
                                );

                                return (
                                    <TableRow
                                        key={`${model.provider_name ?? ""}-${model.model_name}`}
                                    >
                                        <TableCell>
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <span
                                                    dir="ltr"
                                                    className="font-medium"
                                                >
                                                    {model.model_name}
                                                </span>
                                                {!model.is_active && (
                                                    <Badge variant="outline">
                                                        غیرفعال
                                                    </Badge>
                                                )}
                                            </div>
                                            {model.label &&
                                                model.label !==
                                                    model.model_name && (
                                                    <span className="text-muted-foreground block text-sm">
                                                        {model.label}
                                                    </span>
                                                )}
                                        </TableCell>
                                        <TableCell>
                                            <span
                                                dir="ltr"
                                                className="text-muted-foreground text-sm"
                                            >
                                                {model.provider_name ?? "—"}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            {model.capabilities.length === 0 ? (
                                                <span className="text-muted-foreground text-sm">
                                                    —
                                                </span>
                                            ) : (
                                                <div className="flex flex-wrap gap-1">
                                                    {model.capabilities.map(
                                                        (capability) => (
                                                            <Badge
                                                                key={capability}
                                                                variant="secondary"
                                                            >
                                                                {capabilityLabel(
                                                                    capability,
                                                                )}
                                                            </Badge>
                                                        ),
                                                    )}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-end">
                                            {owner && canManage && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        onManage(owner)
                                                    }
                                                >
                                                    <IconPencil className="size-4" />
                                                    ویرایش
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                )}
            </CardContent>
        </Card>
    );
}

/**
 * One headline fact. Deliberately quiet: it states the number, names what it
 * is, and adds one sentence about what it means.
 */
function StatusTile({
    icon: Icon,
    label,
    value,
    hint,
    tone = "default",
    onClick,
}: {
    icon: typeof IconBrain;
    label: string;
    value: ReactNode;
    hint?: string;
    tone?: "default" | "success" | "warning";
    /** Makes the whole tile a button, for a tile that has somewhere to go. */
    onClick?: () => void;
}) {
    const body = (
        <CardContent className="flex items-start gap-3 px-4">
            <span
                className={
                    tone === "warning"
                        ? "bg-warning/10 text-warning grid size-9 shrink-0 place-items-center rounded-lg"
                        : tone === "success"
                          ? "bg-success/10 text-success grid size-9 shrink-0 place-items-center rounded-lg"
                          : "bg-muted text-muted-foreground grid size-9 shrink-0 place-items-center rounded-lg"
                }
            >
                {tone === "success" ? (
                    <IconCheck className="size-4" />
                ) : (
                    <Icon className="size-4" />
                )}
            </span>
            <div className="min-w-0">
                <p className="text-muted-foreground text-xs">{label}</p>
                <p className="truncate text-lg font-semibold">{value}</p>
                {hint && (
                    <p className="text-muted-foreground mt-0.5 line-clamp-2 text-xs">
                        {hint}
                    </p>
                )}
            </div>
        </CardContent>
    );

    if (onClick === undefined) {
        return <Card className="gap-2 py-4">{body}</Card>;
    }

    return (
        <Card className="hover:border-ring gap-2 py-4 transition-colors">
            <button
                type="button"
                onClick={onClick}
                className="rounded-xl text-start outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
            >
                {body}
            </button>
        </Card>
    );
}

/** The providers tab's card body; the default leads. */
function ProviderCardGrid({
    providers,
    defaultProvider,
    actions,
    isTesting,
    onTest,
    onOpenModels,
}: {
    providers: AiProviderDto[];
    defaultProvider?: AiProviderDto;
    actions: (provider: AiProviderDto) => RowAction[];
    isTesting: (provider: AiProviderDto) => boolean;
    onTest: (provider: AiProviderDto) => void;
    onOpenModels: (provider: AiProviderDto) => void;
}) {
    // The default first, then the rest in the order the server sent them.
    const ordered = defaultProvider
        ? [
              defaultProvider,
              ...providers.filter((p) => p.name !== defaultProvider.name),
          ]
        : providers;

    return (
        <RecordCardGrid>
            {ordered.map((provider) => {
                const testing = isTesting(provider);

                return (
                    <RecordCard
                        key={provider.name}
                        title={
                            <span dir="ltr" className="font-medium">
                                {provider.name}
                            </span>
                        }
                        subtitle={provider.base_url ?? undefined}
                        icon={IconBrain}
                        badges={[
                            {
                                label: driverLabel(provider.driver),
                                tone: "outline",
                            },
                            ...(provider.is_default
                                ? [
                                      {
                                          label: "پیش‌فرض",
                                          tone: "info" as const,
                                      },
                                  ]
                                : []),
                            ...(provider.is_active
                                ? []
                                : [
                                      {
                                          label: "غیرفعال",
                                          tone: "secondary" as const,
                                      },
                                  ]),
                            ...(!provider.has_api_key &&
                            !isKeyless(provider.driver)
                                ? [
                                      {
                                          label: "کلید ندارد",
                                          tone: "warning" as const,
                                      },
                                  ]
                                : []),
                        ]}
                        items={[
                            {
                                icon: IconCpu,
                                label: `${provider.models.length.toLocaleString("fa-IR")} مدل`,
                            },
                            ...(provider.has_api_key
                                ? [
                                      {
                                          icon: IconKey,
                                          label: "کلید ذخیره شده",
                                      },
                                  ]
                                : []),
                        ]}
                        actions={actions(provider)}
                    >
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={testing}
                            onClick={() => onTest(provider)}
                        >
                            <IconActivity className="size-3.5" />
                            {testing ? "در حال تست…" : "تست اتصال"}
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => onOpenModels(provider)}
                        >
                            <IconSparkles className="size-3.5" />
                            مدل‌ها
                        </Button>
                    </RecordCard>
                );
            })}
        </RecordCardGrid>
    );
}
