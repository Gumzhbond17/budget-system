<template>
    <Badge v-if="label !== ''" size="sm" :color="color">{{ label }}</Badge>
    <span v-else>—</span>
</template>

<script setup lang="ts">
import type { ICellRendererParams } from 'ag-grid-community';
import { computed } from 'vue';
import Badge from '@/components/ui/Badge.vue';

type BadgeColor =
    'primary' | 'success' | 'error' | 'warning' | 'info' | 'light' | 'dark';

/**
 * ຊ່ອງທີ່ສະແດງເປັນປ້າຍສີ (ສະຖານະ, ປະເພດ, ເປີດ/ປິດໃຊ້ງານ).
 *
 * ຂໍ້ຄວາມມາຈາກ `valueFormatter` ຂອງຖັນຕາມປົກກະຕິ ສ່ວນສີສົ່ງເປັນ function
 * ຜ່ານ `cellRendererParams.color` — ຖັນຈຶ່ງຍັງຮຽງ ແລະ ກັ່ນຕອງດ້ວຍຄ່າດິບ
 * ຢູ່ ບໍ່ແມ່ນດ້ວຍຄຳທີ່ແປແລ້ວ.
 */
interface BadgeCellParams extends ICellRendererParams {
    color?: BadgeColor | ((params: ICellRendererParams) => BadgeColor);
}

const props = defineProps<{ params: BadgeCellParams }>();

const label = computed(() => {
    const { valueFormatted, value } = props.params;

    return String(valueFormatted ?? value ?? '');
});

const color = computed<BadgeColor>(() => {
    const { color: given } = props.params;

    if (typeof given === 'function') {
        return given(props.params);
    }

    return given ?? 'light';
});
</script>
