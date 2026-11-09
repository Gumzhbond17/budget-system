<template>
    <AdminLayout>
        <div class="space-y-6">
            <div>
                <h1
                    class="flex items-center gap-2.5 text-title-sm font-semibold text-gray-800 dark:text-white/90"
                >
                    <!-- ໄອຄອນຫົວຂໍ້ໜ້າ — ຄືກັບ PageBreadcrumb ຢູ່ໜ້າອື່ນ.
                         ໜ້ານີ້ບໍ່ໄດ້ໃຊ້ PageBreadcrumb ເພາະບໍ່ມີ breadcrumb
                         ໃຫ້ສະແດງ (ມັນຄືໜ້າຫຼັກ) ຈຶ່ງວາງເອງໃຫ້ໜ້າຕາກົງກັນ -->
                    <span
                        aria-hidden="true"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-500 dark:bg-brand-500/15"
                    >
                        <PageIcon class="h-5 w-5" />
                    </span>

                    {{ t('app.name') }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{
                        t('dashboard.signedInAs', {
                            name: auth.user?.name ?? '',
                        })
                    }}
                </p>
            </div>

            <!--
                ຕົວກັ່ນຕອງແຖວດຽວຢູ່ເທິງສຸດ ຄຸມທຸກຢ່າງທີ່ຢູ່ລຸ່ມ — ບໍ່ແມ່ນຕົວກັ່ນຕອງ
                ແຍກຕໍ່ກາດ. ກາດຕົວເລກ ແລະ ວົງກົມ ຈຶ່ງເວົ້າເຖິງໂຄງການດຽວກັນສະເໝີ.
            -->
            <ProjectPicker v-model="projectUuid" :projects="projects" />

            <div v-if="!projectUuid" class="empty-state">
                {{ t('ledger.selectProjectFirst') }}
            </div>

            <template v-else>
                <!-- ---------- ຕົວເລກຫຼັກ ---------- -->
                <!--
                    ຄ່າເປັນກີບ: ເປັນສະກຸນທີ່ຄົນໃຊ້ລະບົບນີ້ຄິດເປັນທຳມະຊາດ
                    (ຍອດ USD ຢູ່ໃນວົງກົມ ແລະ ໃນລາຍງານ)
                -->
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
                    />
                </div>

                <Alert
                    v-if="loadError"
                    variant="error"
                    :title="t('settings.loadFailed')"
                    :message="loadError"
                />

                <!--
                    ---------- ວົງກົມສະຫຼຸບ ----------
                    ຄູ່ກັນ: ເງິນເຂົ້າມາຈາກໃສ (ຊ້າຍ) ແລະ ເງິນອອກໄປໃສ (ຂວາ).
                    ຢູ່ຈໍແຄບຊ້ອນກັນ ໂດຍລາຍຮັບຢູ່ເທິງ — ລຳດັບດຽວກັນກັບກາດ
                    ຕົວເລກຂ້າງເທິງ ແລະ ກັບບັນຊີແຍກປະເພດ
                -->
                <div class="grid gap-4 xl:grid-cols-2">
                    <BreakdownDonut
                        :rows="revenueSlices"
                        :loading="loading"
                        title="dashboard.revenueByPayer"
                        hint="dashboard.revenueByPayerHint"
                        empty="dashboard.noRevenues"
                        total-label="ledger.totalRevenue"
                        label-heading="dashboard.source"
                    />

                    <BreakdownDonut
                        :rows="expenseSlices"
                        :loading="loading"
                        title="dashboard.expenseByCategory"
                        hint="dashboard.expenseByCategoryHint"
                        empty="dashboard.noExpenses"
                        total-label="dashboard.totalExpense"
                        label-heading="dashboard.category"
                    />
                </div>
            </template>

            <!-- ພິສູດວ່າ SPA ຄຸຍກັບ API ຈິງໄດ້: ດຶງສະກຸນເງິນຈາກ /api/v1/currencies -->
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <h2
                    class="mb-4 text-theme-xl font-semibold text-gray-800 dark:text-white/90"
                >
                    {{ t('dashboard.currencies') }}
                </h2>

                <DataGrid
                    :column-defs="currencyColumnDefs"
                    :row-data="currencies"
                    :loading="loadingCurrencies"
                    :error="currencyError"
                    :get-row-id="getCurrencyRowId"
                />
            </div>
        </div>
    </AdminLayout>
