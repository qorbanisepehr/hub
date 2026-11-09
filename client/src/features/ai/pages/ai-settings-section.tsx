import { useState } from "react";
import type { ColumnDef, Row, StockFeatures } from "@tanstack/react-table";
import {
    IconBrain,
    IconCpu,
    IconKey,
    IconLink,
    IconPencil,
    IconPlugConnected,
    IconPlus,
    IconRefresh,
    IconRobot,
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
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import { RowActions, type RowAction } from "@/components/shared/row-actions";
import {
    useDataTable,
    useDataTableUrlState,
} from "@/hooks/use-data-table-page";
import { usePermission } from "@/features/auth/components/permission-guard";
import { useRunProgressChannel } from "@/features/realtime/use-run-progress-channel";
import {
    useAiAgents,
    useAiProviders,
    useDeleteAiProvider,
    useMakeAiProviderDefault,
    useTestAiProvider,
} from "@/features/ai/hooks";
import { AiAgentDialog } from "@/features/ai/pages/ai-agent-dialog";
import { AiProviderDialog } from "@/features/ai/pages/ai-provider-dialog";
import { AiProviderModelsDialog } from "@/features/ai/pages/ai-provider-models-dialog";
import {
    AI_DRIVERS,
    type AiAgentDto,
    type AiProviderDto,
} from "@/features/ai/types";
import { getApiError } from "@/lib/error-utils";
import { PERMISSIONS } from "@/lib/permissions";
import { getRouteApi } from "@tanstack/react-router";

const aiRoute = getRouteApi("/protected/settings/ai");

/**
 * The AI tab (R3): bring-your-own-key providers, their models, and what each
 * registered agent is bound to.
 *
 * Two tables, not one. A provider and an agent are different things with
 * different lifecycles — one is a credential, the other is a row a consuming
 * domain registered — and merging them into a single list would have implied
 * that Settings creates agents, which it deliberately does not.
 *
 * Each table owns its own search keys. They share the settings route's URL, so
 * with the default `page`/`filter` both tables would write to the same
 * parameters and page one while paging the other.
 */
export function AiSettingsSection() {
    const canManage = usePermission([PERMISSIONS.AI_PROVIDER_MANAGE]);

    const search = aiRoute.useSearch();
    const navigate = aiRoute.useNavigate();

    const providersUrl = useDataTableUrlState({
        search: search as unknown as Record<string, unknown>,
        navigate,
        urlState: {
            globalFilter: { enabled: true, key: "filter" },
        },
    });

    const providersView = useListView({ search, navigate, key: "view" });
    const agentsView = useListView({ search, navigate, key: "ai_view" });

    const agentsUrl = useDataTableUrlState({
        search: search as unknown as Record<string, unknown>,
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
        if (progress.channel !== "ai" || progress.subject?.type !== "ai-provider") {
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

    // One declaration per row's commands: the table cell and the card menu
    // are the same menu, so a command cannot exist in one and not the other.
    // The probe is expensive (one model call), so it lives behind the «...»
    // menu on the menu shape and inline in the table — either way it is the
    // first thing a person who suspects a gateway reaches for.
    const providerRowActions = (provider: AiProviderDto): RowAction[] => {
        const testing =
            (test.isPending && test.variables?.name === provider.name) ||
            testingRuns.has(provider.name);

        return [
            {
                icon: <IconLink className="size-4" />,
                label: "تست اتصال",
                onClick: () => handleTest(provider),
                disabled: testing,
                permission: PERMISSIONS.AI_PROVIDER_MANAGE,
            },
            {
                icon: <IconBrain className="size-4" />,
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
    ];

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
                    {AI_DRIVERS.find((d) => d.value === row.original.driver)
                        ?.label ?? row.original.driver}
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
                ) : AI_DRIVERS.find((d) => d.value === row.original.driver)
                      ?.keyless ? (
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
                <span className="text-muted-foreground text-sm">
                    {row.original.models.length.toLocaleString("fa-IR")}
                </span>
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
        data: providersData?.data ?? [],
        meta: providersData?.meta,
        url: providersUrl,
    });

    const agentTable = useDataTable({
        columns: agentColumns,
        data: agentsData?.data ?? [],
        meta: agentsData?.meta,
        url: agentsUrl,
    });

    return (
        <div className="grid gap-6">
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <IconBrain className="size-5" />
                        ارائه‌دهنده‌های هوش مصنوعی
                    </CardTitle>
                    <CardDescription>
                        درگاه‌ها و کلیدهای دسترسی. هر عاملی که اتصال مشخصی ندارد
                        از ارائه‌دهنده فعال پیش‌فرض استفاده می‌کند؛ خالی بودن
                        کلید برای Ollama محلی حالت درست است، نه نقص.
                    </CardDescription>
                </CardHeader>
                <CardContent className="p-0">
                    <div className="flex flex-wrap items-center justify-end gap-3 px-4 pt-4">
                        {canManage && (
                            <Button type="button" onClick={openCreateProvider}>
                                <IconPlus className="size-4" />
                                افزودن ارائه‌دهنده
                            </Button>
                        )}
                    </div>

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
                        onGlobalFilterChange={providersUrl.onGlobalFilterChange}
                        actions={[
                            {
                                id: "refresh",
                                label: "بازخوانی",
                                icon: IconRefresh,
                                onClick: () => refetchProviders(),
                                disabled: providersFetching,
                            },
                        ]}
                        onRetry={() => refetchProviders()}
                        colSpan={providerColumns.length}
                        emptyMessage="هنوز ارائه‌دهنده‌ای ثبت نشده است"
                        views={TABLE_AND_CARD_VIEWS}
                        view={providersView.view}
                        onViewChange={providersView.setView}
                        renderView={(mode) =>
                            mode === "card" ? (
                                <RecordCardGrid>
                                    {(providersData?.data ?? []).map(
                                        (provider) => (
                                            <RecordCard
                                                key={provider.name}
                                                title={provider.name}
                                                subtitle={
                                                    provider.base_url ??
                                                    undefined
                                                }
                                                icon={IconBrain}
                                                badges={[
                                                    // The default is the
                                                    // strongest fact about a
                                                    // provider, so it leads;
                                                    // «فعال» is the normal
                                                    // state and gets no badge.
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
                                                ]}
                                                items={[
                                                    {
                                                        icon: IconCpu,
                                                        label: `${
                                                            provider.models
                                                                .length
                                                        } مدل`,
                                                    },
                                                    ...(provider.has_api_key
                                                        ? [
                                                              {
                                                                  icon: IconKey,
                                                                  label: "کلید تنظیم شده",
                                                              },
                                                          ]
                                                        : []),
                                                ]}
                                                actions={providerRowActions(
                                                    provider,
                                                )}
                                            />
                                        ),
                                    )}
                                </RecordCardGrid>
                            ) : null
                        }
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <IconRobot className="size-5" />
                        عامل‌ها
                    </CardTitle>
                    <CardDescription>
                        عامل‌ها را دامنه‌های مصرف‌کننده تعریف می‌کنند و اینجا
                        فقط به آن‌ها متصل می‌شوند. عامل بدون اتصال، ارائه‌دهنده
                        و مدل پیش‌فرض را به ارث می‌برد.
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
                        onGlobalFilterChange={agentsUrl.onGlobalFilterChange}
                        actions={[
                            {
                                id: "refresh",
                                label: "بازخوانی",
                                icon: IconRefresh,
                                onClick: () => refetchAgents(),
                                disabled: agentsFetching,
                            },
                        ]}
                        onRetry={() => refetchAgents()}
                        colSpan={agentColumns.length}
                        emptyMessage="هنوز عاملی ثبت نشده است"
                        views={TABLE_AND_CARD_VIEWS}
                        view={agentsView.view}
                        onViewChange={agentsView.setView}
                        renderView={(mode) =>
                            mode === "card" ? (
                                <RecordCardGrid>
                                    {(agentsData?.data ?? []).map((agent) => (
                                        <RecordCard
                                            key={agent.key}
                                            title={agent.label ?? agent.key}
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
                                                              label: agent
                                                                  .provider,
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
                                            ]}
                                            actions={agentRowActions(agent)}
                                        />
                                    ))}
                                </RecordCardGrid>
                            ) : null
                        }
                    />
                </CardContent>
            </Card>

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
                    onOpenChange={(open) => !open && setModelsProvider(null)}
                    // The list is the source of truth for the models, not the
                    // row captured when the dialog opened: a model added or
                    // deleted elsewhere must be visible here too.
                    provider={
                        providersData?.data.find(
                            (p) => p.name === modelsProvider.name,
                        ) ?? modelsProvider
                    }
                />
            )}

            {bindingAgent && (
                <AiAgentDialog
                    open
                    onOpenChange={(open) => !open && setBindingAgent(null)}
                    agent={bindingAgent}
                    providers={providersData?.data ?? []}
                />
            )}
        </div>
    );
}
