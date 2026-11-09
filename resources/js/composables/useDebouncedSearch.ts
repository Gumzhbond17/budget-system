import { onScopeDispose, watch } from 'vue';
import type { Ref } from 'vue';

/**
 * ຍິງ callback ຫຼັງຜູ້ໃຊ້ຢຸດພິມ — ບໍ່ດັ່ງນັ້ນຈະໄດ້ 1 request ຕໍ່ 1 ຕົວອັກສອນ.
 *
 * ຍິງເທື່ອທຳອິດທັນທີ (immediate) ເພື່ອໂຫຼດຂໍ້ມູນຕອນເປີດໜ້າ ໂດຍບໍ່ຕ້ອງ
 * ໃຫ້ແຕ່ລະໜ້າຮ້ອງ fetchAll() ເອງອີກ.
 */
export function useDebouncedSearch(
    source: Ref<string>,
    callback: (term: string) => void,
    delay = 300,
): void {
    let timer: ReturnType<typeof setTimeout> | undefined;

    watch(
        source,
        (term, previous) => {
            clearTimeout(timer);

            // ຄັ້ງທຳອິດ (previous === undefined) ບໍ່ຕ້ອງລໍ — ໜ້າຍັງຫວ່າງຢູ່
            if (previous === undefined) {
                callback(term);

                return;
            }

            timer = setTimeout(() => callback(term), delay);
        },
        { immediate: true },
    );

    // ອອກຈາກໜ້າກ່ອນ timer ຍິງ → ຫ້າມ fetch ໃສ່ component ທີ່ຕາຍໄປແລ້ວ
    onScopeDispose(() => clearTimeout(timer));
}
