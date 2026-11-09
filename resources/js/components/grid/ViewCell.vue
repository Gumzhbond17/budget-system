<template>
    <!--
        root ດຽວ — ag-grid-vue3 ເອົາສະເພາະ firstElementChild ໄປໃສ່ຊ່ອງ
        (ເບິ່ງ TransactionActionsCell)
    -->
    <button
        v-if="params.data"
        type="button"
        class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-brand-500 dark:text-gray-400 dark:hover:bg-white/5"
        :title="t('tx.viewDetail')"
        :aria-label="t('tx.viewDetail')"
        @click="params.onView(params.data)"
    >
        <EyeIcon class="h-5 w-5" />
    </button>
</template>

<script setup lang="ts">
import type { ICellRendererParams } from 'ag-grid-community';
import { useI18n } from 'vue-i18n';
import EyeIcon from '~icons/tabler/eye';

/** ປຸ່ມຮູບຕາປຸ່ມດຽວ — ເປີດລາຍລະອຽດຂອງແຖວ */
interface ViewCellParams extends ICellRendererParams {
    onView: (row: unknown) => void;
}

defineProps<{ params: ViewCellParams }>();

const { t } = useI18n();
</script>
