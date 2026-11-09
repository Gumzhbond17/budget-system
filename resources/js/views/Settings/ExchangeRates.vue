<template>
    <AdminLayout>
        <!--
            ແຖບສະຖານະ "ມື້ນີ້" — ຄຳຖາມດຽວທີ່ຄົນເປີດໜ້ານີ້ຢາກຮູ້ທຸກເຊົ້າ:
            ຕັ້ງອັດຕາຂອງມື້ນີ້ແລ້ວບໍ? ຖ້າຍັງ ຄ່າທີ່ຟອມທຸລະກຳຈະເຕີມໃຫ້ແມ່ນຂອງມື້ເກົ່າ.
        -->
        <div
            v-if="dailyStatus.length > 0"
            class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
        >
            <div
                v-for="status in dailyStatus"
                :key="status.code"
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <!--
                            ສະແດງເປັນກີບແບບປ້າຍ BCEL: ແຖວ LAK ຄືຄູ່ "USD → LAK"
                            (1 USD = 22,239 ກີບ), ແຖວ THB ຄື 1 THB = 675.48 ກີບ
                        -->
                        <p
                            class="font-mono text-sm font-medium text-gray-500 dark:text-gray-400"
                        >
                            {{ pairLabel(status.code) }}
                        </p>
                        <p
                            class="mt-1 text-title-sm font-bold text-gray-800 tabular-nums dark:text-white/90"
                        >
                            {{
                                status.rate_lak
                                    ? formatRate(status.rate_lak)
                                    : '—'
                            }}
                        </p>
                        <p
                            class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400"
                        >
                            {{
                                t('settings.rates.lakPer', {
                                    code: quoteCode(status.code),
                                })
                            }}
                        </p>
                    </div>

                    <Badge size="sm" :color="statusColor(status)">
                        {{ statusLabel(status) }}
                    </Badge>
                </div>

                <p
                    v-if="status.effective_date && !status.isToday"
                    class="mt-3 text-theme-xs text-warning-600 dark:text-warning-400"
                >
                    {{
                        t('settings.rates.staleSince', {
                            date: formatDate(status.effective_date),
                        })
                    }}
                </p>

                <button
                    v-if="canWrite"
                    class="mt-4 text-sm font-medium text-brand-500 hover:text-brand-600"
                    @click="openCreate(status.code)"
                >
                    {{
                        status.isToday
                            ? t('settings.rates.updateToday')
                            : t('settings.rates.setToday')
                    }}
                </button>
            </div>
        </div>

        <SettingsPage
            title="settings.rates.title"
            :icon="FormIcon"
            description="settings.rates.description"
            :error="listError"
            :can-create="canWrite"
            create-label="settings.rates.create"
            :searchable="false"
            @create="openCreate()"
        >
            <template #filters>
                <SearchableSelect
                    v-model="filters.currency"
                    variant="filter"
                    class="sm:w-48"
                    :aria-label="t('settings.rates.currency')"
                    :options="currencyOptions"
                    nullable
                    null-label="settings.rates.allCurrencies"
                />

                <!--
                    ປ້າຍຊື່ເປັນ sr-only: ແຖວຕົວກອງແຄບເກີນກວ່າຈະວາງປ້າຍໄວ້ເທິງ
                    ແຕ່ຊ່ອງວັນທີສອງອັນຕິດກັນຕ້ອງແຍກອອກຈາກກັນໄດ້ດ້ວຍສຽງອ່ານ.
                    ໃສ່ aria-label ໃສ່ DateInput ບໍ່ໄດ້ຜົນ — flatpickr ສ້າງ input
                    ທີ່ເຫັນໄດ້ອັນໃໝ່ ແລ້ວ copy ມາແຕ່ class/placeholder/disabled
                -->
                <label class="relative">
                    <span class="sr-only">{{
                        t('settings.rates.dateFrom')
                    }}</span>
                    <DateInput
                        v-model="filters.date_from"
                        class="h-10 w-36 rounded-lg border border-gray-300 bg-transparent pr-3 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-none dark:border-gray-700 dark:text-white/90 dark:placeholder:text-white/30"
                    />
                </label>

                <label class="relative">
                    <span class="sr-only">{{
                        t('settings.rates.dateTo')
                    }}</span>
                    <DateInput
                        v-model="filters.date_to"
                        class="h-10 w-36 rounded-lg border border-gray-300 bg-transparent pr-3 pl-11 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-none dark:border-gray-700 dark:text-white/90 dark:placeholder:text-white/30"
                    />
                </label>
            </template>

            <DataGrid
                :column-defs="columnDefs"
                :row-data="items"
                :loading="loading"
                :get-row-id="getRowId"
                :empty-label="t('settings.rates.empty')"
            />

            <!-- ຕາຕະລາງນີ້ໃຫຍ່ຂຶ້ນທຸກມື້ — ບອກໃຫ້ຮູ້ວ່າຍັງມີອີກ ດີກວ່າຕັດຖິ້ມງຽບໆ -->
            <p
                v-if="total > items.length"
                class="px-5 py-3 text-theme-xs text-gray-500 dark:text-gray-400"
            >
                {{
                    t('settings.rates.truncated', {
                        shown: items.length,
                        total,
                    })
                }}
            </p>
        </SettingsPage>

        <FormModal
            :open="modalOpen"
            :icon="FormIcon"
            :title="editing ? 'settings.rates.edit' : 'settings.rates.create'"
            subtitle="settings.rates.formHint"
            :saving="saving"
            :error="error"
            @close="closeModal"
            @submit="submit"
        >
            <div class="grid gap-5 sm:grid-cols-2">
                <SelectField
                    v-model="form.currency_code"
                    label="settings.rates.currency"
                    name="currency_code"
                    :error="error"
                    required
                    :options="currencyOptions"
                />

                <DatePicker
                    v-model="form.effective_date"
                    label="settings.rates.effectiveDate"
                    name="effective_date"
                    :error="error"
                    required
                    hint="settings.rates.effectiveDateHint"
                />

                <TextField
                    v-model="form.rate_lak"
                    :label="
                        t('settings.rates.rateLak', {
                            code: quoteCode(form.currency_code),
                        })
                    "
                    name="rate_lak"
                    :error="error"
                    required
                    :placeholder="
                        form.currency_code === 'LAK' ? '22239' : '675.48'
                    "
                    :hint="
                        t('settings.rates.rateLakHint', {
                            code: quoteCode(form.currency_code),
                        })
                    "
                />

                <SelectField
                    v-model="form.source"
                    label="settings.rates.source"
                    name="source"
                    :error="error"
                    :options="sourceOptions"
                />

                <!--
                    Guard rail ຂອງພາກ 3.2 — ຈັບການພິມ "2117" ແທນ "21170"
                    ທີ່ເຮັດໃຫ້ຍອດໃນ Excel ເດີມຜິດໄປ 5,314 USD. ເປັນຄຳເຕືອນ
                    ບໍ່ແມ່ນການປິດກັ້ນ: ອັດຕາຜິດປົກກະຕິກໍເກີດຂຶ້ນຈິງໄດ້.
                -->
                <div v-if="rateWarning" class="sm:col-span-2">
                    <Alert
                        variant="warning"
                        :title="t('settings.rates.checkTitle')"
                        :message="rateWarning"
                    />
                </div>

                <p
                    v-else-if="referenceRate"
                    class="text-theme-xs text-gray-500 sm:col-span-2 dark:text-gray-400"
                >
                    {{
                        t('settings.rates.previous', {
                            rate: formatRate(referenceRate),
                        })
                    }}
                </p>
            </div>
        </FormModal>
    </AdminLayout>
