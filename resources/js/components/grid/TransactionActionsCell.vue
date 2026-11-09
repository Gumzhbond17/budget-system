<template>
    <!--
        ຕ້ອງມີ root element ດຽວ: ag-grid-vue3 ເອົາສະເພາະ `firstElementChild`
        ຂອງ component ໄປໃສ່ຊ່ອງ (VueComponentFactory) — ສ່ວນທີ່ເຫຼືອຂອງ
        fragment ຖືກປະຖິ້ມງຽບໆ. ເຄີຍເກີດ: ແຖວຮ່າງເຫັນແຕ່ "ສົ່ງອະນຸມັດ",
        ປຸ່ມ "ແກ້ໄຂ" ແລະ ຮູບຕາຫາຍໝົດ ຈົນແກ້ລາຍການກ່ອນສົ່ງບໍ່ໄດ້.
    -->
    <div v-if="entry" class="flex items-center justify-end gap-1">
        <!-- ປຸ່ມຂັ້ນຕອນອະນຸມັດຢູ່ຊ້າຍສຸດ: ສຳລັບຜູ້ອະນຸມັດ ນີ້ຄືເຫດຜົນທີ່ເປີດໜ້ານີ້ -->
        <WorkflowActions
            :entry="entry"
            :busy="params.busy?.(entry) ?? false"
            @submit="params.onSubmit"
            @approve="params.onApprove"
            @reject="params.onReject"
            @reopen="params.onReopen?.($event)"
        />

        <!-- ເສັ້ນແບ່ງ: ປຸ່ມຂັ້ນຕອນອະນຸມັດ | ປຸ່ມໄອຄອນ — ສອງກຸ່ມນີ້ຄົນລະປະເພດ -->
        <span
            v-if="hasWorkflowActions"
            class="mx-1.5 h-5 w-px shrink-0 bg-gray-200 dark:bg-gray-700"
            aria-hidden="true"
        />

        <button
            type="button"
            class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-brand-500 dark:text-gray-400 dark:hover:bg-white/5"
            :title="t('tx.viewDetail')"
            :aria-label="t('tx.viewDetail')"
            @click="params.onDetail(entry)"
        >
            <EyeIcon class="h-5 w-5" />
        </button>

        <!--
            ແກ້ໄຂ / ລຶບ ຂຶ້ນຕາມ `entry.can` ຈາກ Policy ເທົ່ານັ້ນ — ແຖວທີ່ອະນຸມັດແລ້ວ
            (ລັອກ) ຈະເຫຼືອແຕ່ຮູບຕາ ເຊິ່ງເປີດລາຍລະອຽດ + ຫຼັກຖານໄດ້ຄົບຢູ່ແລ້ວ
        -->
        <button
            v-if="entry.can.update"
            type="button"
            class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-brand-500 dark:text-gray-400 dark:hover:bg-white/5"
            :title="t('common.edit')"
            :aria-label="t('common.edit')"
            @click="params.onEdit(entry)"
        >
            <PencilIcon class="h-5 w-5" />
        </button>

        <button
            v-if="entry.can.delete && params.onDelete"
            type="button"
            class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 transition hover:bg-error-50 hover:text-error-500 dark:text-gray-400 dark:hover:bg-error-500/10"
            :title="t('common.delete')"
            :aria-label="t('common.delete')"
            @click="params.onDelete(entry)"
        >
            <TrashIcon class="h-5 w-5" />
        </button>
    </div>
</template>

<script setup lang="ts">
import type { ICellRendererParams } from 'ag-grid-community';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import WorkflowActions from '@/components/transactions/WorkflowActions.vue';
import type { WorkflowEntry } from '@/composables/useTransactionWorkflow';
import EyeIcon from '~icons/tabler/eye';
import PencilIcon from '~icons/tabler/pencil';
import TrashIcon from '~icons/tabler/trash';

/**
 * ຖັນປຸ່ມຂອງຕາຕະລາງທຸລະກຳ (ລາຍຮັບ ແລະ ລາຍຈ່າຍໃຊ້ຮ່ວມກັນ).
 *
 * ຊຸດປຸ່ມນີ້ຊັບຊ້ອນກວ່າ `ActionsCell` ທົ່ວໄປ: ມີທັງຂັ້ນຕອນອະນຸມັດ (ທີ່ຕົວມັນ
 * ເອງເຊື່ອງ/ສະແດງຕາມສິດຂອງແຖວ), ປຸ່ມເບິ່ງລາຍລະອຽດ ແລະ ປຸ່ມແກ້ໄຂທີ່ປ່ຽນ
 * ຂໍ້ຄວາມເປັນ "ເບິ່ງ" ເມື່ອແຖວຖືກລັອກແລ້ວ.
 */
interface TransactionActionsParams<
    T extends WorkflowEntry,
> extends ICellRendererParams<T> {
    busy?: (entry: T) => boolean;
    onSubmit: (entry: T) => void;
    onApprove: (entry: T) => void;
    onReject: (entry: T) => void;
    /** ຍົກເລີກການອະນຸມັດ (ຜູ້ດູແລລະບົບ) — ປຸ່ມຂຶ້ນຕາມ `entry.can.reopen` */
    onReopen?: (entry: T) => void;
    onDetail: (entry: T) => void;
    onEdit: (entry: T) => void;
    /** ບໍ່ສົ່ງມາ = ໜ້ານັ້ນບໍ່ມີການລຶບ → ບໍ່ສະແດງປຸ່ມ */
    onDelete?: (entry: T) => void;
}

const props = defineProps<{
    params: TransactionActionsParams<WorkflowEntry>;
}>();

const { t } = useI18n();

const entry = computed(() => props.params.data ?? null);

const hasWorkflowActions = computed(() => {
    const can = entry.value?.can;

    return !!can && (can.submit || can.approve || can.reject || can.reopen);
});
</script>
