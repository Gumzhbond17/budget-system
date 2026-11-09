<template>
    <AdminLayout>
        <PageBreadcrumb page-title="reports.expenses.title" :icon="PageIcon" />

        <ReportFilterBar
            v-model:project-uuid="projectUuid"
            v-model:search="search"
            v-model:payer-id="payerId"
            v-model:category-id="categoryId"
            v-model:date-from="dateFrom"
            v-model:date-to="dateTo"
            v-model:status-mode="statusMode"
            :projects="projects"
            :payers="payers"
            :categories="categories"
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

        <template v-else>
            <!-- ---------- ຍອດລວມ ---------- -->
            <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <SummaryCard
                    label="reports.expenses.totalUsd"
                    tone="expense"
                    :value="report?.summary.total_usd ?? null"
                    :icon="ReceiptIcon"
                    :caption="
                        t('reports.itemCount', {
                            count: report?.summary.item_count ?? 0,
                        })
                    "
                />
                <SummaryCard
                    label="reports.expenses.totalLak"
                    tone="expense"
                    :value="report?.summary.total_lak ?? null"
                    :icon="WalletIcon"
                    :caption="t('reports.lakCaption')"
                />
                <StatCard
                    :label="t('reports.payerCount')"
                    :value="report?.by_payer.length ?? 0"
                    :icon="UsersIcon"
                    :caption="t('reports.payerCaption')"
                />
                <StatCard
                    :label="t('reports.missingEvidence')"
                    :value="report?.summary.missing_evidence_count ?? 0"
                    :icon="AlertIcon"
                    :caption="t('reports.missingEvidenceCaption')"
                    :alarming="
                        (report?.summary.missing_evidence_count ?? 0) > 0
                    "
                />
            </div>

            <PayerBreakdownTable
                :rows="report?.by_payer ?? []"
                title="reports.expenses.byPayer"
                label-header="tx.payer"
                tone="expense"
                :loading="loading"
            />

            <!-- ---------- ລາຍການລະອຽດ ---------- -->
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

                <!--
                    ເກີນເພດານແຖວ = ບໍ່ສະແດງຕາຕະລາງເລີຍ (ບໍ່ແມ່ນຕັດເອົາບາງສ່ວນ)
                    — ລາຍງານທີ່ບໍ່ຄົບແມ່ນອັນຕະລາຍກວ່າລາຍງານທີ່ບໍ່ມີ
                -->
                <Alert
                    v-if="overLimit"
                    variant="warning"
                    :title="t('reports.tooMany')"
                    :message="
                        t('reports.tooManyHint', {
                            rows: report?.row_count ?? 0,
                            max: report?.max_rows ?? 0,
                        })
                    "
                    class="m-5"
                />

                <DataGrid
                    v-else
                    :column-defs="columnDefs"
                    :row-data="report?.expenses ?? []"
                    :loading="loading"
                    :get-row-id="getRowId"
                    :row-height="60"
                    :pinned-bottom-row-data="totalsRow"
                    :empty-label="t('reports.empty')"
                />
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
import BadgeCell from '@/components/grid/BadgeCell.vue';
import MoneyCell from '@/components/grid/MoneyCell.vue';
import TwoLineCell from '@/components/grid/TwoLineCell.vue';
import AdminLayout from '@/components/layout/AdminLayout.vue';
import SummaryCard from '@/components/ledger/SummaryCard.vue';
import PayerBreakdownTable from '@/components/reports/PayerBreakdownTable.vue';
import ReportFilterBar from '@/components/reports/ReportFilterBar.vue';
import StatCard from '@/components/reports/StatCard.vue';
import Alert from '@/components/ui/Alert.vue';
import { useAutoRefresh } from '@/composables/useAutoRefresh';
import { useDebouncedSearch } from '@/composables/useDebouncedSearch';
import { useReportFilters } from '@/composables/useReportFilters';
import type { EntryStatus } from '@/types/ledger';
import type { ExpenseReport } from '@/types/reports';
import type { Project } from '@/types/settings';
import type { Expense } from '@/types/transactions';
import { formatDate } from '@/utils/date';
import { formatMoney } from '@/utils/money';
import AlertIcon from '~icons/tabler/alert-triangle';
import ReceiptIcon from '~icons/tabler/receipt';
import PageIcon from '~icons/tabler/report';
import UsersIcon from '~icons/tabler/users';
import WalletIcon from '~icons/tabler/wallet';

const { t } = useI18n();

const projects = ref<Project[]>([]);
const payers = ref<{ id: number; name: string }[]>([]);
const categories = ref<{ id: number; name_lo: string }[]>([]);

const projectUuid = ref('');
// ຕ້ອງ destructure ບໍ່ແມ່ນເກັບເປັນ object: ref ທີ່ຢູ່ໃນ object ຊັ້ນໃນ
// template ຈະບໍ່ unwrap ໃຫ້ — ຈະໄດ້ Ref ແທນຄ່າຂອງມັນ
const {
    search,
    payerId,
    categoryId,
    dateFrom,
    dateTo,
    statusMode,
    hasFilters,
    buildParams,
    reset,
} = useReportFilters();

const report = ref<ExpenseReport | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

const currentProject = computed(() =>
    projects.value.find((p) => p.uuid === projectUuid.value),
);

// ທຸກ Policy ຂອງລາຍງານເລີ່ມຈາກ "ເປັນສະມາຊິກໂຄງການບໍ" ຄືກັນກັບໜ້າລາຍຈ່າຍ
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

