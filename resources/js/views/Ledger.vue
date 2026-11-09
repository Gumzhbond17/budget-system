<template>
    <AdminLayout>
        <PageBreadcrumb page-title="ledger.title" :icon="PageIcon" />

        <!-- ---------- ຕົວເລືອກໂຄງການ + ຊ່ວງວັນທີ ---------- -->
        <div
            class="mb-6 flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 sm:flex-row sm:items-end dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <label class="flex-1">
                <span
                    class="mb-1.5 block text-theme-xs font-medium text-gray-500 dark:text-gray-400"
                >
                    {{ t('ledger.project') }}
                </span>
                <SearchableSelect
                    v-model="projectUuid"
                    variant="filter"
                    class="w-full"
                    :options="projectOptions"
                    placeholder="ledger.selectProject"
                />
            </label>

            <label>
                <span
                    class="mb-1.5 block text-theme-xs font-medium text-gray-500 dark:text-gray-400"
                >
                    {{ t('ledger.dateFrom') }}
                </span>
                <DateInput v-model="dateFrom" class="filter-control pl-11" />
            </label>

            <label>
                <span
                    class="mb-1.5 block text-theme-xs font-medium text-gray-500 dark:text-gray-400"
                >
                    {{ t('ledger.dateTo') }}
                </span>
                <DateInput v-model="dateTo" class="filter-control pl-11" />
            </label>

            <label>
                <span
                    class="mb-1.5 block text-theme-xs font-medium text-gray-500 dark:text-gray-400"
                >
                    {{ t('ledger.status') }}
                </span>
                <SearchableSelect
                    :model-value="statusMode"
                    variant="filter"
                    class="sm:w-48"
                    :options="statusOptions"
                    @update:model-value="
                        statusMode = $event as 'approved' | 'all'
                    "
                />
            </label>
        </div>

        <div v-if="!projectUuid" class="empty-state">
            {{ t('ledger.selectProjectFirst') }}
        </div>

        <template v-else>
            <!-- ---------- ຍອດລວມ ---------- -->
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

            <!-- ---------- ແຖວບັນຊີ ---------- -->
            <div
                class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <div
                    class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800"
                >
                    <h3
                        class="text-base font-medium text-gray-800 dark:text-white/90"
                    >
                        {{ t('ledger.entries') }}
                    </h3>
                    <span
                        class="text-theme-xs text-gray-500 dark:text-gray-400"
                    >
                        {{
                            t('ledger.entryCount', {
                                count: totals?.entry_count ?? 0,
                            })
                        }}
                    </span>
                </div>

                <DataGrid
                    :column-defs="columnDefs"
                    :row-data="entries"
                    :loading="loading"
                    :error="error"
                    :get-row-id="getRowId"
                    :row-height="60"
                    :col-defaults="ledgerColDefaults"
                    :pinned-bottom-row-data="totalsRow"
                    :empty-label="t('ledger.empty')"
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
import SearchableSelect from '@/components/common/SearchableSelect.vue';
import DateInput from '@/components/forms/DateInput.vue';
import MoneyCell from '@/components/grid/MoneyCell.vue';
import TwoLineCell from '@/components/grid/TwoLineCell.vue';
import AdminLayout from '@/components/layout/AdminLayout.vue';
import SummaryCard from '@/components/ledger/SummaryCard.vue';
import { useAutoRefresh } from '@/composables/useAutoRefresh';
import type { Ledger, LedgerEntry, LedgerTotals } from '@/types/ledger';
import type { Money, Project } from '@/types/settings';
import { formatDate } from '@/utils/date';
import { formatMoney, formatMoneyWithCurrency } from '@/utils/money';
import ArrowDownIcon from '~icons/tabler/arrow-down-right';
import ArrowUpIcon from '~icons/tabler/arrow-up-right';
import PageIcon from '~icons/tabler/book-2';
import TargetIcon from '~icons/tabler/target';
import WalletIcon from '~icons/tabler/wallet';

const { t } = useI18n();

const projects = ref<Project[]>([]);
const projectUuid = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const statusMode = ref<'approved' | 'all'>('approved');

const entries = ref<LedgerEntry[]>([]);
const totals = ref<LedgerTotals | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

