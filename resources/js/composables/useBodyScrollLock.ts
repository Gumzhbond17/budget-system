import { onBeforeUnmount, onMounted } from 'vue';

/**
 * ລັອກການເລື່ອນຂອງໜ້າຫຼັກຕະຫຼອດເວລາທີ່ modal ເປີດຢູ່.
 *
 * ບໍ່ດັ່ງນັ້ນການໝູນລໍ້ເໜືອ backdrop ຈະໄປເລື່ອນໜ້າທີ່ຢູ່ຂ້າງຫຼັງ ໃນຂະນະທີ່
 * modal ຄ້າງຢູ່ກັບທີ່ — ເບິ່ງຄືກັບວ່າໜ້າຈໍພັງ.
 *
 * ⚠️ ນັບຈຳນວນຜູ້ລັອກ ບໍ່ແມ່ນ boolean: ມີ modal ຊ້ອນກັນຢູ່ຈິງ (ໜ້າຕ່າງ
 * ລາຍລະອຽດ → lightbox ຮູບຫຼັກຖານ). ຖ້າໃຊ້ boolean ການປິດອັນໃນຈະປົດລັອກ
 * ໃຫ້ອັນນອກນຳ ແລ້ວໜ້າຫຼັງຈະເລື່ອນໄດ້ທັງທີ່ຍັງມີ modal ເປີດຢູ່.
 */
let lockCount = 0;
let previousOverflow = '';

export function useBodyScrollLock(): void {
    onMounted(() => {
        if (lockCount === 0) {
            previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
        }

        lockCount += 1;
    });

    onBeforeUnmount(() => {
        lockCount = Math.max(lockCount - 1, 0);

        if (lockCount === 0) {
            document.body.style.overflow = previousOverflow;
        }
    });
}
