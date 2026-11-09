import Swal from 'sweetalert2';

/**
 * ຂໍ້ຄວາມແຈ້ງຜົນສຳເລັດແບບ toast (SweetAlert2).
 *
 * ເປັນຫຍັງ **toast** ບໍ່ແມ່ນກ່ອງກາງຈໍທີ່ຕ້ອງກົດ OK:
 * ຕອນສ້າງລາຍການໃໝ່ ຟອມ **ບໍ່ປິດ** — ມັນສະຫຼັບໄປໂໝດແກ້ໄຂເພື່ອໃຫ້ແນບຫຼັກຖານ
 * ຕໍ່ໄດ້ທັນທີ (ເບິ່ງ `submit()` ຢູ່ Expenses.vue / Revenues.vue). ກ່ອງທີ່ຕ້ອງ
 * ກົດປິດຈະຂວາງຂັ້ນຕອນນັ້ນທຸກເທື່ອ ແລະ ກາຍເປັນການກົດເພີ່ມທີ່ບໍ່ໄດ້ໃຫ້ຫຍັງ.
 *
 * ⚠️ `.swal2-container` ຕັ້ງ z-index ໄວ້ 1060 ແຕ່ `Modal.vue` ໃຊ້ 99999 —
 * ຖ້າບໍ່ຍົກຂຶ້ນ (ເຮັດຢູ່ resources/css/app.css) toast ຈະຖືກຟອມບັງໝົດ.
 */
const toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true,
    customClass: { popup: 'app-toast' },
    didOpen(element) {
        // ຢຸດຕົວນັບເມື່ອເອົາເມົ້າໄປວາງ — ຂໍ້ຄວາມມີເລກທີ່ໃບສຳຄັນ ຜູ້ໃຊ້ອາດຢາກອ່ານຊ້ຳ
        element.addEventListener('mouseenter', Swal.stopTimer);
        element.addEventListener('mouseleave', Swal.resumeTimer);
    },
});

/**
 * @param title ຂໍ້ຄວາມຫຼັກ — ແປແລ້ວ (ຟັງຊັນນີ້ບໍ່ຮູ້ຈັກ i18n)
 * @param text ລາຍລະອຽດປະກອບ ເຊັ່ນ ເລກທີ່ໃບສຳຄັນ ແລະ ຍອດເງິນ
 */
export function toastSuccess(title: string, text?: string): void {
    void toast.fire({ icon: 'success', title, text });
}
