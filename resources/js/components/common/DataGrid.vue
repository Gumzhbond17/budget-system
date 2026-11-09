<template>
    <div>
        <!--
            ຂໍ້ຜິດພາດຕອນໂຫຼດບໍ່ແມ່ນເລື່ອງຂອງຕາຕະລາງ — ສະແດງເໜືອ grid ແລ້ວປະ
            grid ໄວ້ຄືເກົ່າ. ຖ້າແທນທີ່ grid ຜູ້ໃຊ້ຈະເສຍທັງຕົວກັ່ນຕອງ ແລະ
            ແຖວທີ່ອ່ານຄ້າງຢູ່ ຍ້ອນເນັດສະດຸດເທື່ອດຽວ
        -->
        <Alert
            v-if="error"
            variant="error"
            :title="t('settings.loadFailed')"
            :message="error"
            class="m-5"
        />

        <AgGridVue
            :theme="gridTheme"
            :column-defs="columnDefs"
            :row-data="rowData"
            :default-col-def="mergedDefaultColDef"
            :locale-text="localeText"
            :loading="loading"
            :get-row-id="getRowId"
            :dom-layout="height === undefined ? 'autoHeight' : 'normal'"
            :style="height === undefined ? undefined : { height }"
            :pagination="pagination"
            :pagination-page-size="pageSize"
            :pagination-page-size-selector="PAGE_SIZES"
            :row-height="rowHeight"
            :suppress-cell-focus="true"
            :animate-rows="false"
            v-bind="$attrs"
            @row-data-updated="redrawChangedRows"
        />
    </div>
</template>

<script setup lang="ts">
import type {
    ColDef,
    GetRowIdFunc,
    IRowNode,
    RowDataUpdatedEvent,
} from 'ag-grid-community';
import { AgGridVue } from 'ag-grid-vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Alert from '@/components/ui/Alert.vue';
import { defaultColDef, gridLocaleText, gridTheme } from '@/lib/ag-grid';

/**
 * ຕົວຫຸ້ມ AG Grid ທີ່ທຸກຕາຕະລາງໃນແອັບໃຊ້ຮ່ວມກັນ.
 *
 * ມີຢູ່ເພື່ອບໍ່ໃຫ້ 20 ຕາຕະລາງແຕ່ລະບ່ອນຕັ້ງຮູບແບບ, ຄຳແປ ແລະ ຄ່າເລີ່ມຕົ້ນຂອງ
 * ຖັນເອງ — ຊຶ່ງເປັນວິທີທີ່ຮັບປະກັນວ່າພາຍໃນສາມເດືອນຈະບໍ່ມີສອງໜ້າໃດເໝືອນກັນ.
 *
 * `$attrs` ຖືກສົ່ງຕໍ່ໃຫ້ AgGridVue ໝົດ ດັ່ງນັ້ນ option ຂອງ grid ອັນໃດທີ່ຢູ່
 * ນີ້ບໍ່ໄດ້ຫຸ້ມໄວ້ ກໍຍັງໃສ່ຈາກໜ້າທີ່ໃຊ້ໄດ້ຕາມປົກກະຕິ.
 */
const props = withDefaults(
    defineProps<{
        columnDefs: ColDef[];
        rowData: unknown[];
        loading?: boolean;
        error?: string | null;
        /** ບໍ່ໃສ່ = grid ສູງຕາມຈຳນວນແຖວ (autoHeight) */
        height?: string;
        rowHeight?: number;
        pagination?: boolean;
        pageSize?: number;
        /**
         * ຄີຄົງທີ່ຂອງແຖວ — ໃສ່ເມື່ອຂໍ້ມູນຖືກໂຫຼດຄືນເປັນໄລຍະ (useAutoRefresh)
         * ບໍ່ດັ່ງນັ້ນ grid ຈະສ້າງທຸກແຖວໃໝ່ທຸກຮອບ ແລ້ວຕຳແໜ່ງ scroll ແລະ
         * ແຖວທີ່ເລືອກໄວ້ຈະຫາຍ
         */
        getRowId?: GetRowIdFunc;
        /** ຄ່າເລີ່ມຕົ້ນຂອງຖັນສະເພາະໜ້ານີ້ — ທັບຂອງກາງເປັນລາຍຄີ */
        colDefaults?: ColDef;
        /** ຂໍ້ຄວາມເມື່ອບໍ່ມີແຖວ — ບໍ່ໃສ່ = ໃຊ້ຄຳກາງ "ບໍ່ມີລາຍການ" */
        emptyLabel?: string;
    }>(),
    {
        loading: false,
        error: null,
        height: undefined,
        rowHeight: undefined,
        pagination: false,
        pageSize: 25,
        getRowId: undefined,
        colDefaults: undefined,
        emptyLabel: undefined,
    },
);

