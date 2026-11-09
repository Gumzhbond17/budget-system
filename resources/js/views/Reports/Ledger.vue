<template>
    <AdminLayout>
        <PageBreadcrumb page-title="reports.ledger.title" :icon="PageIcon" />

        <ReportFilterBar
            v-model:project-uuid="projectUuid"
            v-model:search="search"
            v-model:payer-id="payerId"
            v-model:date-from="dateFrom"
            v-model:date-to="dateTo"
            v-model:status-mode="statusMode"
            :projects="projects"
            :payers="payers"
            search-hint="reports.ledger.searchHint"
            :has-filters="hasFilters"
            :can-print="canPrint"
            @print="printPdf"
            @reset="reset"
        />

        <div v-if="!projectUuid" class="empty-state">
            {{ t('tx.selectProjectFirst') }}
        </div>

        <Alert
            v-else-if="!isMember"
            variant="info"
            :title="t('tx.notAMember')"
            :message="t('tx.notAMemberHint')"
        />

        <Alert
            v-else-if="error"
            variant="error"
            :title="t('settings.loadFailed')"
            :message="error"
        />

        <!-- ເກີນເພດານ = ບໍ່ໄດ້ດຶງແຖວມາຄິດເລີຍ ຈຶ່ງບໍ່ມີແມ່ນແຕ່ຍອດລວມທີ່ຈະສະແດງ -->
        <Alert
            v-else-if="overLimit"
            variant="warning"
            :title="t('reports.tooMany')"
            :message="
                t('reports.tooManyHint', {
                    rows: report?.row_count ?? 0,
                    max: report?.max_rows ?? 0,
                })
            "
        />

        <template v-else>
            <!-- ================= ສ່ວນສະຫຼຸບ ================= -->
            <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <SummaryCard
                    label="ledger.totalRevenue"
                    tone="revenue"
                    :value="totals?.revenue_lak ?? null"
                    :icon="ArrowDownIcon"
                />
                <SummaryCard
                    label="ledger.totalExpense"
                    tone="expense"
                    :value="totals?.expense_lak ?? null"
                    :icon="ArrowUpIcon"
                />
                <SummaryCard
                    label="ledger.balance"
                    tone="balance"
                    :value="totals?.balance_lak ?? null"
                    :icon="WalletIcon"
                    :caption="t('ledger.balanceHint')"
                />
                <SummaryCard
                    label="ledger.budgetRemaining"
                    tone="neutral"
                    :value="totals?.budget_remaining_lak ?? null"
                    :icon="TargetIcon"
                    :caption="budgetCaption"
                />
            </div>

            <!-- ---------- ສະຫຼຸບຕໍ່ເດືອນ ---------- -->
            <div
                class="mb-6 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <div
                    class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800"
                >
                    <div>
                        <h3
                            class="text-base font-medium text-gray-800 dark:text-white/90"
                        >
                            {{ t('reports.ledger.monthly') }}
                        </h3>
                        <p
                            class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                        >
                            {{ t('reports.ledger.monthlyHint') }}
                        </p>
                    </div>
                </div>

                <DataGrid
                    :column-defs="monthlyColumnDefs"
                    :row-data="report?.monthly ?? []"
                    :loading="loading"
                    :get-row-id="getMonthRowId"
                    :pinned-bottom-row-data="monthlyTotalsRow"
                    :empty-label="t('reports.empty')"
                />
            </div>

            <!-- ================= ສ່ວນລະອຽດ ================= -->
            <div
                class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <div
                    class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800"
                >
                    <h3
                        class="text-base font-medium text-gray-800 dark:text-white/90"
                    >
                        {{ t('reports.detail') }}
                    </h3>
                    <span
                        class="text-theme-xs text-gray-500 dark:text-gray-400"
                    >
                        {{
                            t('ledger.entryCount', {
                                count: report?.row_count ?? 0,
                            })
                        }}
                    </span>
                </div>

                <DataGrid
                    :column-defs="entryColumnDefs"
                    :row-data="report?.entries ?? []"
                    :loading="loading"
                    :get-row-id="getEntryRowId"
                    :row-height="60"
                    :col-defaults="ledgerColDefaults"
                    :pinned-bottom-row-data="entryTotalsRow"
                    :empty-label="t('reports.empty')"
                />

                <p
                    class="px-5 py-3 text-theme-xs text-gray-500 dark:text-gray-400"
                >
                    {{ t('ledger.lakNote') }}
                </p>
            </div>
        </template>
    </AdminLayout>