</template>

<script setup lang="ts">
import type { ColDef, ICellRendererParams } from 'ag-grid-community';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { api } from '@/api/client';
import DataGrid from '@/components/common/DataGrid.vue';
import SearchableSelect from '@/components/common/SearchableSelect.vue';
import DateInput from '@/components/forms/DateInput.vue';
import DatePicker from '@/components/forms/DatePicker.vue';
import ActionsCell from '@/components/grid/ActionsCell.vue';
import BadgeCell from '@/components/grid/BadgeCell.vue';
import TwoLineCell from '@/components/grid/TwoLineCell.vue';
import AdminLayout from '@/components/layout/AdminLayout.vue';
import FormModal from '@/components/settings/FormModal.vue';
import SelectField from '@/components/settings/SelectField.vue';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import TextField from '@/components/settings/TextField.vue';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import { useAutoRefresh } from '@/composables/useAutoRefresh';
import { useCrud } from '@/composables/useCrud';
import { actionColDef } from '@/lib/ag-grid';
import { useAuthStore } from '@/stores/auth';
import type { Currency, ExchangeRate, RateLookup } from '@/types/settings';
import { trimTrailingZeros } from '@/utils/money';
import FormIcon from '~icons/tabler/arrows-exchange';

/** per_page ສູງສຸດທີ່ server ຍອມ (ExchangeRateController) */
const PER_PAGE = 200;