/** ຕ້ອງກົງກັບຕົວເລືອກຂອງ Pagination.vue ເດີມ ເພື່ອບໍ່ໃຫ້ຄົນເສຍນິດໄສ */
const PAGE_SIZES = [20, 50, 100, 150];

defineOptions({ inheritAttrs: false });

const { t } = useI18n();

/**
 * ຂໍ້ຄວາມ "ບໍ່ມີລາຍການ" ຕໍ່ໜ້າຜ່ານ localeText ບໍ່ແມ່ນ `overlayNoRowsTemplate`
 * — ອັນຫຼັງຮັບ HTML ດິບ ຈຶ່ງເປັນປະຕູ XSS ໂດຍບໍ່ຈຳເປັນ
 */
const localeText = computed(() => ({
    ...gridLocaleText(t),
    ...(props.emptyLabel === undefined
        ? {}
        : { noRowsToShow: props.emptyLabel }),
}));

const mergedDefaultColDef = computed<ColDef>(() => ({
    ...defaultColDef,
    ...props.colDefaults,
}));

/** ເນື້ອໃນຂອງແຕ່ລະແຖວຮອບກ່ອນ (ຕາມ id) — ໃຊ້ຫາວ່າແຖວໃດປ່ຽນແທ້ */
let snapshot = new Map<string, string>();

/**
 * ແຕ້ມແຖວທີ່ຂໍ້ມູນປ່ຽນຄືນໃໝ່ທັງແຖວ.
 *
 * ເມື່ອມີ `getRowId` AG Grid ອັບເດດແຖວເດີມ ແລ້ວແຕ້ມຄືນ**ສະເພາະຊ່ອງທີ່ "ຄ່າ"
 * ປ່ຽນ** — ຄ່າ = `field` ຫຼື `valueGetter`. ແຕ່ຊ່ອງໃນແອັບນີ້ສ່ວນຫຼາຍໃຊ້ cell
 * renderer ທີ່ອ່ານ `params.data` ໄປສະແດງຫຼາຍກວ່າຄ່ານັ້ນ: ເຄີຍເກີດ — ຂະຫຍາຍ
 * ອາຍຸຜູ້ໃຊ້ແລ້ວປ້າຍຍັງ "ເຫຼືອ 52 ວັນ" ເພາະຄ່າຂອງຖັນຄື plan (`trial`) ທີ່ບໍ່ປ່ຽນ.
 * ອີເມວໃຕ້ຊື່, ປ້າຍ, ປຸ່ມຕາມ `can` ກໍຕົກບັນຫາດຽວກັນ.
 *
 * ແຕ້ມຄືນສະເພາະແຖວທີ່ເນື້ອໃນຕ່າງຈາກຮອບກ່ອນ — ຮອບ 30 ວິນາທີທີ່ບໍ່ມີຫຍັງປ່ຽນ
 * ຈຶ່ງບໍ່ແຕະຫຍັງເລີຍ (ບໍ່ກະພິບ, hover/focus ບໍ່ຫາຍ).
 */
function redrawChangedRows(event: RowDataUpdatedEvent): void {
    const next = new Map<string, string>();
    const changed: IRowNode[] = [];

    event.api.forEachNode((node) => {
        if (node.id === undefined) {
            return;
        }

        const content = JSON.stringify(node.data);
        const before = snapshot.get(node.id);

        next.set(node.id, content);

        if (before !== undefined && before !== content) {
            changed.push(node);
        }
    });

    snapshot = next;

    if (changed.length > 0) {
        event.api.refreshCells({ rowNodes: changed, force: true });
    }
}
</script>

