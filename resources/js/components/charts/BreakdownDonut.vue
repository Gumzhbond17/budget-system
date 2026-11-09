<template>
    <div
        class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
    >
        <div class="mb-1 flex items-center justify-between gap-3">
            <h3 class="text-base font-medium text-gray-800 dark:text-white/90">
                {{ t(title) }}
            </h3>
        </div>
        <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
            {{ t(hint) }}
        </p>

        <LoadingState v-if="loading" size="md" />

        <div v-else-if="rows.length === 0" class="empty-state">
            {{ t(empty) }}
        </div>

        <!--
            ວົງຢູ່ເທິງ ຕາຕະລາງຢູ່ລຸ່ມ — ບໍ່ແມ່ນຄຽງຂ້າງກັນ.
            ໜ້າຫຼັກວາງສອງກາດນີ້ຄຽງກັນຢູ່ແລ້ວ (xl:grid-cols-2) ການແບ່ງ
            ຂ້າງໃນອີກເທື່ອໜຶ່ງເຮັດໃຫ້ຕາຕະລາງແຄບຈົນຖັນ "ສັດສ່ວນ" ຖືກຕັດ.
            ຊ້ອນລົງລຸ່ມແທນ ຕາຕະລາງຈຶ່ງໄດ້ຄວາມກວ້າງເຕັມກາດ.
        -->
        <div v-else class="space-y-6">
            <!--
                ບໍ່ມີ `dataLabels` ເທິງແຕ່ລະສ່ວນ — ຕົວເລກເທິງທຸກຈຸດອ່ານບໍ່ໄຫວ.
                ຄ່າທັງໝົດຢູ່ຕາຕະລາງຂ້າງລຸ່ມ ເຊິ່ງເປັນທັງ legend ແລະ "table view"
                ໃນອັນດຽວກັນ — ຈຶ່ງບໍ່ມີຄ່າໃດທີ່ອ່ານໄດ້ຜ່ານ tooltip ຢ່າງດຽວ.
            -->
            <VueApexCharts
                type="donut"
                height="280"
                :options="chartOptions"
                :series="series"
            />

            <!--
                legend + ຕາຕະລາງ ໃນອັນດຽວ. ຈຳເປັນບໍ່ແມ່ນທາງເລືອກ:
                ສີໝວດຈາກຖານຂໍ້ມູນຕົກ contrast ຕ່ຳກວ່າ 3:1 ເທິງພື້ນຂາວຫຼາຍສີ
                ແລະ ຄູ່ ຂຽວ/ແດງ ຫ່າງກັນພຽງ ΔE 7.4 ສຳລັບຕາບອດສີແດງ-ຂຽວ —
                ຊື່ ແລະ ຕົວເລກທີ່ຂຽນໄວ້ຈຶ່ງເປັນຊ່ອງທາງທີ່ສອງທີ່ຂາດບໍ່ໄດ້.
                ຈານສີຂອງແຫຼ່ງທຶນກໍມີ 3 ສີທີ່ຕ່ຳກວ່າ 3:1 ຄືກັນ (palette.ts)

                `role=region` + ຊື່ ແທນ <caption> ເດີມ — grid ບໍ່ແມ່ນ <table>
                ຈຶ່ງບໍ່ມີ caption ໃຫ້ໃສ່ ແຕ່ຍັງຕ້ອງບອກໂປຣແກຣມອ່ານໜ້າຈໍວ່າ
                ຕາຕະລາງນີ້ຄືຂໍ້ມູນຂອງວົງກົມອັນໃດ
            -->
            <div role="region" :aria-label="t(title)">
                <DataGrid
                    :column-defs="columnDefs"
                    :row-data="slices"
                    :get-row-id="getRowId"
                    :col-defaults="legendColDefaults"
                    :empty-label="t(empty)"
                />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import type { ColDef, ICellRendererParams } from 'ag-grid-community';
import type { ApexOptions } from 'apexcharts';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import VueApexCharts from 'vue3-apexcharts';
import DataGrid from '@/components/common/DataGrid.vue';
import TwoLineCell from '@/components/grid/TwoLineCell.vue';
import { useTheme } from '@/components/layout/ThemeProvider.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import type { BreakdownRow } from '@/types/reports';
import { formatMoney } from '@/utils/money';
import { NEUTRAL_COLOR, seriesColor } from './palette';