function statusColor(status: EntryStatus) {
    return (
        {
            draft: 'light',
            submitted: 'info',
            approved: 'success',
            rejected: 'error',
        } as const
    )[status];
}

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
        const [projectRes, payerRes, categoryRes] = await Promise.all([
            api.get<{ data: Project[] }>('/projects'),
            api.get<{ data: { id: number; name: string }[] }>('/payers'),
            api.get<{ data: { id: number; name_lo: string }[] }>('/categories'),
        ]);

        projects.value = projectRes.data.data;
        payers.value = payerRes.data.data;
        categories.value = categoryRes.data.data;

        // ມີໂຄງການດຽວ = ເລືອກໃຫ້ເລີຍ ບໍ່ຕ້ອງໃຫ້ກົດອີກເທື່ອ
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
        const { data } = await api.get<{ data: ExpenseReport }>(
            `/projects/${projectUuid.value}/reports/expenses`,
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

// ---------- ຕາຕະລາງ ----------

const getRowId = (params: { data: { uuid: string } }) => params.data.uuid;

/** ແຖວຍອດລວມທີ່ປັກໄວ້ລຸ່ມສຸດ — ແທນ <tfoot> ເດີມ */
const totalsRow = computed(() =>
    report.value === null
        ? []
        : [{ uuid: '__totals__', title: t('ledger.total') }],
);

function isTotalsRow(data: unknown): boolean {
    return (data as { uuid?: string } | undefined)?.uuid === '__totals__';
}

const columnDefs = computed<ColDef[]>(() => [
    {
        colId: 'index',
        headerName: '#',
        width: 70,
        sortable: false,
        filter: false,
        floatingFilter: false,
        cellClass: 'ag-cell-numeric text-gray-400',
        headerClass: 'ag-right-aligned-header',
        // ລຳດັບຕາມທີ່ເຫັນຢູ່ໜ້າຈໍ ບໍ່ແມ່ນຕາມຂໍ້ມູນ — ຮຽງ/ກັ່ນຕອງແລ້ວຍັງນັບ 1,2,3
        valueGetter: (params) =>
            isTotalsRow(params.data) ? '' : (params.node?.rowIndex ?? 0) + 1,
    },
    {
        field: 'expense_date',
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
        minWidth: 200,
        cellRenderer: TwoLineCell,
        cellRendererParams: {
            secondary: (params: ICellRendererParams) =>
                isTotalsRow(params.data)
                    ? null
                    : ((params.data as Expense | undefined)?.category
                          ?.name_lo ?? null),
            dotColor: (params: ICellRendererParams) =>
                isTotalsRow(params.data)
                    ? null
                    : ((params.data as Expense | undefined)?.category?.color ??
                      null),
        },
    },
    {
        colId: 'payer',
        headerName: t('tx.payer'),
        flex: 1,
        minWidth: 150,
        valueGetter: (params) =>
            isTotalsRow(params.data) ? '' : (params.data?.payer?.name ?? '—'),
    },
    {
        colId: 'amount',
        headerName: t('tx.amount'),
        width: 170,
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
        valueGetter: (params) => params.data?.amount_usd?.minor ?? 0,
        cellRenderer: MoneyCell,
        cellRendererParams: {
            tone: 'expense',
            money: (params: ICellRendererParams) =>
                isTotalsRow(params.data)
                    ? null
                    : (params.data as Expense | undefined)?.amount,
        },
    },
    {
        colId: 'usd',
        headerName: 'USD',
        width: 140,
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
        valueGetter: (params) => params.data?.amount_usd?.minor ?? 0,
        valueFormatter: (params) =>
            isTotalsRow(params.data)
                ? formatMoney(report.value?.summary.total_usd ?? null)
                : formatMoney((params.data as Expense | undefined)?.amount_usd),
        cellClassRules: {
            'font-semibold': (params) => isTotalsRow(params.data),
        },
    },
    {
        field: 'attachments_count',
        headerName: t('tx.evidence'),
        width: 110,
        filter: 'agNumberColumnFilter',
        headerClass: 'ag-center-aligned-header',
        cellClass: 'justify-center',
        cellRenderer: BadgeCell,
        cellRendererParams: {
            color: (params: ICellRendererParams) =>
                Number(params.value ?? 0) > 0 ? 'success' : 'warning',
        },
        valueGetter: (params) =>
            isTotalsRow(params.data)
                ? null
                : (params.data?.attachments_count ?? 0),
    },
    {
        field: 'status',
        headerName: t('tx.status'),
        width: 140,
        cellRenderer: BadgeCell,
        cellRendererParams: {
            color: (params: ICellRendererParams) =>
                isTotalsRow(params.data)
                    ? 'light'
                    : statusColor((params.data as Expense).status),
        },
        valueGetter: (params) =>
            isTotalsRow(params.data) || !params.data
                ? ''
                : t(`ledger.statusLabel.${params.data.status}`),
    },
]);

watch(
    [projectUuid, payerId, categoryId, dateFrom, dateTo, statusMode],
    () => void load(),
);

// ຊ່ອງຄົ້ນຫາລໍໃຫ້ຢຸດພິມກ່ອນ — ບໍ່ດັ່ງນັ້ນຈະໄດ້ 1 request ຕໍ່ 1 ຕົວອັກສອນ
useDebouncedSearch(search, () => void load());

void loadReference();
</script>
