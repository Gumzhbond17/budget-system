import Swal from 'sweetalert2';
import type { SweetAlertIcon } from 'sweetalert2';

/**
 * ກ່ອງແຈ້ງເຕືອນກາງຈໍ (SweetAlert2) ທີ່ຕ້ອງກົດ OK ກ່ອນເຮັດຕໍ່.
 *
 * ໃຊ້ສະເພາະເລື່ອງທີ່ຜູ້ໃຊ້ **ຕ້ອງ** ອ່ານ ເຊັ່ນ login ບໍ່ຜ່ານ ຫຼື ບັນຊີໝົດອາຍຸ —
 * ຜົນສຳເລັດທົ່ວໄປໃຫ້ໃຊ້ toast (utils/toast.ts) ທີ່ບໍ່ຂວາງການເຮັດວຽກ.
 *
 * ປຸ່ມໃຊ້ class ຂອງແອັບເອງ (buttonsStyling: false) ໃຫ້ສີຄືກັບປຸ່ມອື່ນ.
 */
const dialog = Swal.mixin({
    buttonsStyling: false,
    customClass: {
        popup: 'app-dialog',
        confirmButton:
            'rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600',
    },
});

/**
 * @param title ຂໍ້ຄວາມຫຼັກ — ແປແລ້ວ (ຟັງຊັນນີ້ບໍ່ຮູ້ຈັກ i18n)
 * @param text ລາຍລະອຽດ — ຂຶ້ນແຖວໃໝ່ດ້ວຍ "\n" ໄດ້
 * @returns ສຳເລັດເມື່ອຜູ້ໃຊ້ປິດກ່ອງ
 */
export async function alertDialog(
    icon: SweetAlertIcon,
    title: string,
    text?: string,
): Promise<void> {
    await dialog.fire({ icon, title, text });
}
