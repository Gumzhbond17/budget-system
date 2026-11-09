<template>
    <!--
        ບ່ອນທີ່ຕາຕະລາງ (ຫຼື ລາຍການ) ຈະຢູ່ ຕອນຍັງໂຫຼດບໍ່ທັນແລ້ວ.
        padding ດຽວກັບ `empty-state` ເພື່ອໃຫ້ຄວາມສູງບໍ່ກະໂດດຕອນຂໍ້ມູນມາຮອດ.

        `role="status"` + `aria-live="polite"`: ຜູ້ໃຊ້ screen reader ບໍ່ເຫັນ
        ພາບໝູນ — ຖ້າບໍ່ປະກາດ ລາວຈະໄດ້ຍິນແຕ່ຄວາມງຽບແລ້ວຄິດວ່າໜ້າຄ້າງ.
        "polite" ບໍ່ແມ່ນ "assertive": ການໂຫຼດບໍ່ແມ່ນເລື່ອງດ່ວນທີ່ຄວນຕັດບົດ
        ສິ່ງທີ່ກຳລັງອ່ານຢູ່.
    -->
    <div
        role="status"
        aria-live="polite"
        class="flex flex-col items-center justify-center gap-3 px-5 py-12 text-sm text-gray-500 dark:text-gray-400"
    >
        <Spinner :size="size" class="text-brand-500" />
        <span>{{ t(label) }}</span>
    </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import Spinner from './Spinner.vue';

interface Props {
    /** ຄີພາສາ — ປ່ຽນໄດ້ເມື່ອຢາກບອກສະເພາະວ່າກຳລັງໂຫຼດຫຍັງ */
    label?: string;
    size?: 'sm' | 'md' | 'lg';
}

withDefaults(defineProps<Props>(), {
    label: 'common.loading',
    size: 'lg',
});

const { t } = useI18n();
</script>
