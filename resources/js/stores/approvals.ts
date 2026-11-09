import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { api } from '@/api/client';
import type { PendingApproval } from '@/types/transactions';

/**
 * ຄິວລໍຖ້າອະນຸມັດ — ໃຊ້ຮ່ວມກັນລະຫວ່າງກະດິ່ງແຈ້ງເຕືອນ ແລະ ໜ້າ `/approvals`.
 *
 * ເປັນຫຍັງເປັນ store ບໍ່ແມ່ນ composable ຕໍ່ໜ້າ:
 * ກະດິ່ງຢູ່ AppHeader ສ່ວນລາຍການຢູ່ໜ້າ — ຄົນລະຕົ້ນໄມ້ component ກັນ. ຖ້າ
 * ແຍກ state ກັນ ການກົດ "ອະນຸມັດ" ຢູ່ໜ້າຈະບໍ່ລົບເລກເທິງກະດິ່ງຈົນກວ່າຈະຮອດ
 * ຮອບດຶງຖັດໄປ — ຜູ້ໃຊ້ເຫັນ "ເຫຼືອ 3" ທັງທີ່ຫາກໍເຄລຍໝົດແລ້ວ.
 */
export const useApprovalsStore = defineStore('approvals', () => {
    const items = ref<PendingApproval[]>([]);

    /**
     * ຈຳນວນເຕັມຈາກ server — **ບໍ່ແມ່ນ** `items.length`.
     *
     * ກະດິ່ງດຶງມາແຄ່ 5 ແຖວເທິງສຸດ ແຕ່ປ້າຍເລກຕ້ອງບອກຈຳນວນທັງໝົດ
     * (`meta.total`) ບໍ່ດັ່ງນັ້ນມັນຈະຄ້າງຢູ່ທີ່ 5 ຕະຫຼອດ.
     */
    const total = ref(0);

    const loading = ref(false);

    /** ດຶງມາຢ່າງໜ້ອຍໜຶ່ງເທື່ອແລ້ວບໍ — ກັນກະດິ່ງກະພິບ "0" ຕອນເປີດແອັບ */
    const loaded = ref(false);

    const hasPending = computed(() => total.value > 0);

    /**
     * ຈຳນວນໜ້າທີ່ຕ້ອງການຄິວ**ເຕັມ** ທີ່ເປີດຢູ່ (`/approvals`).
     *
     * ກະດິ່ງດຶງ 5 ແຖວທຸກ 30 ວິນາທີ ລົງ `items` ອັນດຽວກັນ — ຕອນຢູ່ໜ້າຄິວເຕັມ
     * ມັນເຄີຍທັບລາຍການທັງໝົດໃຫ້ເຫຼືອ 5 ແຖວ. ຕາບໃດທີ່ມີໜ້າເຕັມເປີດຢູ່ ທຸກການດຶງ
     * ເອົາໝົດ ແລະ ກະດິ່ງຕັດເອົາ 5 ແຖວທຳອິດເອງ.
     */
    let fullViewers = 0;

    /** ລົງທະບຽນໜ້າຄິວເຕັມ — ຄືນຟັງຊັນສຳລັບຖອນຕອນອອກຈາກໜ້າ */
    function holdFull(): () => void {
        fullViewers++;

        return () => {
            fullViewers = Math.max(0, fullViewers - 1);
        };
    }

    /** ຄຳຕອບທີ່ເກົ່າກວ່າການດຶງອັນຫຼ້າສຸດຖືກຖິ້ມ */
    let fetchSeq = 0;

    /**
     * @param limit ຈຳກັດຈຳນວນແຖວທີ່ເອົາມາ (ກະດິ່ງໃຊ້ 5). ບໍ່ໃສ່ = ເອົາໝົດ.
     *              ບໍ່ສົນໃຈເມື່ອມີໜ້າຄິວເຕັມເປີດຢູ່ (ເບິ່ງ `holdFull`).
     */
    async function fetch(limit?: number): Promise<void> {
        const seq = ++fetchSeq;
        const effectiveLimit = fullViewers > 0 ? undefined : limit;

        loading.value = true;

        try {
            const { data } = await api.get<{
                data: PendingApproval[];
                meta: { total: number };
            }>('/approvals', {
                params: effectiveLimit ? { limit: effectiveLimit } : undefined,
            });

            if (seq !== fetchSeq) {
                return;
            }

            items.value = data.data;
            total.value = data.meta.total;
            loaded.value = true;
        } catch {
            // ດຶງບໍ່ໄດ້ = ປະລາຍການເກົ່າໄວ້ຄືເກົ່າ ບໍ່ແມ່ນລ້າງຖິ້ມ.
            // ກະດິ່ງບໍ່ແມ່ນໜ້າທີ່ຜູ້ໃຊ້ກຳລັງເຮັດວຽກຢູ່ ຈຶ່ງບໍ່ຄວນເດັ້ງ
            // ຂໍ້ຜິດພາດໃສ່ໜ້າ — ເນັດສະດຸດເທື່ອດຽວບໍ່ຄວນລ້າງເລກຖິ້ມ.
        } finally {
            if (seq === fetchSeq) {
                loading.value = false;
            }
        }
    }

    /**
     * ເອົາແຖວອອກຫຼັງອະນຸມັດ/ປະຕິເສດສຳເລັດ — ບໍ່ຕ້ອງລໍດຶງຮອບໃໝ່.
     *
     * ລົດ `total` ນຳ ເພື່ອໃຫ້ເລກເທິງກະດິ່ງຕົກທັນທີ ເຖິງແມ່ນແຖວທີ່ເອົາອອກ
     * ຈະບໍ່ຢູ່ໃນ 5 ແຖວທີ່ດຶງມາກໍຕາມ.
     */
    function remove(uuid: string): void {
        items.value = items.value.filter((item) => item.uuid !== uuid);
        total.value = Math.max(0, total.value - 1);
    }

    /** ອອກຈາກລະບົບແລ້ວຕ້ອງບໍ່ເຫຼືອຄິວຂອງຄົນກ່ອນຄ້າງໄວ້ */
    function reset(): void {
        items.value = [];
        total.value = 0;
        loaded.value = false;
    }

    return {
        items,
        total,
        loading,
        loaded,
        hasPending,
        fetch,
        holdFull,
        remove,
        reset,
    };
});