/** USD ບໍ່ມີແຖວໃນຕາຕະລາງ: rate ຕໍ່ຕົວມັນເອງ = 1 ສະເໝີ */
const PIVOT = 'USD';

const { t } = useI18n();
const auth = useAuthStore();

const {
    items,
    total,
    loading,
    saving,
    error,
    fetchAll,
    refresh,
    save,
    clearError,
} = useCrud<ExchangeRate & Record<string, unknown>>('/exchange-rates', 'id', {
    // ອັດຕາບໍ່ມີ "ຊື່" — ສາມຢ່າງນີ້ຄືສິ່ງທີ່ລະບຸແຖວ ແລະ ເປັນສິ່ງທີ່ຕ້ອງ
    // ອ່ານຢືນຢັນທີ່ສຸດ (ພິມ 2117 ແທນ 21170 ຈະເຫັນຢູ່ນີ້ທັນທີ)
    describe: (rate) =>
        `1 ${rate.quote_code} = ${rate.rate_lak ?? '—'} LAK (${rate.effective_date})`,
});

const canWrite = computed(() => auth.user?.can_manage_master_data === true);

const listError = computed(() =>
    loading.value || modalOpen.value ? null : (error.value?.message ?? null),
);

/** ວັນທີມື້ນີ້ຕາມ browser ໃນຮູບແບບ YYYY-MM-DD (ບໍ່ຜ່ານ UTC — ຈະຄາດເຄື່ອນ 1 ມື້) */
const today = new Date().toLocaleDateString('sv-SE');

// ---------- ສະກຸນເງິນ ----------
const currencies = ref<Currency[]>([]);

/** ສະກຸນທີ່ຕ້ອງມີອັດຕາ — USD ບໍ່ຕ້ອງ ເພາະເປັນຕົວຕັ້ງ */
const convertibleCurrencies = computed(() =>
    currencies.value.filter((currency) => currency.code !== PIVOT),
);

/**
 * ອັດຕາຖືກຕັ້ງເປັນກີບ: ແຖວ LAK ຄື "1 USD = ? ກີບ" ຈຶ່ງສະແດງເປັນ USD,
 * ສະກຸນອື່ນສະແດງເປັນຕົວມັນເອງ (1 THB = ? ກີບ). ກົງກັບ ExchangeRate::quoteCodeFor()
 */
function quoteCode(code: string): string {
    return code === 'LAK' ? PIVOT : code;
}

function pairLabel(code: string): string {
    return `${quoteCode(code)} → LAK`;
}

const currencyOptions = computed(() =>
    convertibleCurrencies.value.map((currency) => ({
        value: currency.code,
        label: pairLabel(currency.code),
        translate: false,
    })),
);

async function loadCurrencies(): Promise<void> {
    try {
        const { data } = await api.get<{ data: Currency[] }>('/currencies');
        currencies.value = data.data;
    } catch {
        currencies.value = [];
    }
}

// ---------- ສະຖານະ "ມື້ນີ້" ----------
interface DailyStatus extends RateLookup {
    code: string;
    isToday: boolean;
}

const dailyStatus = ref<DailyStatus[]>([]);

