<template>
    <!--
        ໃຊ້ `currentColor` ທັງໝົດ — ສີມາຈາກ parent (`text-brand-500`,
        `text-white` ໃນປຸ່ມ) ຈຶ່ງບໍ່ຕ້ອງມີ prop ສີ ແລະ ບໍ່ຫຼົງໃນ dark mode.

        `motion-reduce:animate-none`: ຜູ້ໃຊ້ທີ່ຕັ້ງລະບົບວ່າ "ຫຼຸດການເຄື່ອນໄຫວ"
        ມັກມີອາການວິນຫົວຈາກພາບໝູນ. ຂໍ້ຄວາມຂ້າງໆບອກສະຖານະຢູ່ແລ້ວ ການຢຸດໝູນ
        ຈຶ່ງບໍ່ໄດ້ເຮັດໃຫ້ຂໍ້ມູນຫາຍໄປ.
    -->
    <svg
        :class="['animate-spin motion-reduce:animate-none', sizeClass]"
        viewBox="0 0 24 24"
        fill="none"
        aria-hidden="true"
    >
        <circle
            class="opacity-25"
            cx="12"
            cy="12"
            r="10"
            stroke="currentColor"
            stroke-width="4"
        />
        <path
            class="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
        />
    </svg>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    /** sm = ໃນປຸ່ມ, md = ໃນແຖວ/ໜ້າຕ່າງນ້ອຍ, lg = ກາງພື້ນທີ່ຕາຕະລາງ */
    size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), { size: 'md' });

const sizeClass = computed(
    () =>
        ({
            sm: 'h-4 w-4',
            md: 'h-6 w-6',
            lg: 'h-8 w-8',
        })[props.size],
);
</script>