/**
 * ວົງກົມສະຫຼຸບ + ຕາຕະລາງ — ໃຊ້ຮ່ວມກັນລະຫວ່າງ "ລາຍຈ່າຍຕາມໝວດ" ແລະ
 * "ລາຍຮັບຕາມແຫຼ່ງທຶນ".
 *
 * ບໍ່ຮູ້ຈັກວ່າຂໍ້ມູນມາຈາກໃສ: ຜູ້ເອີ້ນແປງເປັນ `BreakdownRow` ໃຫ້ກ່ອນ. ສອງ
 * ວົງກົມຈຶ່ງມີພຶດຕິກຳ ແລະ ໜ້າຕາຄືກັນຕະຫຼອດ ໂດຍບໍ່ຕ້ອງຂຽນ Apex options ຊ້ຳ.
 */
interface Props {
    rows: BreakdownRow[];
    loading?: boolean;
    /** i18n key — ຫົວຂໍ້ກາດ */
    title: string;
    /** i18n key — ຄຳອະທິບາຍໃຕ້ຫົວຂໍ້ */
    hint: string;
    /** i18n key — ຂໍ້ຄວາມເມື່ອບໍ່ມີຂໍ້ມູນ */
    empty: string;
    /** i18n key — ປ້າຍກາງວົງ (ຍອດລວມ) */
    totalLabel: string;
    /** i18n key — ຫົວຖັນທຳອິດຂອງຕາຕະລາງ */
    labelHeading: string;
}

const props = withDefaults(defineProps<Props>(), { loading: false });

const { t } = useI18n();
const { isDarkMode } = useTheme();

/**
 * ເພດານ 6 ກຸ່ມ + "ອື່ນໆ".
 *
 * ບໍ່ແມ່ນຕົວເລກມົ້ວ: ເກີນ ~7 ສີ ສີທີ່ຢູ່ຕິດກັນຈະແຍກອອກຈາກກັນບໍ່ໄດ້ ໂດຍສະເພາະ
 * ສຳລັບຄົນຕາບອດສີ — ແລະ ລະບົບນີ້ມີ 16 ໝວດ. ສ່ວນທີ່ເຫຼືອບໍ່ໄດ້ຫາຍໄປ: ມັນຖືກ
 * ລວມເປັນ "ອື່ນໆ" ຢູ່ວົງ ແລະ ຍັງນັບຄົບຢູ່ຍອດລວມ.
 */
const MAX_SLICES = 6;

/** seed ຕິດລົບ = ໃຫ້ palette ຄືນສີເທົາສະຫງວນ (ເບິ່ງ palette.ts) */
const OTHER_SEED = -1;

const slices = computed<BreakdownRow[]>(() => {
    if (props.rows.length <= MAX_SLICES + 1) {
        return props.rows;
    }

    const head = props.rows.slice(0, MAX_SLICES);
    const tail = props.rows.slice(MAX_SLICES);

    return [
        ...head,
        {
            label: t('dashboard.otherCategories', { count: tail.length }),
            color: NEUTRAL_COLOR,
            seed: OTHER_SEED,
            count: tail.reduce((sum, r) => sum + r.count, 0),
            usd: {
                minor: tail.reduce((sum, r) => sum + r.usd.minor, 0),
                decimal: '0',
                currency: 'USD',
                decimals: 2,
            },
            percentage: tail.reduce((sum, r) => sum + r.percentage, 0),
        },
    ];
});

/** ສີຈາກຖານຂໍ້ມູນມາກ່ອນ ບໍ່ມີຈຶ່ງຄ່ອຍເອົາຈາກຈານສີ (ຜູກກັບ id ບໍ່ແມ່ນລຳດັບ) */
function colorOf(row: BreakdownRow): string {
    return row.color ?? seriesColor(row.seed, isDarkMode.value);
}

// ---------- ຕາຕະລາງ (legend) ----------

const getRowId = (params: { data: BreakdownRow }) => params.data.label;

/**
 * ບໍ່ຮຽງ ແລະ ບໍ່ກັ່ນຕອງ — ຕາຕະລາງນີ້ຄື legend ຂອງວົງກົມ ບໍ່ແມ່ນຊຸດຂໍ້ມູນເອກະລາດ.
 * ລຳດັບຕ້ອງກົງກັບສ່ວນຂອງວົງ (ໃຫຍ່ໄປນ້ອຍ, "ອື່ນໆ" ລຸ່ມສຸດ) ບໍ່ດັ່ງນັ້ນຄົນຈະ
 * ຈັບຄູ່ສີກັບຊື່ບໍ່ໄດ້ ເຊິ່ງເປັນເຫດຜົນທັງໝົດທີ່ຕາຕະລາງນີ້ມີຢູ່.
 */
