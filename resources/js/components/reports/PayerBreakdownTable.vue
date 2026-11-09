<template>
    <div
        class="mb-6 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
    >
        <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
            <h3 class="text-base font-medium text-gray-800 dark:text-white/90">
                {{ t(title) }}
            </h3>
        </div>

        <DataGrid
            :column-defs="columnDefs"
            :row-data="rows"
            :loading="loading"
            :get-row-id="getRowId"
            :empty-label="t('reports.empty')"
        />
    </div>
</template>

<script setup lang="ts">
import type { ColDef } from 'ag-grid-community';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import DataGrid from '@/components/common/DataGrid.vue';
import type { PayerBreakdown } from '@/types/reports';
import { formatMoney } from '@/utils/money';

/**
 * ຕາຕະລາງສະຫຼຸບຕາມຜູ້ຈ່າຍ/ແຫຼ່ງທຶນ — ໃຊ້ຄືກັນທັງລາຍຮັບ ແລະ ລາຍຈ່າຍ.
 *
 * ຄຳວ່າ "ຜູ້ຈ່າຍ" ກັບ "ແຫຼ່ງທຶນ" ຄືຕາຕະລາງ payers ອັນດຽວກັນ ແຕ່ອ່ານຄົນລະຄວາມໝາຍ
 * ຕາມທິດທາງເງິນ ຈຶ່ງໃຫ້ຜູ້ເອີ້ນສົ່ງຫົວຄໍລຳມາເອງ.
 */
const props = withDefaults(
    defineProps<{
        rows: PayerBreakdown[];
        title: string;
        labelHeader: string;
        loading?: boolean;
        /** ລາຍຮັບຂຽວ ລາຍຈ່າຍແດງ — ຄືກັນທຸກບ່ອນໃນລະບົບ */
        tone?: 'revenue' | 'expense';
    }>(),
    { loading: false, tone: 'expense' },
);

const { t } = useI18n();

const toneClass = computed(() =>
    props.tone === 'revenue'
        ? 'text-success-600 dark:text-success-500'
        : 'text-error-600 dark:text-error-500',
);

/** ແຖວ "ບໍ່ໄດ້ລະບຸ" ບໍ່ມີ payer_id — ໃຊ້ຄີຄົງທີ່ແທນ */
const getRowId = (params: { data: PayerBreakdown }) =>
    String(params.data.payer_id ?? 'none');

const columnDefs = computed<ColDef[]>(() => [
    {
        field: 'label',
        headerName: t(props.labelHeader),
        flex: 2,
        minWidth: 180,
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
        colId: 'usd',
        headerName: 'USD',
        width: 150,
        filter: 'agNumberColumnFilter',
        cellClass: `ag-cell-numeric font-medium ${toneClass.value}`,
        headerClass: 'ag-right-aligned-header',
        // ຮຽງດ້ວຍ minor unit ບໍ່ແມ່ນຂໍ້ຄວາມທີ່ຈັດກຸ່ມແລ້ວ
        valueGetter: (params) => params.data?.usd?.minor ?? 0,
        valueFormatter: (params) =>
            formatMoney((params.data as PayerBreakdown | undefined)?.usd),
    },
    {
        colId: 'lak',
        headerName: 'LAK',
        width: 170,
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
        valueGetter: (params) => params.data?.lak?.minor ?? 0,
        valueFormatter: (params) =>
            formatMoney((params.data as PayerBreakdown | undefined)?.lak),
    },
    {
        field: 'percentage',
        headerName: t('reports.share'),
        width: 110,
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
        valueFormatter: (params) => `${params.value}%`,
    },
]);
</script>