/**
 * ຖາມ server ວ່າອັດຕາທີ່ **ມີຜົນ** ຂອງມື້ນີ້ຄືອັນໃດ ແລະ ມາຈາກວັນທີໃດ.
 *
 * ໃຊ້ endpoint ດຽວກັນກັບທີ່ຟອມທຸລະກຳໃຊ້ເຕີມອັດຕາໃຫ້ອັດຕະໂນມັດ — ດັ່ງນັ້ນສິ່ງ
 * ທີ່ເຫັນຢູ່ນີ້ຄືສິ່ງທີ່ຄົນບັນທຶກລາຍຈ່າຍຈະໄດ້ຮັບຈິງ ບໍ່ແມ່ນການຄິດຄືນຢູ່ browser.
 */
async function loadDailyStatus(): Promise<void> {
    dailyStatus.value = await Promise.all(
        convertibleCurrencies.value.map(async (currency) => {
            try {
                const { data } = await api.get<RateLookup>(
                    '/exchange-rates/lookup',
                    { params: { currency: currency.code, date: today } },
                );

                return {
                    ...data,
                    code: currency.code,
                    isToday: data.effective_date === today,
                };
            } catch {
                // 404 = ຍັງບໍ່ມີອັດຕາໃດເລີຍສຳລັບສະກຸນນີ້ — ນີ້ຄືສະຖານະທີ່ຕ້ອງເຫັນ
                // ຊັດເຈນທີ່ສຸດ: ບໍ່ມີອັດຕາ = ບັນທຶກລາຍການສະກຸນນີ້ບໍ່ໄດ້ເລີຍ
                return {
                    rate: null,
                    rate_lak: null,
                    currency: currency.code,
                    effective_date: null,
                    code: currency.code,
                    isToday: false,
                };
            }
        }),
    );
}

function statusColor(status: DailyStatus): 'success' | 'warning' | 'error' {
    if (!status.rate_lak) {
        return 'error';
    }

    return status.isToday ? 'success' : 'warning';
}

function statusLabel(status: DailyStatus): string {
    if (!status.rate_lak) {
        return t('settings.rates.statusMissing');
    }

    return status.isToday
        ? t('settings.rates.statusToday')
        : t('settings.rates.statusStale');
}

// ---------- ຕົວກອງ ----------
const filters = reactive({ currency: '', date_from: '', date_to: '' });

function load(): void {
    void fetchAll({
        per_page: PER_PAGE,
        ...(filters.currency ? { currency: filters.currency } : {}),
        ...(filters.date_from ? { date_from: filters.date_from } : {}),
        ...(filters.date_to ? { date_to: filters.date_to } : {}),
    });
}

watch(filters, load);

onMounted(async () => {
    load();
    await loadCurrencies();
    await loadDailyStatus();
});

/**
 * ໜ້ານີ້ດຶງຄືນສອງຢ່າງ: ຕາຕະລາງອັດຕາ ແລະ ປ້າຍ "ມື້ນີ້ຕັ້ງແລ້ວບໍ".
 *
 * ປ້າຍນັ້ນສຳຄັນກວ່າຕາຕະລາງເສຍອີກ — ມັນຄືສິ່ງທີ່ບອກວ່າຄົນອື່ນຕັ້ງອັດຕາຂອງ
 * ມື້ນີ້ໃຫ້ແລ້ວ ຫຼື ຍັງ. ຖ້າດຶງແຕ່ຕາຕະລາງ ປ້າຍຈະຄ້າງຢູ່ "ຍັງບໍ່ໄດ້ຕັ້ງ"
 * ທັງທີ່ແຖວໃໝ່ໂຜ່ຢູ່ໃນຕາຕະລາງແລ້ວ.
 *
 * ບໍ່ມີ `removing` ຢູ່ໜ້ານີ້ — ອັດຕາແລກປ່ຽນບໍ່ມີ route ລຶບ (ເບິ່ງ api.php)
 */
useAutoRefresh(
    async () => {
        await refresh();
        await loadDailyStatus();
    },
    { paused: () => modalOpen.value },
);

// ---------- ການສະແດງຜົນ ----------
/**
 * "21760.0000000000" → "21,760" ແລະ "32.2200000000" → "32.22".
 *
 * ຄໍລຳເປັນ decimal(24,10) ຈຶ່ງເຕັມໄປດ້ວຍສູນທ້າຍທີ່ບໍ່ມີຄວາມໝາຍ. ຕັດຢູ່ຊັ້ນ
 * ສະແດງຜົນເທົ່ານັ້ນ — ຄ່າທີ່ສົ່ງກັບໄປ server ຍັງເປັນ string ດິບ.
 */