</template>

<script setup lang="ts">
import type { ColDef, ICellRendererParams } from 'ag-grid-community';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { api, toApiError } from '@/api/client';
import DataGrid from '@/components/common/DataGrid.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import MoneyCell from '@/components/grid/MoneyCell.vue';
import TwoLineCell from '@/components/grid/TwoLineCell.vue';
import AdminLayout from '@/components/layout/AdminLayout.vue';
import SummaryCard from '@/components/ledger/SummaryCard.vue';
import ReportFilterBar from '@/components/reports/ReportFilterBar.vue';
import Alert from '@/components/ui/Alert.vue';
import { useAutoRefresh } from '@/composables/useAutoRefresh';
import { useDebouncedSearch } from '@/composables/useDebouncedSearch';
import { useReportFilters } from '@/composables/useReportFilters';
import type { LedgerEntry } from '@/types/ledger';
import type { LedgerMonth, LedgerReport } from '@/types/reports';
import type { Money, Project } from '@/types/settings';
import { formatDate } from '@/utils/date';
import { formatMoney, formatMoneyWithCurrency } from '@/utils/money';
import ArrowDownIcon from '~icons/tabler/arrow-down-right';
import ArrowUpIcon from '~icons/tabler/arrow-up-right';
import PageIcon from '~icons/tabler/report-analytics';
import TargetIcon from '~icons/tabler/target';
import WalletIcon from '~icons/tabler/wallet';

const { t } = useI18n();

const projects = ref<Project[]>([]);
const payers = ref<{ id: number; name: string }[]>([]);

const projectUuid = ref('');
// ຕ້ອງ destructure ບໍ່ແມ່ນເກັບເປັນ object: ref ທີ່ຢູ່ໃນ object ຊັ້ນໃນ
// template ຈະບໍ່ unwrap ໃຫ້ — ຈະໄດ້ Ref ແທນຄ່າຂອງມັນ
const {
    search,
    payerId,
    dateFrom,
    dateTo,
    statusMode,
    hasFilters,
    buildParams,
    reset,
} = useReportFilters();

const report = ref<LedgerReport | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

const totals = computed(() => report.value?.totals ?? null);

const currentProject = computed(() =>
    projects.value.find((p) => p.uuid === projectUuid.value),
);

const isMember = computed(() => currentProject.value?.my_role != null);

const overLimit = computed(
    () =>
        report.value !== null && report.value.row_count > report.value.max_rows,
);

// server ຕັດສິນວ່າພິມໄດ້ບໍ (ບໍ່ມີແຖວ / ເກີນເພດານ = ບໍ່ອອກ URL ໃຫ້) —
// ຈຶ່ງບໍ່ມີກໍລະນີທີ່ປຸ່ມກົດໄດ້ແຕ່ server ປະຕິເສດ
const canPrint = computed(
    () => !loading.value && report.value?.pdf_url != null,
);

/**
 * ຕິດລົບ (ຈ່າຍເກີນເງິນທີ່ຮັບ) ຕ້ອງແດງ ບໍ່ດັ່ງນັ້ນຄົນຈະເລື່ອນຜ່ານໂດຍບໍ່ສັງເກດ
 * ເຫັນເຄື່ອງໝາຍລົບຕົວນ້ອຍໆ. `neutralWhenPositive` ໃຊ້ກັບຄໍລຳຍອດຄົງເຫຼືອ
 * ທີ່ບໍ່ຄວນຂຽວທັງຖັນ — ຂຽວໝົດຈະບໍ່ມີຄວາມໝາຍ.
 */
