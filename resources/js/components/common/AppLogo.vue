<template>
    <span
        class="inline-flex shrink-0 items-center justify-center rounded-[28%] bg-linear-to-br from-brand-400 via-brand-500 to-brand-700 text-white shadow-theme-xs ring-1 ring-white/20 ring-inset"
        :style="{ width: `${size}px`, height: `${size}px` }"
        aria-hidden="true"
    >
        <IconRevenueExpense
            :style="{ width: `${iconSize}px`, height: `${iconSize}px` }"
        />
    </span>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import IconRevenueExpense from '~icons/tabler/arrows-down-up';

/**
 * ເຄື່ອງໝາຍຂອງລະບົບ — ລູກສອນຂຶ້ນ/ລົງ ໃນກອບສີ່ຫຼ່ຽມມົນ.
 *
 * ເປັນຫຍັງລູກສອນຂຶ້ນ-ລົງ: ນີ້ຄືລະບົບ **ລາຍຮັບ ແລະ ລາຍຈ່າຍ** — ເງິນເຂົ້າ ແລະ
 * ເງິນອອກ. ສອງລູກສອນສວນທາງກັນສື່ຄວາມນັ້ນໄດ້ທັນທີ ແລະ ຍັງອ່ານອອກຢູ່ທີ່ 19 px
 * (ຂະໜາດຕອນ sidebar ຫຍໍ້) ຕ່າງຈາກຮູບຊັ່ງທີ່ແຂນບາງໆຈະຫາຍໄປ.
 *
 * ເປັນ SVG ຈາກ Iconify (~1 KB, bundle ຕອນ build ບໍ່ດຶງຈາກເນັດ) ບໍ່ແມ່ນ PNG —
 * ໄຟລ໌ເດີມ sss-logo.png ໜັກ 2.5 MB ຂະນະທີ່ສະແດງແຄ່ 32–80 px.
 *
 * ເປັນຫຍັງຈຶ່ງໃສ່ກອບ ບໍ່ແມ່ນໄອຄອນເສັ້ນລ້ວນ: ໄອຄອນເສັ້ນບາງໆເທິງພື້ນຂາວຂອງ sidebar
 * ຈະຈືດຫາຍໄປ ແລະ ບໍ່ມີຮູບຮ່າງທີ່ຈື່ໄດ້. ກອບມົນໃຫ້ silhouette ຄົງທີ່ທຸກຂະໜາດ
 * ແລະ ໃຊ້ໄດ້ທັງພື້ນສະຫວ່າງ ແລະ ພື້ນມືດໂດຍບໍ່ຕ້ອງມີສອງສະບັບ.
 */
const props = withDefaults(defineProps<{ size?: number }>(), { size: 40 });

/** ໄອຄອນກິນ 60% ຂອງກອບ — ເຫຼືອຂອບຫາຍໃຈໃຫ້ຮູບຊົງມົນຍັງອ່ານອອກຕອນນ້ອຍ */
const iconSize = computed(() => Math.round(props.size * 0.6));
</script>