function formatRate(value: string): string {
    const trimmed = value.includes('.')
        ? value.replace(/0+$/, '').replace(/\.$/, '')
        : value;

    const [whole, fraction] = trimmed.split('.');
    const grouped = Number(whole).toLocaleString('en-US');

    return fraction ? `${grouped}.${fraction}` : grouped;
}

// ---------- ຕາຕະລາງ ----------

const getRowId = (params: { data: ExchangeRate }) => String(params.data.id);

const columnDefs = computed<ColDef[]>(() => [
    {
        field: 'effective_date',
        headerName: t('settings.rates.effectiveDate'),
        width: 190,
        // ກັ່ນຕອງດ້ວຍປະຕິທິນ ບໍ່ແມ່ນຂໍ້ຄວາມ — ຄ່າດິບເປັນ ປ-ດ-ວ ຢູ່ແລ້ວ
        filter: 'agDateColumnFilter',
        cellRenderer: TwoLineCell,
        cellRendererParams: {
            badge: (params: ICellRendererParams<ExchangeRate>) =>
                params.data?.effective_date === today
                    ? {
                          label: t('settings.rates.todayBadge'),
                          color: 'success' as const,
                      }
                    : null,
        },
        valueFormatter: (params) =>
            params.value ? formatDate(params.value) : '',
    },
    {
        field: 'currency_code',
        headerName: t('settings.rates.currency'),
        width: 140,
        cellClass: 'font-mono',
        valueGetter: (params) =>
            params.data ? pairLabel(params.data.currency_code) : '',
    },
    {
        field: 'rate_lak',
        headerName: t('settings.rates.rateLakColumn'),
        width: 160,
        // ຮຽງເປັນຕົວເລກ: ຄ່າມາເປັນ string decimal(24,10) ຈາກ server ຈຶ່ງ
        // ຕ້ອງແປງກ່ອນ ບໍ່ດັ່ງນັ້ນ "9" ຈະຖືກຖືວ່າໃຫຍ່ກວ່າ "21760"
        filter: 'agNumberColumnFilter',
        cellClass:
            'ag-cell-numeric font-medium text-gray-800 dark:text-white/90',
        headerClass: 'ag-right-aligned-header',
        valueGetter: (params) =>
            params.data?.rate_lak ? Number(params.data.rate_lak) : null,
        valueFormatter: (params) => {
            const rate = (params.data as ExchangeRate | undefined)?.rate_lak;

            return rate ? formatRate(rate) : '—';
        },
    },
    {
        field: 'source',
        headerName: t('settings.rates.source'),
        width: 140,
        cellRenderer: BadgeCell,
        valueGetter: (params) =>
            params.data
                ? t(`settings.rates.sources.${params.data.source}`)
                : '',
    },
    {
        field: 'created_by_name',
        headerName: t('settings.rates.setBy'),
        flex: 1,
        minWidth: 140,
        cellClass: 'text-gray-500 dark:text-gray-400',
        valueFormatter: (params) => params.value ?? '—',
    },
    ...(canWrite.value
        ? [
              {
                  ...actionColDef,
                  colId: 'actions',
                  headerName: t('common.actions'),
                  width: 110,
                  cellRenderer: ActionsCell,
                  cellRendererParams: {
                      actions: (params: ICellRendererParams<ExchangeRate>) =>
                          params.data
                              ? [
                                    {
                                        label: t('common.edit'),
                                        onClick: () =>
                                            openEdit(
                                                params.data as ExchangeRate,
                                            ),
                                    },
                                ]
                              : [],
                  },
              },
          ]
        : []),
]);

function formatDate(value: string): string {
    const [year, month, day] = value.split('-');

    return `${day}/${month}/${year}`;
}

// ---------- ຟອມ ----------
const modalOpen = ref(false);
const editing = ref<ExchangeRate | null>(null);
const rateWarning = ref<string | null>(null);
const referenceRate = ref<string | null>(null);

