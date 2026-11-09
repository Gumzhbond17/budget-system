import { onScopeDispose } from 'vue';

/**
 * ດຶງຂໍ້ມູນຕາຕະລາງຄືນໃໝ່ເປັນໄລຍະດ້ວຍ `setInterval`.
 *
 * ໜ້າຕາຕະລາງທຸກໜ້າໃຊ້ອັນນີ້ຮ່ວມກັນ ເພື່ອໃຫ້ພຶດຕິກຳການໂຫຼດຄືນເປັນອັນດຽວກັນ
 * ໝົດ — ບໍ່ແມ່ນແຕ່ລະໜ້າຮ້ອງ setInterval ຂອງຕົນເອງແລ້ວລືມ clear.
 *
 * ສີ່ຢ່າງທີ່ `setInterval` ລ້າໆ ບໍ່ໄດ້ໃຫ້ ແລະ ຂາດບໍ່ໄດ້:
 *
 *   1. **ຢຸດເມື່ອອອກຈາກໜ້າ** — ບໍ່ດັ່ງນັ້ນ timer ຍັງແລ່ນຢູ່ຫຼັງປ່ຽນໜ້າ
 *      ແລ້ວຍິງ request ໃສ່ component ທີ່ຕາຍໄປແລ້ວຕະຫຼອດໄປ (memory leak).
 *
 *   2. **ຢຸດເມື່ອແທັບຖືກເຊື່ອງ** — ແທັບທີ່ເປີດຄ້າງໄວ້ຂ້າມຄືນຈະຍິງ request
 *      ນັບພັນເທື່ອໂດຍບໍ່ມີໃຜເບິ່ງ. ກັບມາເຫັນອີກເທື່ອໜຶ່ງ → ດຶງທັນທີ
 *      ບໍ່ຕ້ອງລໍຮອບຖັດໄປ (ຂໍ້ມູນທີ່ເຫັນຕອນສະຫຼັບກັບມາຈຶ່ງໃໝ່ສະເໝີ).
 *
 *   3. **ບໍ່ໃຫ້ຊ້ອນກັນ** — ຖ້າ request ກ່ອນໜ້າຍັງບໍ່ທັນກັບມາ (ເນັດຊ້າ)
 *      ໃຫ້ຂ້າມຮອບນັ້ນໄປ. ບໍ່ດັ່ງນັ້ນເນັດຊ້າ = request ຄ້າງກ້ອນໃຫຍ່ຂຶ້ນເລື້ອຍໆ.
 *
 *   4. **ຢຸດເມື່ອຜູ້ໃຊ້ກຳລັງເຮັດວຽກຢູ່** — ຜ່ານ `paused` (ຟອມເປີດ, ກ່ອງຢືນຢັນ
 *      ເປີດ). ການດຶງຂໍ້ມູນທັບຕອນຜູ້ໃຊ້ພິມຢູ່ ຫຼື ຕອນກຳລັງຢືນຢັນການລຶບ
 *      ເຮັດໃຫ້ແຖວທີ່ກຳລັງເບິ່ງຢູ່ຫາຍໄປກາງຄັນ.
 */

/**
 * 30 ວິນາທີ — ຂໍ້ມູນຫຼັກ (ໝວດ, ຜູ້ຈ່າຍ, ທະນາຄານ) ປ່ຽນນ້ອຍຫຼາຍ ແລະ ຜູ້ໃຊ້
 * ພ້ອມກັນມີບໍ່ຫຼາຍຄົນ ຈຶ່ງບໍ່ຕ້ອງຖີ່ກວ່ານີ້. ຄ່າດຽວທົ່ວແອັບ ເພື່ອບໍ່ໃຫ້ແຕ່ລະ
 * ໜ້າເລືອກຕົວເລກຂອງຕົນເອງແລ້ວກາຍເປັນຈັງຫວະປົນກັນ.
 */
export const AUTO_REFRESH_MS = 30_000;

interface Options {
    /** ຄືນ true ເມື່ອບໍ່ຄວນດຶງຂໍ້ມູນຕອນນີ້ (ຟອມ/ກ່ອງຢືນຢັນເປີດຢູ່) */
    paused?: () => boolean;
    intervalMs?: number;
}

export function useAutoRefresh(
    refresh: () => void | Promise<void>,
    options: Options = {},
) {
    const intervalMs = options.intervalMs ?? AUTO_REFRESH_MS;
    const isPaused = options.paused ?? (() => false);

    let timer: ReturnType<typeof setInterval> | undefined;
    /** ມີ request ຄ້າງຢູ່ບໍ — ກັນຮອບໃໝ່ຊ້ອນທັບຮອບເກົ່າ (ຂໍ້ 3) */
    let inFlight = false;

    async function tick(): Promise<void> {
        if (inFlight || isPaused() || document.hidden) {
            return;
        }

        inFlight = true;

        try {
            await refresh();
        } finally {
            // ປົດລັອກແມ່ນແຕ່ຕອນລົ້ມ — ບໍ່ດັ່ງນັ້ນຄວາມຜິດພາດເທື່ອດຽວ
            // ຈະປິດການໂຫຼດຄືນຖາວອນຈົນກວ່າຈະ reload ໜ້າ
            inFlight = false;
        }
    }

    function start(): void {
        stop();
        timer = setInterval(() => void tick(), intervalMs);
    }

    function stop(): void {
        clearInterval(timer);
        timer = undefined;
    }

    function onVisibilityChange(): void {
        if (document.hidden) {
            stop();

            return;
        }

        // ດຶງທັນທີ ແລ້ວຄ່ອຍນັບຈັງຫວະໃໝ່ຈາກຕອນນີ້ — ບໍ່ແມ່ນສືບຕໍ່ຈາກຮອບເກົ່າ
        void tick();
        start();
    }

    document.addEventListener('visibilitychange', onVisibilityChange);
    start();

    onScopeDispose(() => {
        stop();
        document.removeEventListener('visibilitychange', onVisibilityChange);
    });

    return { start, stop, refreshNow: tick };
}