</template>

<script setup lang="ts">
import type { ColDef } from 'ag-grid-community';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { api, toApiError } from '@/api/client';
import BreakdownDonut from '@/components/charts/BreakdownDonut.vue';
import DataGrid from '@/components/common/DataGrid.vue';
import AdminLayout from '@/components/layout/AdminLayout.vue';
import SummaryCard from '@/components/ledger/SummaryCard.vue';
import ProjectPicker from '@/components/transactions/ProjectPicker.vue';
import Alert from '@/components/ui/Alert.vue';
import { useAutoRefresh } from '@/composables/useAutoRefresh';
import { useAuthStore } from '@/stores/auth';
import type { Ledger, LedgerTotals } from '@/types/ledger';
import type {
    BreakdownRow,
    CategoryBreakdown,
    PayerBreakdown,
} from '@/types/reports';
import type { Project } from '@/types/settings';
import ArrowDownIcon from '~icons/tabler/arrow-down-right';
import ArrowUpIcon from '~icons/tabler/arrow-up-right';
import PageIcon from '~icons/tabler/layout-dashboard';
import TargetIcon from '~icons/tabler/target';
import WalletIcon from '~icons/tabler/wallet';

interface Currency {
    code: string;
    name_lo: string;
    name_en: string;
    symbol: string;
    decimal_places: number;
}

const { t, locale } = useI18n();
const auth = useAuthStore();

const projects = ref<Project[]>([]);
const projectUuid = ref('');

const totals = ref<LedgerTotals | null>(null);
const byCategory = ref<CategoryBreakdown[]>([]);
const byPayer = ref<PayerBreakdown[]>([]);
const loading = ref(false);
const loadError = ref<string | null>(null);

/**
 * ແປງເປັນຮູບແບບກາງໃຫ້ວົງກົມ.
 *
 * `seed` ຄື id ຂອງ entity ບໍ່ແມ່ນລຳດັບໃນ array — ສີຈຶ່ງຕິດຢູ່ກັບໝວດ/ແຫຼ່ງທຶນ
 * ນັ້ນຕະຫຼອດ ເຖິງວ່າຍອດຈະປ່ຽນຈົນລຳດັບສະຫຼັບກັນກໍຕາມ. `-1` = ບໍ່ໄດ້ລະບຸ
 * (ໄດ້ສີເທົາສະຫງວນ).
 */
const expenseSlices = computed<BreakdownRow[]>(() =>
    byCategory.value.map((row) => ({
        label: row.label,
        // ໝວດມີສີຂອງຕົນເອງໃນຖານຂໍ້ມູນ — ຈານສີບໍ່ຖືກໃຊ້ຢູ່ຝັ່ງນີ້
        color: row.color,
        seed: row.category_id ?? -1,
        count: row.count,
        usd: row.usd,
        percentage: row.percentage,
    })),
);

const revenueSlices = computed<BreakdownRow[]>(() =>
    byPayer.value.map((row) => ({
        label: row.label,
        // ແຫຼ່ງທຶນບໍ່ມີສີໃນຖານຂໍ້ມູນ — ໃຫ້ວົງກົມເລືອກຈາກຈານສີຕາມ seed
        color: null,
        seed: row.payer_id ?? -1,
        count: row.count,
        usd: row.usd,
        percentage: row.percentage,
    })),
);

const currencies = ref<Currency[]>([]);
const loadingCurrencies = ref(true);
const currencyError = ref<string | null>(null);

// ---------- ຕາຕະລາງສະກຸນເງິນ ----------

