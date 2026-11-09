<template>
    <!--
        ບໍ່ມີ `overflow-y-auto` ຢູ່ຊັ້ນນີ້ໂດຍເຈດຕະນາ: ຊັ້ນນີ້ຄື "ໜ້າຈໍ" ທີ່ modal
        ລອຍຢູ່ເທິງ ມັນຕ້ອງຢູ່ນິ້ງ. ເມື່ອກ່ອນມີ overflow ຢູ່ນີ້ ການໝູນລໍ້ຈຶ່ງ
        ເລື່ອນທັງກ້ອນ modal ຂຶ້ນລົງ ແທນທີ່ຈະຢູ່ກັບທີ່.

        ເນື້ອໃນທີ່ຍາວເກີນຈໍຖືກຈັດການຢູ່ໃນແຕ່ລະ modal ເອງ (ເບິ່ງ FormModal) —
        ບ່ອນທີ່ຮູ້ວ່າສ່ວນໃດຕ້ອງຢູ່ນິ້ງ (ຫົວຂໍ້, ປຸ່ມ) ແລະ ສ່ວນໃດເລື່ອນໄດ້.
    -->
    <!--
        ຊັ້ນ z-index ຂອງທັງລະບົບ (ຢ່າປ່ຽນອັນໜຶ່ງໂດຍບໍ່ເບິ່ງອັນອື່ນ):
          99999   modal ທົ່ວໄປ (ອັນນີ້)
          100000  .vel-modal — lightbox ຮູບຫຼັກຖານ (ຕັ້ງຢູ່ app.css)
          100001  modal ທີ່ຊ້ອນເທິງ modal ອື່ນ (`stacked`)
          999999  .swal2-container — toast (ຕ້ອງຢູ່ເທິງສຸດສະເໝີ)
    -->
    <div
        class="fixed inset-0 flex items-center justify-center"
        :class="stacked ? 'z-100001' : 'z-99999'"
    >
        <div
            v-if="fullScreenBackdrop"
            class="fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px]"
            aria-hidden="true"
            @click="$emit('close')"
        />
        <slot name="body" />
    </div>
</template>

<script setup lang="ts">
import { useBodyScrollLock } from '@/composables/useBodyScrollLock';

interface ModalProps {
    fullScreenBackdrop?: boolean;
    /**
     * modal ນີ້ເປີດເທິງ modal ອື່ນທີ່ຍັງຄ້າງຢູ່ (ເຊັ່ນ ໜ້າຕ່າງຢືນຢັນການ
     * ອະນຸມັດ ທີ່ເປີດຈາກໜ້າຕ່າງລາຍລະອຽດ).
     *
     * ຈຳເປັນ ເພາະທຸກ modal ໃຊ້ z-index ດຽວກັນ ແລະ ບໍ່ໄດ້ teleport —
     * ຄ່າເທົ່າກັນເມື່ອໃດ **ລຳດັບໃນ DOM** ເປັນຜູ້ຕັດສິນວ່າອັນໃດຢູ່ເທິງ ແລ້ວ
     * ອັນທີ່ຂຽນກ່ອນຈະຖືກ backdrop ຂອງອັນທີ່ຂຽນຫຼັງບັງຈົນເບິ່ງຄືກົດປຸ່ມບໍ່ຕິດ.
     */
    stacked?: boolean;
}

defineProps<ModalProps>();
defineEmits(['close']);

// ໜ້າຫຼັງຫ້າມເລື່ອນຕະຫຼອດເວລາທີ່ modal ນີ້ຢູ່ໃນ DOM
useBodyScrollLock();
</script>