const legendColDefaults: ColDef = {
    sortable: false,
    filter: false,
    floatingFilter: false,
};

const columnDefs = computed<ColDef[]>(() => [
    {
        field: 'label',
        headerName: t(props.labelHeading),
        flex: 2,
        minWidth: 160,
        cellRenderer: TwoLineCell,
        cellRendererParams: {
            dotColor: (params: ICellRendererParams<BreakdownRow>) =>
                params.data ? colorOf(params.data) : null,
        },
    },
    {
        colId: 'usd',
        headerName: t('dashboard.amountUsd'),
        width: 140,
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
        valueGetter: (params) => params.data?.usd?.minor ?? 0,
        valueFormatter: (params) =>
            formatMoney((params.data as BreakdownRow | undefined)?.usd),
    },
    {
        field: 'percentage',
        headerName: t('dashboard.share'),
        width: 110,
        cellClass: 'ag-cell-numeric text-gray-500 dark:text-gray-400',
        headerClass: 'ag-right-aligned-header',
        valueFormatter: (params) => `${Number(params.value).toFixed(1)}%`,
    },
]);

/** ApexCharts ຮັບຕົວເລກທຳມະດາ — ໃຊ້ USD major ເພື່ອໃຫ້ tooltip ອ່ານງ່າຍ */
const series = computed(() => slices.value.map((s) => s.usd.minor / 100));

/**
 * ສີພື້ນຂອງກາດ — ໃຊ້ເປັນ "ຊ່ອງຫວ່າງ 2px" ລະຫວ່າງແຕ່ລະສ່ວນ.
 * ຕ້ອງກົງກັບພື້ນຈິງ ບໍ່ດັ່ງນັ້ນຈະກາຍເປັນເສັ້ນຂອບທີ່ເຫັນໄດ້ ແທນທີ່ຈະເປັນຊ່ອງຫວ່າງ.
 * dark mode ບໍ່ແມ່ນການພິກສີອັດຕະໂນມັດ — ຄ່ານີ້ຄື gray-900 ຂອງ theme ຈິງ.
 */
const surface = computed(() => (isDarkMode.value ? '#101828' : '#ffffff'));

const chartOptions = computed<ApexOptions>(() => ({
    chart: {
        type: 'donut',
        fontFamily: 'inherit',
        background: 'transparent',
        animations: { enabled: true },
    },
    labels: slices.value.map((s) => s.label),
    // ສີຜູກກັບກຸ່ມ ບໍ່ແມ່ນຜູກກັບລຳດັບໃນ array — ກັ່ນຕອງແລ້ວກຸ່ມທີ່ຍັງເຫຼືອບໍ່ປ່ຽນສີ
    colors: slices.value.map(colorOf),
    // ຊ່ອງຫວ່າງສີພື້ນ 2px ແທນເສັ້ນຂອບ — ເສັ້ນຂອບຄືໝຶກທີ່ບໍ່ແມ່ນຂໍ້ມູນ
    stroke: { width: 2, colors: [surface.value] },
    dataLabels: { enabled: false },
    // legend ຂອງ Apex ປິດໄວ້: ຕາຕະລາງຂ້າງລຸ່ມເຮັດໜ້າທີ່ນີ້ພ້ອມທັງບອກຄ່າ
    legend: { show: false },
    plotOptions: {
        pie: {
            donut: {
                size: '68%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: t(props.totalLabel),
                        fontSize: '13px',
                        color: isDarkMode.value ? '#98a2b3' : '#667085',
                        formatter: () => usdTotal.value,
                    },
                    value: {
                        fontSize: '22px',
                        fontWeight: 600,
                        color: isDarkMode.value ? '#f2f4f7' : '#1d2939',
                    },
                },
            },
        },
    },
    tooltip: {
        theme: isDarkMode.value ? 'dark' : 'light',
        y: { formatter: (value: number) => `$${value.toLocaleString()}` },
    },
    states: { active: { filter: { type: 'none' } } },
}));

const usdTotal = computed(() => {
    const minor = props.rows.reduce((sum, r) => sum + r.usd.minor, 0);

    return `$${(minor / 100).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
});
</script>