const getCurrencyRowId = (params: { data: Currency }) => params.data.code;

const currencyColumnDefs = computed<ColDef[]>(() => [
    {
        colId: 'code',
        headerName: t('dashboard.code'),
        width: 130,
        cellClass: 'font-medium text-gray-800 dark:text-white/90',
        valueGetter: (params) =>
            params.data ? `${params.data.symbol} ${params.data.code}` : '',
    },
    {
        colId: 'name',
        headerName: t('dashboard.currencyName'),
        flex: 1,
        minWidth: 160,
        cellClass: 'text-gray-500 dark:text-gray-400',
        // ຊື່ຕາມພາສາທີ່ເລືອກຢູ່ — ບໍ່ແມ່ນ name_lo ຕາຍຕົວ
        valueGetter: (params) =>
            locale.value === 'lo'
                ? (params.data?.name_lo ?? '')
                : (params.data?.name_en ?? ''),
    },
    {
        field: 'decimal_places',
        headerName: t('dashboard.decimalPlaces'),
        width: 140,
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric text-gray-500 dark:text-gray-400',
        headerClass: 'ag-right-aligned-header',
    },
]);

/**
 * @param silent ຮອບໂຫຼດຄືນອັດຕະໂນມັດ — ບໍ່ສະແດງພາບໝູນ ແລະ ຖ້າລົ້ມໃຫ້ຖືຄ່າ
 *               ເກົ່າໄວ້ ແທນທີ່ຈະລ້າງກາດເປັນ 0 (ຕົວເລກທີ່ກະພິບເປັນສູນແລ້ວ
 *               ກັບມາ ອ່ານຄືລະບົບພັງ)
 */
async function load(silent = false): Promise<void> {
    if (!projectUuid.value) {
        return;
    }

    if (!silent) {
        loading.value = true;
        loadError.value = null;
    }

    try {
        // ສາມ endpoint ນີ້ຄິດຈາກຕົວກັ່ນຕອງດຽວກັນ (ໂຄງການດຽວກັນ, ສະຖານະ
        // ອະນຸມັດແລ້ວ) ຈຶ່ງບໍ່ມີທາງທີ່ກາດກັບວົງກົມຈະຂັດກັນເອງ
        const [ledgerRes, categoryRes, payerRes] = await Promise.all([
            api.get<{ data: Ledger }>(`/projects/${projectUuid.value}/ledger`),
            api.get<{ data: CategoryBreakdown[] }>(
                `/projects/${projectUuid.value}/reports/by-category`,
            ),
            api.get<{ data: PayerBreakdown[] }>(
                `/projects/${projectUuid.value}/reports/revenue-by-payer`,
            ),
        ]);

        totals.value = ledgerRes.data.data.totals;
        byCategory.value = categoryRes.data.data;
        byPayer.value = payerRes.data.data;
    } catch (e) {
        if (silent) {
            return;
        }

        loadError.value = toApiError(e).message;
        totals.value = null;
        byCategory.value = [];
        byPayer.value = [];
    } finally {
        if (!silent) {
            loading.value = false;
        }
    }
}

// ຫຸ້ມດ້ວຍ arrow: watcher ສົ່ງ (ຄ່າໃໝ່, ຄ່າເກົ່າ) ເຊິ່ງຈະຖືກອ່ານເປັນ `silent`
watch(projectUuid, () => void load());

useAutoRefresh(() => load(true));

void (async () => {
    try {
        const [projectRes, currencyRes] = await Promise.all([
            api.get<{ data: Project[] }>('/projects'),
            api.get<{ data: Currency[] }>('/currencies'),
        ]);

        projects.value = projectRes.data.data;
        currencies.value = currencyRes.data.data;

        if (projects.value.length > 0) {
            projectUuid.value = projects.value[0].uuid;
        }
    } catch (e) {
        currencyError.value = toApiError(e).message;
    } finally {
        loadingCurrencies.value = false;
    }
})();
</script>
