/**
 * ການສະແດງວັນທີ.
 *
 * ລະບົບເກັບ ແລະ ຮັບສົ່ງວັນທີເປັນ ISO 'YYYY-MM-DD' ສະເໝີ — ນັ້ນຄືສິ່ງດຽວ
 * ທີ່ຮຽງລຳດັບໄດ້ ແລະ ບໍ່ກຳກວມ. `d/m/Y` ມີໄວ້ໃຫ້ຄົນອ່ານເທົ່ານັ້ນ
 * ຫ້າມສົ່ງກັບຄືນໄປ API.
 */

/** '2026-08-26' → '26/08/2026'. ຄ່າຫວ່າງ/ຜິດຮູບແບບ → '—' */
export function formatDate(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    // ຕັດ string ເອົາ ບໍ່ແມ່ນ new Date() — `new Date('2026-08-26')` ອ່ານເປັນ
    // UTC ທ່ຽງຄືນ ແລ້ວ timezone ລາວ (UTC+7) ຈະດຶງກັບມາເປັນມື້ກ່ອນໜ້າ
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso);

    if (!match) {
        return iso;
    }

    const [, year, month, day] = match;

    return `${day}/${month}/${year}`;
}

/**
 * '2026-09-14T09:05:00+07:00' → 'ຫາກໍ່ / 5 ນາທີກ່ອນ / 3 ຊົ່ວໂມງກ່ອນ / 2 ວັນກ່ອນ'.
 *
 * ໃຊ້ `Intl.RelativeTimeFormat` ຈຶ່ງໄດ້ພາສາຕາມ locale ຂອງ browser ໂດຍບໍ່ຕ້ອງ
 * ເພີ່ມ library. ລາວຍັງບໍ່ມີໃນ ICU ທຸກ browser — ຕົກມາເປັນອັງກິດເອງ
 * ເຊິ່ງຍັງອ່ານອອກ ແລະ ດີກວ່າການສະແດງເວລາເຕັມທີ່ຍາວຈົນລົ້ນແຖວ.
 *
 * ຮັບ ISO ທີ່ມີ offset ຕິດມາ (`toIso8601String()`) ຈຶ່ງໃຊ້ `new Date()` ໄດ້
 * ຢ່າງປອດໄພ — ຕ່າງຈາກ `formatDate()` ທີ່ຮັບວັນທີລ້ວນ.
 */
export function fromNow(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const at = new Date(iso);

    if (Number.isNaN(at.getTime())) {
        return '—';
    }

    const seconds = Math.round((at.getTime() - Date.now()) / 1000);
    const absolute = Math.abs(seconds);

    if (absolute < 60) {
        return 'ຫາກໍ່';
    }

    const format = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

    // ຫາໜ່ວຍໃຫຍ່ສຸດທີ່ຍັງໄດ້ຕົວເລກ >= 1 — "3 ຊົ່ວໂມງກ່ອນ" ອ່ານງ່າຍກວ່າ
    // "180 ນາທີກ່ອນ". ຮຽງຈາກນ້ອຍໄປໃຫຍ່ແລ້ວເອົາອັນສຸດທ້າຍທີ່ຜ່ານ.
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['minute', 60],
        ['hour', 3600],
        ['day', 86400],
        ['month', 2592000],
        ['year', 31536000],
    ];

    let chosen: [Intl.RelativeTimeFormatUnit, number] = units[0];

    for (const unit of units) {
        if (absolute >= unit[1]) {
            chosen = unit;
        }
    }

    return format.format(Math.round(seconds / chosen[1]), chosen[0]);
}

/**
 * '2026-09-11T14:05:00+07:00' → '11/09/2026 14:05'. ຄ່າຫວ່າງ → '—'
 *
 * ຕ່າງຈາກ `formatDate()` ຢູ່ບ່ອນທີ່ໃຊ້ `new Date()` ຈິງ — ຢູ່ນີ້ຈຳເປັນ
 * ເພາະຄ່າມີເວລາ ແລະ offset ຕິດມາ (`toIso8601String()`) ຈຶ່ງບໍ່ມີບັນຫາ
 * ການອ່ານເປັນ UTC ທ່ຽງຄືນ ຄືກັບວັນທີລ້ວນ. ສະແດງຕາມ timezone ຂອງເຄື່ອງ
 * ຜູ້ອ່ານ ເຊິ່ງຖືກຕ້ອງສຳລັບ "ເກີດຂຶ້ນເມື່ອໃດ".
 */
export function formatDateTime(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const at = new Date(iso);

    if (Number.isNaN(at.getTime())) {
        return iso;
    }

    const pad = (value: number) => String(value).padStart(2, '0');

    return (
        `${pad(at.getDate())}/${pad(at.getMonth() + 1)}/${at.getFullYear()} ` +
        `${pad(at.getHours())}:${pad(at.getMinutes())}`
    );
}