function signClass(minor: number, neutralWhenPositive = false): string {
    if (minor < 0) {
        return 'text-error-600 dark:text-error-500';
    }

    return neutralWhenPositive
        ? 'text-gray-800 dark:text-white/90'
        : 'text-success-600 dark:text-success-500';
}

/** ບອກວ່າງົບປະມານທັງໝົດເທົ່າໃດ — ຍອດທີ່ຍັງເຫຼືອຢ່າງດຽວບໍ່ພຽງພໍໃຫ້ຕັດສິນ */
const budgetCaption = computed(() => {
    if (totals.value?.budget_lak) {
        return t('ledger.ofBudget', {
            total: formatMoney(totals.value.budget_lak),
        });
    }

    // ຕັ້ງງົບໄວ້ ແຕ່ເປັນສະກຸນອື່ນ — ບອກເຫດຜົນ ບໍ່ດັ່ງນັ້ນຈະອ່ານວ່າ "ຍັງບໍ່ໄດ້ຕັ້ງງົບ"
    if (totals.value?.budget_currency) {
        return t('ledger.budgetOtherCurrency', {
            currency: totals.value.budget_currency,
        });
    }

    return t('ledger.noBudget');
});

/**
 * ເປີດ URL ທີ່ server ເຊັນມາ — ຫ້າມປະກອບເອງ: ເສັ້ນທາງ PDF ພິສູດສິດ
 * ດ້ວຍລາຍເຊັນ ບໍ່ແມ່ນ session (ແທັບໃໝ່ບໍ່ມີ Referer ໃຫ້ Sanctum).
 * ປຸ່ມຖືກປິດຢູ່ແລ້ວເມື່ອ pdf_url ເປັນ null.
 */
function printPdf(): void {
    if (report.value?.pdf_url) {
        window.open(report.value.pdf_url, '_blank');
    }
}

async function loadReference(): Promise<void> {
    try {
        const [projectRes, payerRes] = await Promise.all([
            api.get<{ data: Project[] }>('/projects'),
            api.get<{ data: { id: number; name: string }[] }>('/payers'),
        ]);

        projects.value = projectRes.data.data;
        payers.value = payerRes.data.data;

        if (projects.value.length > 0 && !projectUuid.value) {
            projectUuid.value = projects.value[0].uuid;
        }
    } catch (e) {
        error.value = toApiError(e).message;
    }
}

/** ຄຳຕອບທີ່ມາຊ້າກວ່າການໂຫຼດອັນໃໝ່ (ປ່ຽນຕົວກອງໄວໆ / ຮອບອັດຕະໂນມັດ) ຖືກຖິ້ມ */
let loadSeq = 0;

/**
 * @param silent ຮອບໂຫຼດຄືນອັດຕະໂນມັດ — ບໍ່ສະແດງ "ກຳລັງໂຫຼດ" ແລະ ຖ້າລົ້ມ
 *               ໃຫ້ຮັກສາລາຍງານເກົ່າໄວ້
 */
async function load(silent = false): Promise<void> {
    const seq = ++loadSeq;

    if (!projectUuid.value || !isMember.value) {
        report.value = null;

        return;
    }

    if (!silent) {
        loading.value = true;
        error.value = null;
    }

    try {
        const { data } = await api.get<{ data: LedgerReport }>(
            `/projects/${projectUuid.value}/reports/ledger`,
            { params: buildParams() },
        );

        if (seq === loadSeq) {
            report.value = data.data;
        }
    } catch (e) {
        if (!silent && seq === loadSeq) {
            error.value = toApiError(e).message;
            report.value = null;
        }
    } finally {
        if (seq === loadSeq) {
            loading.value = false;
        }
    }
}

// ລາຍການທີ່ຫາກໍອະນຸມັດ/ແກ້ ໂດຍຄົນອື່ນ ປາກົດເອງ ບໍ່ຕ້ອງກົດໂຫຼດໜ້າ
useAutoRefresh(() => load(true));

// ---------- ຕາຕະລາງສະຫຼຸບຕໍ່ເດືອນ ----------

const getMonthRowId = (params: { data: LedgerMonth }) => params.data.month;