<style>
/*
 * ບໍ່ແມ່ນ `scoped`: AG Grid render cell ຢູ່ນອກ tree ຂອງ component ນີ້
 * (popup ຂອງຕົວກັ່ນຕອງ, ແຖວທີ່ virtualise) ຈຶ່ງບໍ່ໄດ້ຮັບ attribute ຂອງ scope.
 */
@reference '../../../css/app.css';

/*
 * ເນື້ອໃນທຸກຊ່ອງຢູ່ກາງແນວຕັ້ງ.
 *
 * AG Grid ຈັດກາງດ້ວຍການຕັ້ງ `line-height` ໃຫ້ເທົ່າກັບຄວາມສູງແຖວ ເຊິ່ງໄດ້ຜົນ
 * ສະເພາະຂໍ້ຄວາມແຖວດຽວລ້ວນໆ. ຊ່ອງທີ່ໃຊ້ cell renderer (ເກືອບທຸກຊ່ອງໃນແອັບນີ້)
 * ຄືນ <div> ອອກມາ ເຊິ່ງ `line-height` ບໍ່ມີຜົນ — ເນື້ອໃນຈຶ່ງໄປຕິດຂອບເທິງ
 * ແລ້ວປະຊ່ອງຫວ່າງໄວ້ຂ້າງລຸ່ມ. ປ່ຽນຊ່ອງເປັນ flex ຈຶ່ງຈັດກາງໄດ້ທຸກກໍລະນີ.
 */
.ag-cell {
    display: flex;
    align-items: center;
}

/*
 * `.ag-cell-value` ເປັນ `flex: 1 1 auto` ຢູ່ແລ້ວ ແຕ່ flex item ບໍ່ຫົດຕ່ຳກວ່າ
 * ຄວາມກວ້າງເນື້ອໃນຂອງມັນ ຖ້າບໍ່ໃສ່ `min-width: 0` — ຂໍ້ຄວາມຍາວຈະດັນຊ່ອງລົ້ນ
 * ແທນທີ່ຈະຖືກຕັດດ້ວຍ …
 */
.ag-cell-value {
    min-width: 0;
}

/* ຖັນປຸ່ມຈັດການ — ປຸ່ມຈັດຊິດຂວາຄືກັບຕາຕະລາງເດີມ */
.ag-cell-actions {
    @apply justify-end gap-1;
}

/*
 * ຕົວເລກເງິນຕ້ອງຊ້ອນຫຼັກກັນຕົງ ບໍ່ວ່າຈະຢູ່ຖັນໃດ.
 *
 * ຕ້ອງມີທັງ `justify-end` ແລະ `text-right`: ຊ່ອງທີ່ມີ renderer ຖືກດັນໄປຂວາ
 * ດ້ວຍອັນທຳອິດ ສ່ວນຊ່ອງຂໍ້ຄວາມທຳມະດາ (`valueFormatter` ລ້ວນໆ) ຢູ່ໃນ span ທີ່
 * ຍືດເຕັມຊ່ອງແລ້ວ ຈຶ່ງຕ້ອງອາໄສອັນທີສອງ.
 */
.ag-cell-numeric {
    @apply justify-end text-right tabular-nums;
}

/*
 * ຊ່ອງທີ່ດັບເບີນຄລິກແກ້ໄດ້ (cellClassRules `ag-cell-editable`) — ຂອບປະເມື່ອ
 * ຊີ້ເມົ້າ ໃຫ້ຮູ້ວ່າຊ່ອງນີ້ແກ້ໄດ້ ໂດຍບໍ່ຕ້ອງເດົາ. ແຖວທີ່ບໍ່ມີສິດແກ້ບໍ່ໄດ້ class ນີ້.
 */
.ag-cell-editable {
    @apply cursor-text;
}

.ag-cell-editable:not(.ag-cell-inline-editing):hover {
    @apply outline-1 -outline-offset-4 outline-gray-300 outline-dashed dark:outline-gray-600;
}

/* AG Grid ມີ `ag-right-aligned-header` ມາໃຫ້ ແຕ່ບໍ່ມີແບບຈັດກາງ */
.ag-center-aligned-header .ag-header-cell-label {
    @apply justify-center;
}

/* ຊ່ອງທີ່ຮັບ `type: 'rightAligned'` ຂອງ grid ເອງ — flex ບໍ່ຟັງ `text-align` */
.ag-right-aligned-cell {
    @apply justify-end;
}
</style>