const projectOptions = computed(() =>
    projects.value.map((project) => ({
        value: project.uuid,
        label: project.name,
        secondary: project.code,
        translate: false,
    })),
);

const statusOptions = [
    { value: 'approved', label: 'ledger.approvedOnly' },
    { value: 'all', label: 'ledger.allStatuses' },
];

/**
 * ແຖວຍ່ອຍໃຕ້ຊື່ລາຍການ. ຄໍລຳຈຳນວນເປັນກີບແລ້ວ ຈຶ່ງເອົາ USD ມາໄວ້ນີ້ເປັນຕົວທຽບ
 * ແລະ ບອກຈຳນວນເດີມເມື່ອບັນທຶກເປັນສະກຸນອື່ນ — ລາຍການທີ່ບັນທຶກເປັນກີບຢູ່ແລ້ວ
 * ຈະຊ້ຳກັບຄໍລຳ ຈຶ່ງບໍ່ຕ້ອງບອກ.
 */
function amountNote(entry: LedgerEntry): string {
    const usd = formatMoneyWithCurrency(entry.amount_usd);

    return entry.amount.currency === 'LAK'
        ? usd
        : `${formatMoneyWithCurrency(entry.amount)} · ${usd}`;
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

async function loadProjects(): Promise<void> {
    try {
        const { data } = await api.get<{ data: Project[] }>('/projects');
        projects.value = data.data;

        // ມີໂຄງການດຽວ = ເລືອກໃຫ້ເລີຍ ບໍ່ຕ້ອງໃຫ້ກົດອີກເທື່ອ
        if (projects.value.length > 0 && !projectUuid.value) {
            projectUuid.value = projects.value[0].uuid;
        }
    } catch (e) {
        error.value = toApiError(e).message;
    }
}

/**
 * @param silent ຮອບໂຫຼດຄືນອັດຕະໂນມັດ — ບໍ່ຕ້ອງສະແດງ "ກຳລັງໂຫຼດ..." ແລະ
 *               ຖ້າລົ້ມໃຫ້ຮັກສາແຖວເກົ່າໄວ້ ແທນທີ່ຈະລ້າງຕາຕະລາງຖິ້ມ
 */
async function loadLedger(silent = false): Promise<void> {
    if (!projectUuid.value) {
        return;
    }

    if (!silent) {
        loading.value = true;
        error.value = null;
    }

    try {
        const params: Record<string, string> = {};

        if (dateFrom.value) {
            params.date_from = dateFrom.value;
        }

        if (dateTo.value) {
            params.date_to = dateTo.value;
        }

        if (statusMode.value === 'all') {
            params.statuses = 'all';
        }

        const { data } = await api.get<{ data: Ledger }>(
            `/projects/${projectUuid.value}/ledger`,
            { params },
        );

        entries.value = data.data.entries;
        totals.value = data.data.totals;
    } catch (e) {
        if (silent) {
            return;
        }

        error.value = toApiError(e).message;
        entries.value = [];
        totals.value = null;
    } finally {
        if (!silent) {
            loading.value = false;
        }
    }
}

// ---------- ຕາຕະລາງ ----------

const getRowId = (params: { data: LedgerEntry }) => params.data.uuid;

/**
 * ຫ້າມຮຽງໃໝ່ຢູ່ໜ້ານີ້ — ບໍ່ແມ່ນຄວາມຂີ້ຄ້ານ.
 *
 * ຖັນ "ຍອດຄົງເຫຼືອ" ເປັນຍອດສະສົມທີ່ server ຄິດຕາມລຳດັບວັນທີ. ຖ້າຜູ້ໃຊ້
 * ຮຽງໃໝ່ຕາມຈຳນວນເງິນ ຕົວເລກໃນຖັນນັ້ນຈະຍັງເປັນຄ່າເກົ່າຂອງແຕ່ລະແຖວ ແຕ່
 * ລຽນກັນລົງມາແບບບໍ່ມີຄວາມໝາຍ — ອ່ານແລ້ວເບິ່ງຄືບັນຊີພັງ. ການກັ່ນຕອງບໍ່ມີ
 * ບັນຫານີ້ (ຕັດແຖວອອກ ບໍ່ໄດ້ສະຫຼັບລຳດັບ) ຈຶ່ງເປີດໄວ້ຕາມປົກກະຕິ.
 */
const ledgerColDefaults: ColDef = { sortable: false };

/** ແຖວຍອດລວມທີ່ປັກໄວ້ລຸ່ມສຸດ — ແທນ <tfoot> ເດີມ */
const totalsRow = computed(() =>
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

/** ແຖວຍອດລວມບໍ່ມີ `type` — ໃຊ້ແຍກມັນອອກຈາກແຖວຂໍ້ມູນຈິງໃນ renderer */
function isTotalsRow(data: unknown): boolean {
    return (data as { uuid?: string } | undefined)?.uuid === '__totals__';
}

const columnDefs = computed<ColDef[]>(() => [
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
        minWidth: 240,
        cellRenderer: TwoLineCell,
        cellRendererParams: {
            secondary: (params: ICellRendererParams<LedgerEntry>) => {
                const entry = params.data;

                if (!entry || isTotalsRow(entry)) {
                    return null;
                }

                return [
                    entry.counterparty,
                    amountNote(entry),
                    // ສະຖານະມີຄວາມໝາຍສະເພາະຕອນນັບລວມທຸກສະຖານະ —
                    // ຢູ່ໂໝດ "ອະນຸມັດແລ້ວ" ທຸກແຖວຄືກັນໝົດ ຈຶ່ງເປັນສຽງລົບກວນ
                    statusMode.value === 'all'
                        ? t(`ledger.statusLabel.${entry.status}`)
                        : null,
                ]
                    .filter(Boolean)
                    .join(' · ');
            },
        },
    },
    // ສອງຄໍລຳແຍກ (ເດບິດ/ເຄຣດິດ) ບໍ່ແມ່ນຄໍລຳດຽວທີ່ມີເຄື່ອງໝາຍ — ນີ້ຄືຮູບແບບ
    // ບັນຊີແຍກປະເພດມາດຕະຖານ ແລະ ອ່ານງ່າຍກວ່າເມື່ອກວາດຕາຫາລາຍການປະເພດດຽວ
    {
        colId: 'revenue',
        headerName: `${t('ledger.revenue')} (LAK)`,
        width: 160,
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
        valueGetter: (params) =>
            isTotalsRow(params.data)
                ? ((params.data as { revenue?: Money }).revenue?.minor ?? 0)
                : params.data?.type === 'revenue'
                  ? params.data.amount_lak.minor
                  : null,
        cellRenderer: MoneyCell,
        cellRendererParams: {
            tone: 'revenue',
            money: (params: ICellRendererParams) =>
                isTotalsRow(params.data)
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
        width: 160,
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
        valueGetter: (params) =>
            isTotalsRow(params.data)
                ? ((params.data as { expense?: Money }).expense?.minor ?? 0)
                : params.data?.type === 'expense'
                  ? params.data.amount_lak.minor
                  : null,
        cellRenderer: MoneyCell,
        cellRendererParams: {
            tone: 'expense',
            money: (params: ICellRendererParams) =>
                isTotalsRow(params.data)
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
        width: 180,
        filter: 'agNumberColumnFilter',
        cellClass: (params) =>
            [
                'ag-cell-numeric font-medium',
                (params.data?.balance_lak?.minor ?? 0) < 0
                    ? 'text-error-600 dark:text-error-500'
                    : 'text-gray-800 dark:text-white/90',
            ].join(' '),
        headerClass: 'ag-right-aligned-header',
        valueGetter: (params) => params.data?.balance_lak?.minor ?? 0,
        valueFormatter: (params) =>
            formatMoney(
                (params.data as { balance_lak?: Money } | undefined)
                    ?.balance_lak ?? null,
            ),
    },
]);

// ຫຸ້ມດ້ວຍ arrow: watcher ສົ່ງ array ຂອງຄ່າໃໝ່ເປັນ argument ທຳອິດ —
// ສົ່ງ `loadLedger` ກົງໆຈະເຮັດໃຫ້ array ນັ້ນຖືກອ່ານເປັນ `silent`
watch([projectUuid, dateFrom, dateTo, statusMode], () => void loadLedger());

// ບໍ່ມີຟອມຢູ່ໜ້ານີ້ — ເປັນໜ້າອ່ານຢ່າງດຽວ ຈຶ່ງບໍ່ຕ້ອງ pause
useAutoRefresh(() => loadLedger(true));

void loadProjects();
</script>