/** ແຖວຍອດລວມທີ່ປັກໄວ້ລຸ່ມສຸດ — ແທນ <tfoot> ເດີມ */
const monthlyTotalsRow = computed(() =>
    totals.value === null
        ? []
        : [
              {
                  month: '__totals__',
                  count: totals.value.entry_count,
                  revenue_lak: totals.value.revenue_lak,
                  expense_lak: totals.value.expense_lak,
                  net_lak: totals.value.balance_lak,
                  balance_lak: totals.value.balance_lak,
              },
          ],
);

function isMonthTotals(data: unknown): boolean {
    return (data as { month?: string } | undefined)?.month === '__totals__';
}

const monthlyColumnDefs = computed<ColDef[]>(() => [
    {
        field: 'month',
        headerName: t('reports.ledger.month'),
        width: 150,
        cellClass: 'font-medium',
        valueFormatter: (params) =>
            isMonthTotals(params.data) ? t('ledger.total') : params.value,
    },
    {
        field: 'count',
        headerName: t('reports.items'),
        width: 110,
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
    },
    {
        colId: 'revenue',
        headerName: `${t('ledger.revenue')} (LAK)`,
        width: 170,
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
        valueGetter: (params) => params.data?.revenue_lak?.minor ?? 0,
        cellRenderer: MoneyCell,
        cellRendererParams: {
            tone: 'revenue',
            money: (params: ICellRendererParams) =>
                (params.data as LedgerMonth | undefined)?.revenue_lak,
        },
    },
    {
        colId: 'expense',
        headerName: `${t('ledger.expense')} (LAK)`,
        width: 170,
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
        valueGetter: (params) => params.data?.expense_lak?.minor ?? 0,
        cellRenderer: MoneyCell,
        cellRendererParams: {
            tone: 'expense',
            money: (params: ICellRendererParams) =>
                (params.data as LedgerMonth | undefined)?.expense_lak,
        },
    },
    {
        colId: 'net',
        headerName: `${t('reports.ledger.net')} (LAK)`,
        width: 170,
        filter: 'agNumberColumnFilter',
        cellClass: (params) =>
            `ag-cell-numeric font-medium ${signClass(params.data?.net_lak?.minor ?? 0)}`,
        headerClass: 'ag-right-aligned-header',
        valueGetter: (params) => params.data?.net_lak?.minor ?? 0,
        valueFormatter: (params) =>
            formatMoney(
                (params.data as LedgerMonth | undefined)?.net_lak ?? null,
            ),
    },
    {
        colId: 'balance',
        headerName: `${t('ledger.runningBalance')} (LAK)`,
        width: 190,
        filter: 'agNumberColumnFilter',
        cellClass: (params) =>
            `ag-cell-numeric font-medium ${signClass(params.data?.balance_lak?.minor ?? 0)}`,
        headerClass: 'ag-right-aligned-header',
        valueGetter: (params) => params.data?.balance_lak?.minor ?? 0,
        valueFormatter: (params) =>
            formatMoney(
                (params.data as LedgerMonth | undefined)?.balance_lak ?? null,
            ),
    },
]);

// ---------- ຕາຕະລາງລາຍການລະອຽດ ----------

const getEntryRowId = (params: { data: LedgerEntry }) => params.data.uuid;

/**
 * ຫ້າມຮຽງໃໝ່ — ຖັນ "ຍອດຄົງເຫຼືອ" ເປັນຍອດສະສົມຕາມລຳດັບວັນທີ. ຮຽງໃໝ່ແລ້ວ
 * ຕົວເລກຈະລຽນກັນລົງມາແບບບໍ່ມີຄວາມໝາຍ (ເຫດຜົນເຕັມຢູ່ views/Ledger.vue).
 */
const ledgerColDefaults: ColDef = { sortable: false };

const entryTotalsRow = computed(() =>
    totals.value === null
        ? []
        : [
              {
                  uuid: '__totals__',
                  title: t('ledger.total'),
                  revenue: totals.value.revenue_lak,
                  expense: totals.value.expense_lak,
                  balance_lak: totals.value.balance_lak,
              },
          ],
);

