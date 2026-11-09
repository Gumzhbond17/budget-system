<template>
    <!-- `min-w-0` ຢູ່ທັງສອງຊັ້ນ: ຊ່ອງເປັນ flex ແລ້ວ (ເບິ່ງ DataGrid.vue) ແລະ
         flex item ບໍ່ຫົດຕ່ຳກວ່າຄວາມກວ້າງເນື້ອໃນຂອງມັນຖ້າບໍ່ໃສ່ — ຊື່ຍາວຈະ
         ດັນລົ້ນອອກໄປທັບຖັນຖັດໄປ ແທນທີ່ຈະຖືກຕັດດ້ວຍ … -->
    <div
        class="flex min-w-0 items-center gap-2 leading-tight"
        :style="
            depth > 0 ? { paddingInlineStart: `${depth * 20}px` } : undefined
        "
    >
        <!-- ຍໍ້ໜ້າຕາມຊັ້ນ = ເຫັນໂຄງສ້າງແມ່-ລູກທັນທີ ໂດຍບໍ່ຕ້ອງໃຊ້ tree ຂອງ
             AG Grid ເຊິ່ງເປັນຄວາມສາມາດຝັ່ງ Enterprise -->
        <span v-if="depth > 0" class="text-gray-300 dark:text-gray-600">└</span>

        <!-- ຈຸດສີຂອງໝວດ — ສີມາຈາກ DB ບໍ່ແມ່ນ hardcode ຢູ່ໜ້າຈໍ ຈຶ່ງຜູກເປັນ
             style ບໍ່ແມ່ນ class (docs/architecture.md `expense_categories.color`) -->
        <span
            v-if="dotColor"
            class="h-3 w-3 shrink-0 rounded-full"
            :style="{ backgroundColor: dotColor }"
            aria-hidden="true"
        />

        <div class="min-w-0">
            <p
                class="flex items-center gap-2 font-medium text-gray-800 dark:text-white/90"
            >
                <!-- `title` ເພື່ອໃຫ້ຊື່ທີ່ຖືກຕັດຍັງອ່ານເຕັມໄດ້ດ້ວຍການຊີ້ເມົ້າ -->
                <span class="truncate" :title="primary">{{ primary }}</span>
                <!-- ປ້າຍບໍ່ຫົດ — ຖ້າຫົດ ຄຳວ່າ "ຜູ້ດູແລລະບົບ" ຈະບີບຈົນອ່ານບໍ່ອອກ
                     ກ່ອນທີ່ຊື່ຈະເລີ່ມຖືກຕັດ -->
                <Badge
                    v-if="badge"
                    size="sm"
                    :color="badge.color"
                    class="shrink-0"
                >
                    {{ badge.label }}
                </Badge>
            </p>
            <p
                v-if="secondary"
                class="truncate text-theme-xs text-gray-500 dark:text-gray-400"
                :title="secondary"
            >
                {{ secondary }}
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import type { ICellRendererParams } from 'ag-grid-community';
import { computed } from 'vue';
import Badge from '@/components/ui/Badge.vue';

type BadgeColor =
    'primary' | 'success' | 'error' | 'warning' | 'info' | 'light' | 'dark';

/**
 * ຊ່ອງສອງແຖວ: ຊື່ຫຼັກຢູ່ເທິງ ລາຍລະອຽດຮອງຢູ່ລຸ່ມ.
 *
 * ຮູບແບບນີ້ຊ້ຳຢູ່ເກືອບທຸກຕາຕະລາງ (ຊື່ + ໝາຍເຫດ, ຊື່ລາຍການ + ຜູ້ຈ່າຍ,
 * ຊື່ທະນາຄານ + ຊື່ຫຍໍ້) ຈຶ່ງແຍກອອກມາແທນທີ່ຈະຂຽນ renderer ຊ້ຳທຸກໜ້າ.
 *
 * ແຖວທີ່ໃຊ້ຊ່ອງນີ້ຕ້ອງຕັ້ງ `rowHeight` ໃຫ້ພໍ (ປະມານ 56–64) ບໍ່ດັ່ງນັ້ນ
 * ແຖວທີສອງຈະຖືກຕັດ.
 */
interface TwoLineCellParams extends ICellRendererParams {
    /** ຂໍ້ຄວາມແຖວລຸ່ມ — ຄືນ '' ຫຼື null ເມື່ອແຖວນັ້ນບໍ່ມີ */
    secondary?: (params: ICellRendererParams) => string | null;
    /** ຊັ້ນຄວາມເລິກສຳລັບຂໍ້ມູນແບບແມ່-ລູກ (0 = ຊັ້ນເທິງສຸດ) */
    depth?: (params: ICellRendererParams) => number;
    /** ຈຸດສີນຳໜ້າຊື່ (ໝວດລາຍຈ່າຍ) — ຄືນ null = ບໍ່ມີຈຸດ */
    dotColor?: (params: ICellRendererParams) => string | null;
    /** ປ້າຍກຳກັບຕໍ່ທ້າຍຊື່ (ເຊັ່ນ "ຜູ້ດູແລລະບົບ") */
    badge?: (
        params: ICellRendererParams,
    ) => { label: string; color: BadgeColor } | null;
}

const props = defineProps<{ params: TwoLineCellParams }>();

const depth = computed(() => props.params.depth?.(props.params) ?? 0);
const dotColor = computed(() => props.params.dotColor?.(props.params) ?? null);
const badge = computed(() => props.params.badge?.(props.params) ?? null);

const primary = computed(() => {
    const { valueFormatted, value } = props.params;

    return String(valueFormatted ?? value ?? '');
});

const secondary = computed(() => props.params.secondary?.(props.params) ?? '');
</script>
