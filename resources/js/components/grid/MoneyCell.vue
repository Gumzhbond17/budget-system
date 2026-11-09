<template>
    <span :class="toneClass">{{ text }}</span>
</template>

<script setup lang="ts">
import type { ICellRendererParams } from 'ag-grid-community';
import { computed } from 'vue';
import type { Money } from '@/types/settings';
import { formatMoneyWithCurrency } from '@/utils/money';

/**
 * ຈຳນວນເງິນພ້ອມສະກຸນ ແລະ ເຄື່ອງໝາຍທິດທາງ.
 *
 * ສີເປັນຂໍ້ຕົກລົງທັງລະບົບ ບໍ່ແມ່ນການຕົກແຕ່ງ: ລາຍຮັບຂຽວ (+) ລາຍຈ່າຍແດງ (−)
 * ຢູ່ທຸກໜ້າ ຄົນຈຶ່ງອ່ານແຖວປົນກັນຢູ່ບັນຊີແຍກປະເພດໄດ້ໂດຍບໍ່ຕ້ອງອ່ານຫົວຖັນ.
 */
interface MoneyCellParams extends ICellRendererParams {
    /** ຈຳນວນເງິນຂອງແຖວ — ບໍ່ດຶງຈາກ `value` ເພາະນັ້ນເປັນຕົວເລກສຳລັບຮຽງ */
    money: (params: ICellRendererParams) => Money | null | undefined;
    tone?: 'revenue' | 'expense' | 'neutral';
}

const props = defineProps<{ params: MoneyCellParams }>();

const tone = computed(() => props.params.tone ?? 'neutral');

const text = computed(() => {
    const formatted = formatMoneyWithCurrency(props.params.money(props.params));

    if (formatted === '—' || tone.value === 'neutral') {
        return formatted;
    }

    return `${tone.value === 'revenue' ? '+' : '−'}${formatted}`;
});

const toneClass = computed(() => {
    if (tone.value === 'revenue') {
        return 'font-medium text-success-600 dark:text-success-500';
    }

    if (tone.value === 'expense') {
        return 'font-medium text-error-600 dark:text-error-500';
    }

    return 'text-gray-700 dark:text-gray-300';
});
</script>
