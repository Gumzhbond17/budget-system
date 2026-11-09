<template>
    <!--
        ພື້ນໃສ ບໍ່ມີແຜ່ນຂາວ ບໍ່ມີຂອບ — ໂລໂກ້ສະແດງຕາມທີ່ມັນເປັນ.

        `overflow-hidden rounded-full` ຍັງມີຢູ່: ໂລໂກ້ທີ່ມີພື້ນທຶບຂອງຕົນເອງ
        (ເຊັ່ນ IB ທີ່ເປັນສີ່ຫຼ່ຽມສີມ່ວງ) ຈຶ່ງຖືກຕັດເປັນວົງມົນ ສ່ວນອັນທີ່ພື້ນໃສ
        ຢູ່ແລ້ວກໍລອຍຢູ່ເສີຍໆ. ບໍ່ມີ padding ເພື່ອໃຫ້ຮູບເຕັມວົງ — ມີ padding
        ແລ້ວຮູບຈະນ້ອຍກວ່າວົງ ແລະ ການຕັດເປັນວົງມົນຈະບໍ່ມີຜົນຫຍັງເລີຍ.

        ⚠️ ໂລໂກ້ທີ່ເປັນຕົວອັກສອນສີເຂັ້ມພື້ນໃສ (BIC, LVB, STB) ຈະອ່ານຍາກຢູ່
        dark mode ເພາະບໍ່ມີພື້ນຂາວຮອງແລ້ວ — ນີ້ຄືການແລກປ່ຽນທີ່ຮູ້ຕົວ.
        ທາງແກ້ຄືອັບໄຟລ໌ສະບັບທີ່ມີພື້ນຂາວຂອງຕົນເອງມາແທນ.
    -->
    <span
        class="flex shrink-0 items-center justify-center overflow-hidden rounded-full"
        :class="[
            sizeClass,
            // ພື້ນຈາງໆສະເພາະຄ່າສຳຮອງ (ຕົວອັກສອນ) — ບໍ່ມີພື້ນແລ້ວລະຫັດຈະ
            // ອ່ານບໍ່ອອກ. ອັນທີ່ມີຮູບແທ້ບໍ່ມີພື້ນເລີຍ
            bank.logo_url ? '' : 'bg-gray-100 dark:bg-white/10',
        ]"
    >
        <img
            v-if="bank.logo_url"
            :src="bank.logo_url"
            :alt="bank.name_lo"
            loading="lazy"
            class="h-full w-full object-contain"
        />
        <!-- ບໍ່ມີຮູບ → ລະຫັດແທນ (ຄື avatar ຫຍໍ້ຊື່).
             aria-hidden ເພາະຊື່/ລະຫັດທະນາຄານຢູ່ຂ້າງໆແລ້ວທຸກບ່ອນທີ່ໃຊ້ —
             ບໍ່ດັ່ງນັ້ນ screen reader ຈະອ່ານ "BCEL BCEL" -->
        <span
            v-else
            aria-hidden="true"
            class="truncate px-0.5 font-semibold text-gray-400 dark:text-gray-500"
            :class="labelClass"
        >
            {{ bank.code.slice(0, 4) }}
        </span>
    </span>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { Bank } from '@/types/settings';

interface Props {
    bank: Bank;
    /** sm = ໃນລາຍການເລືອກ, md = ໃນຕາຕະລາງ, lg = ຕົວຢ່າງໃນຟອມ */
    size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), { size: 'md' });

const sizeClass = computed(
    () => ({ sm: 'h-8 w-8', md: 'h-11 w-11', lg: 'h-20 w-20' })[props.size],
);

const labelClass = computed(
    () =>
        ({ sm: 'text-[10px]', md: 'text-theme-xs', lg: 'text-sm' })[props.size],
);
</script>
