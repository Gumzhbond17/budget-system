<template>
    <div
        class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
    >
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ label }}</p>
            <span
                class="flex h-9 w-9 items-center justify-center rounded-lg"
                :class="
                    alarming
                        ? 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500'
                        : 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300'
                "
            >
                <component :is="icon" class="h-5 w-5" />
            </span>
        </div>

        <p
            class="mt-3 text-2xl font-semibold tabular-nums"
            :class="
                alarming
                    ? 'text-error-600 dark:text-error-500'
                    : 'text-gray-800 dark:text-white/90'
            "
        >
            {{ value }}
        </p>

        <p
            v-if="caption"
            class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400"
        >
            {{ caption }}
        </p>
    </div>
</template>

<script setup lang="ts">
/**
 * ກ່ອງຕົວເລກທີ່ **ບໍ່ແມ່ນ** ຈຳນວນເງິນ (ຈຳນວນຜູ້ຈ່າຍ, ຈຳນວນລາຍການທີ່ຂາດຫຼັກຖານ).
 *
 * ແຍກຈາກ SummaryCard ເພາະອັນນັ້ນຮັບແຕ່ Money ແລະ format ຜ່ານ formatMoney ສະເໝີ —
 * ຖ້າຍັດຕົວນັບເຂົ້າໄປຈະໄດ້ "5.00" ແທນ "5". ໜ້າຕາຈົງໃຈໃຫ້ຄືກັນ.
 */
defineProps<{
    label: string;
    value: number | string;
    icon: object;
    caption?: string;
    /** ເປັນສີແດງເມື່ອຕົວເລກນີ້ຄືບັນຫາ (ເຊັ່ນ ຍັງມີລາຍການທີ່ບໍ່ມີຫຼັກຖານ) */
    alarming?: boolean;
}>();
</script>