const blankForm = {
    currency_code: '',
    effective_date: today,
    /** ກີບຕໍ່ 1 USD (ແຖວ LAK) ຫຼື ກີບຕໍ່ 1 ໜ່ວຍ (THB) — server ແປງເປັນ "ຕໍ່ 1 USD" ເອງ */
    rate_lak: '',
    source: 'manual',
};

const form = reactive({ ...blankForm });

const sourceOptions = [
    { value: 'manual', label: 'settings.rates.sources.manual' },
    { value: 'bcel', label: 'settings.rates.sources.bcel' },
    { value: 'bol', label: 'settings.rates.sources.bol' },
    { value: 'api', label: 'settings.rates.sources.api' },
];

/** ບັດສະຖານະສົ່ງ code ມານຳ — ຕັ້ງອັດຕາຂອງມື້ນີ້ໄດ້ເລີຍ ບໍ່ຕ້ອງເລືອກສະກຸນຄືນ */
function openCreate(currency?: string): void {
    clearError();
    editing.value = null;
    rateWarning.value = null;
    referenceRate.value = null;
    Object.assign(form, blankForm, {
        currency_code: currency ?? convertibleCurrencies.value[0]?.code ?? '',
    });
    modalOpen.value = true;
}

function openEdit(rate: ExchangeRate): void {
    clearError();
    editing.value = rate;
    rateWarning.value = null;
    referenceRate.value = null;
    Object.assign(form, {
        currency_code: rate.currency_code,
        effective_date: rate.effective_date,
        // ຕັດສູນທ້າຍອອກ ບໍ່ດັ່ງນັ້ນຊ່ອງຈະເຕັມໄປດ້ວຍ "21760.0000000000"
        rate_lak: rate.rate_lak ? trimTrailingZeros(rate.rate_lak) : '',
        source: rate.source,
    });
    modalOpen.value = true;
}

function closeModal(): void {
    modalOpen.value = false;
    clearError();
}

/** ຖາມ server ວ່າອັດຕາທີ່ໃສ່ສົມເຫດສົມຜົນບໍ — ຕົວກວດອັນດຽວກັນກັບຟອມທຸລະກຳ */
async function checkRate(): Promise<void> {
    rateWarning.value = null;

    if (!form.currency_code || !form.rate_lak || !form.effective_date) {
        return;
    }

    try {
        const { data } = await api.post<{
            ok: boolean;
            message: string | null;
        }>('/exchange-rates/check', {
            currency: form.currency_code,
            rate: form.rate_lak.replace(/[,\s]/g, ''),
            date: form.effective_date,
            // ຂອບເຂດ ແລະ ຂໍ້ຄວາມເປັນກີບ (ເບິ່ງ sanityCheckLakQuote)
            quote: 'lak',
        });

        rateWarning.value = data.ok ? null : data.message;
    } catch {
        // ຕົວກວດລົ້ມ ≠ ອັດຕາຜິດ — ຢ່າຂວາງຟອມ
        rateWarning.value = null;
    }
}

/** ອັດຕາທີ່ໃຊ້ຢູ່ກ່ອນໜ້ານີ້ — ໃຫ້ຄົນປຽບທຽບກ່ອນບັນທຶກ */
async function loadReferenceRate(): Promise<void> {
    referenceRate.value = null;

    if (!form.currency_code || !form.effective_date) {
        return;
    }

    try {
        const { data } = await api.get<RateLookup>('/exchange-rates/lookup', {
            params: { currency: form.currency_code, date: form.effective_date },
        });

        referenceRate.value = data.rate_lak ?? null;
    } catch {
        referenceRate.value = null;
    }
}

watch(
    () => [form.currency_code, form.rate_lak, form.effective_date],
    checkRate,
);

watch(() => [form.currency_code, form.effective_date], loadReferenceRate);

async function submit(): Promise<void> {
    const payload = {
        currency_code: form.currency_code,
        effective_date: form.effective_date,
        rate_lak: form.rate_lak,
        source: form.source,
    };

    if (await save(payload, editing.value)) {
        closeModal();
        // ບັດສະຖານະດ້ານເທິງອີງໃສ່ server — ຕ້ອງຖາມຄືນ ບໍ່ແມ່ນຄິດເອົາເອງ
        await loadDailyStatus();
    }
}
</script>
