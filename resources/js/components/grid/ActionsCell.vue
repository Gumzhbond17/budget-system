<template>
    <!--
        root ດຽວ: ag-grid-vue3 ເອົາສະເພາະ `firstElementChild` ໄປໃສ່ຊ່ອງ.
        ເມື່ອກ່ອນ `v-for` ຢູ່ root ໂດຍກົງ → ເຫັນແຕ່ປຸ່ມທຳອິດ ("ແກ້ໄຂ")
        ສ່ວນ "ລຶບ" ຫາຍໄປງຽບໆ ທຸກຕາຕະລາງ (ເບິ່ງ TransactionActionsCell)
    -->
    <div class="flex items-center justify-end gap-1">
        <button
            v-for="action in actions"
            :key="action.label"
            type="button"
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg transition focus:outline-hidden focus-visible:ring-3 disabled:cursor-not-allowed disabled:text-gray-300 disabled:hover:bg-transparent dark:disabled:text-gray-600"
            :class="
                action.danger
                    ? 'text-gray-500 hover:bg-error-50 hover:text-error-500 focus-visible:ring-error-500/20 dark:text-gray-400 dark:hover:bg-error-500/10'
                    : 'text-gray-500 hover:bg-gray-100 hover:text-brand-500 focus-visible:ring-brand-500/20 dark:text-gray-400 dark:hover:bg-white/5'
            "
            :disabled="action.disabled"
            :title="action.title ?? action.label"
            :aria-label="action.label"
            @click="action.onClick"
        >
            <component :is="iconFor(action)" class="h-5 w-5" />
        </button>
    </div>
</template>

<script setup lang="ts">
import type { ICellRendererParams } from 'ag-grid-community';
import { computed } from 'vue';
import type { Component } from 'vue';
import PencilIcon from '~icons/tabler/pencil';
import TrashIcon from '~icons/tabler/trash';

export interface RowAction {
    /** ບໍ່ສະແດງເປັນຂໍ້ຄວາມແລ້ວ — ເປັນ tooltip ແລະ aria-label ຂອງປຸ່ມໄອຄອນ */
    label: string;
    onClick: () => void;
    /** ບໍ່ໃສ່ = ດິນສໍ (ແກ້ໄຂ) ຫຼື ຖັງຂີ້ເຫຍື້ອ ຖ້າ `danger` */
    icon?: Component;
    disabled?: boolean;
    /** ຂໍ້ຄວາມເມື່ອເອົາເມົ້າໄປໃສ່ — ໃຊ້ບອກ **ເຫດຜົນ** ທີ່ປຸ່ມຖືກປິດ */
    title?: string;
    danger?: boolean;
}

/**
 * ຖັນປຸ່ມຈັດການ (ແກ້ໄຂ / ລຶບ / ...) ເປັນປຸ່ມໄອຄອນ — ໜ້າຕາດຽວກັບ
 * TransactionActionsCell ຂອງໜ້າລາຍຮັບ/ລາຍຈ່າຍ.
 *
 * ແຕ່ລະໜ້າສົ່ງ function `actions(row)` ມາ ບໍ່ແມ່ນສົ່ງລາຍການປຸ່ມຄົງທີ່ —
 * ປຸ່ມສ່ວນຫຼາຍຂຶ້ນກັບແຖວ (ລຶບບໍ່ໄດ້ເມື່ອຖືກອ້າງອີງຢູ່, ແກ້ບໍ່ໄດ້ເມື່ອລັອກແລ້ວ).
 */
interface ActionsCellParams extends ICellRendererParams {
    actions: (params: ICellRendererParams) => RowAction[];
}

const props = defineProps<{ params: ActionsCellParams }>();

const actions = computed(() => props.params.actions(props.params));

function iconFor(action: RowAction): Component {
    return action.icon ?? (action.danger ? TrashIcon : PencilIcon);
}
</script>
