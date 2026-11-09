<template>
    <span class="flex items-center gap-2">
        <span class="font-mono font-medium text-gray-800 dark:text-white/90">
            {{ code }}
        </span>
        <Badge v-if="badge" size="sm" :color="badge.color">
            {{ badge.label }}
        </Badge>
    </span>
</template>

<script setup lang="ts">
import type { ICellRendererParams } from 'ag-grid-community';
import { computed } from 'vue';
import Badge from '@/components/ui/Badge.vue';

type BadgeColor =
    'primary' | 'success' | 'error' | 'warning' | 'info' | 'light' | 'dark';

/**
 * ລະຫັດ/ເລກທີ່ ເປັນຕົວພິມດຽວ ພ້ອມປ້າຍກຳກັບຕໍ່ທ້າຍ (ທາງເລືອກ).
 *
 * ຕົວພິມດຽວບໍ່ແມ່ນເລື່ອງຄວາມງາມ: ລະຫັດເຊັ່ນ REV-2026-0018 ຫຼື LAK ຖືກອ່ານ
 * ແບບປຽບທຽບເປັນຖັນລົງມາ ຕົວອັກສອນຈຶ່ງຕ້ອງກວ້າງເທົ່າກັນ.
 */
interface CodeCellParams extends ICellRendererParams {
    badge?: (
        params: ICellRendererParams,
    ) => { label: string; color: BadgeColor } | null;
}

const props = defineProps<{ params: CodeCellParams }>();

const code = computed(() => {
    const { valueFormatted, value } = props.params;

    return String(valueFormatted ?? value ?? '—');
});

const badge = computed(() => props.params.badge?.(props.params) ?? null);
</script>