function isEntryTotals(data: unknown): boolean {
    return (data as { uuid?: string } | undefined)?.uuid === '__totals__';
}

const entryColumnDefs = computed<ColDef[]>(() => [
    {
        colId: 'index',
        headerName: '#',
        width: 70,
        filter: false,
        floatingFilter: false,
        cellClass: 'ag-cell-numeric text-gray-400',
        headerClass: 'ag-right-aligned-header',
        valueGetter: (params) =>
            isEntryTotals(params.data) ? '' : (params.node?.rowIndex ?? 0) + 1,
    },
    {
        field: 'date',
        headerName: t('ledger.date'),
        width: 130,
        filter: 'agDateColumnFilter',
        valueFormatter: (params) =>
            params.value ? formatDate(params.value) : '',
    },
    {
        field: 'doc_no',
        headerName: t('ledger.docNo'),
        width: 150,
        cellClass: 'font-mono text-theme-xs',
        valueFormatter: (params) => params.value ?? '—',
    },
    {
        field: 'title',
        headerName: t('ledger.description'),
        flex: 2,
        minWidth: 220,
        cellRenderer: TwoLineCell,
        cellRendererParams: {
            secondary: (params: ICellRendererParams) => {
                const entry = params.data as LedgerEntry | undefined;

                if (!entry || isEntryTotals(entry)) {
                    return null;
                }

                return [
                    entry.counterparty,
                    formatMoneyWithCurrency(entry.amount_usd),
                    statusMode.value === 'all'
                        ? t(`ledger.statusLabel.${entry.status}`)
                        : null,
                ]
                    .filter(Boolean)
                    .join(' · ');
            },
        },
    },
    {
        colId: 'revenue',
        headerName: `${t('ledger.revenue')} (LAK)`,
        width: 170,
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
        valueGetter: (params) =>
            isEntryTotals(params.data)
                ? ((params.data as { revenue?: Money }).revenue?.minor ?? 0)
                : params.data?.type === 'revenue'
                  ? params.data.amount_lak.minor
                  : null,
        cellRenderer: MoneyCell,
        cellRendererParams: {
            tone: 'revenue',
            money: (params: ICellRendererParams) =>
                isEntryTotals(params.data)
                    ? (params.data as { revenue?: Money }).revenue
                    : (params.data as LedgerEntry | undefined)?.type ===
                        'revenue'
                      ? (params.data as LedgerEntry).amount_lak
                      : null,
        },
    },
    {
        colId: 'expense',
        headerName: `${t('ledger.expense')} (LAK)`,
        width: 170,
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
        valueGetter: (params) =>
            isEntryTotals(params.data)
                ? ((params.data as { expense?: Money }).expense?.minor ?? 0)
                : params.data?.type === 'expense'
                  ? params.data.amount_lak.minor
                  : null,
        cellRenderer: MoneyCell,
        cellRendererParams: {
            tone: 'expense',
            money: (params: ICellRendererParams) =>
                isEntryTotals(params.data)
                    ? (params.data as { expense?: Money }).expense
                    : (params.data as LedgerEntry | undefined)?.type ===
                        'expense'
                      ? (params.data as LedgerEntry).amount_lak
                      : null,
        },
    },
    {
        colId: 'balance',
        headerName: `${t('ledger.runningBalance')} (LAK)`,
        width: 190,
        filter: 'agNumberColumnFilter',
        cellClass: (params) =>
            `ag-cell-numeric font-medium ${signClass(params.data?.balance_lak?.minor ?? 0, true)}`,
        headerClass: 'ag-right-aligned-header',
        valueGetter: (params) => params.data?.balance_lak?.minor ?? 0,
        valueFormatter: (params) =>
            formatMoney(
                (params.data as { balance_lak?: Money } | undefined)
                    ?.balance_lak ?? null,
            ),
    },
]);

watch([projectUuid, payerId, dateFrom, dateTo, statusMode], () => void load());

useDebouncedSearch(search, () => void load());

void loadReference();
</script>
