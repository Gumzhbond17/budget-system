import type { Money } from '@/types/settings';

/**
 * ຈັດຮູບແບບຈຳນວນເງິນຈາກ minor units.
 *
 * ⚠️ ຫ້າມແປງເປັນ Number ແລ້ວຄິດໄລ່ຕໍ່: JSON number ຢູ່ JS ຄື IEEE-754 double
 * ດັ່ງນັ້ນ 6,559,489,394 ກີບ ຈະກັບມາເປັນ float rounding error ອັນເກົ່າ
 * (docs/architecture.md ພາກ 1.4). ຢູ່ນີ້ໃຊ້ Number ເພື່ອ **ສະແດງຜົນເທົ່ານັ້ນ**
 * ແລະ ຄ່າຢູ່ໃນຂອບເຂດປອດໄພຂອງ Number.MAX_SAFE_INTEGER ຢູ່ແລ້ວ.
 */
export function formatMoney(money: Money | null | undefined): string {
    if (!money) {
        return '—';
    }

    const value = money.minor / 10 ** money.decimals;

    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: money.decimals,
        maximumFractionDigits: money.decimals,
    }).format(value);
}

/** ພ້ອມສະກຸນ — ໃຊ້ບ່ອນທີ່ສະກຸນບໍ່ຊັດເຈນຈາກ context */
export function formatMoneyWithCurrency(
    money: Money | null | undefined,
): string {
    return money ? `${formatMoney(money)} ${money.currency}` : '—';
}

/**
 * ຈັດກຸ່ມຫຼັກພັນໃນຂະນະທີ່ຜູ້ໃຊ້ພິມ: "1200000" → "1,200,000".
 *
 * ບໍ່ໄດ້ຜ່ານ Number ຈັກບ່ອນ — ເຮັດວຽກກັບ string ລ້ວນໆ ຈຶ່ງບໍ່ມີທາງທີ່
 * ຍອດກີບ 6,559,489,394 ຈະຖືກປັດເສດຕອນສະແດງ (ພາກ 1.4).
 *
 * @param decimals ຈຳນວນຫຼັກທົດສະນິຍົມສູງສຸດ (LAK = 0, USD/THB = 2)
 */
export function groupNumeric(input: string, decimals: number): string {
    let cleaned = input.replace(/[^\d.]/g, '');

    // ຈຸດຫຼາຍອັນ = ພິມຜິດ — ເອົາອັນທຳອິດໄວ້ ລຶບທີ່ເຫຼືອ
    const firstDot = cleaned.indexOf('.');

    if (firstDot !== -1) {
        cleaned =
            cleaned.slice(0, firstDot + 1) +
            cleaned.slice(firstDot + 1).replace(/\./g, '');
    }

    if (decimals === 0) {
        cleaned = cleaned.split('.')[0];
    }

    const [rawInt = '', fraction] = cleaned.split('.');

    // ຕັດສູນນຳໜ້າ ("007" → "7") ແຕ່ຮັກສາ "0" ດຽວໄວ້
    const integer = rawInt.replace(/^0+(?=\d)/, '');
    const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    if (fraction === undefined) {
        return grouped;
    }

    return `${grouped === '' ? '0' : grouped}.${fraction.slice(0, decimals)}`;
}

/**
 * ນັບຕົວອັກສອນທີ່ "ມີຄວາມໝາຍ" (ຕົວເລກ ແລະ ຈຸດ) ກ່ອນຕຳແໜ່ງ cursor.
 *
 * ນີ້ຄືກຸນແຈຂອງການຮັກສາຕຳແໜ່ງ cursor ຕອນ `groupNumeric` ຂະນະພິມ: ຫຼັງຈັດ
 * ຮູບແບບແລ້ວ ຈຳນວນເຄື່ອງໝາຍຈຸດປ່ຽນໄປ ແຕ່ຈຳນວນຕົວເລກກ່ອນ cursor ຍັງເທົ່າເດີມ.
 * ຖ້າບໍ່ເຮັດແບບນີ້ cursor ຈະກະໂດດໄປທ້າຍຊ່ອງທຸກຄັ້ງທີ່ພິມ ແລ້ວແກ້ຕົວເລກກາງໆ
 * ບໍ່ໄດ້ເລີຍ.
 */
export function countSignificant(value: string, upTo: number): number {
    return value.slice(0, upTo).replace(/[^\d.]/g, '').length;
}

/** ຕຳແໜ່ງໃນຂໍ້ຄວາມທີ່ຈັດຮູບແບບແລ້ວ ຫຼັງຈາກຜ່ານຕົວທີ່ມີຄວາມໝາຍ n ຕົວ */
export function caretAfter(formatted: string, significant: number): number {
    if (significant === 0) {
        return 0;
    }

    let seen = 0;

    for (let i = 0; i < formatted.length; i++) {
        if (/[\d.]/.test(formatted[i])) {
            seen++;

            if (seen === significant) {
                return i + 1;
            }
        }
    }

    return formatted.length;
}

/**
 * ຕັດສູນທ້າຍທີ່ບໍ່ມີຄວາມໝາຍອອກ: "21760.0000000000" → "21760".
 *
 * ຖານຂໍ້ມູນເກັບ rate ເປັນ decimal(24,10) ຈຶ່ງຄືນມາພ້ອມສູນ 10 ຕົວສະເໝີ —
 * ອ່ານຍາກ ແລະ ເຮັດໃຫ້ຊ່ອງປ້ອນຂໍ້ມູນເບິ່ງຄືເສຍ.
 */
export function trimTrailingZeros(value: string): string {
    if (!value.includes('.')) {
        return value;
    }

    return value.replace(/\.?0+$/, '');
}

/**
 * ຄ່າອັດຕາທີ່ຈະສົ່ງໄປ server ຕອນບັນທຶກ: null = ບໍ່ສົ່ງ (server ໃຊ້ຄ່າເດີມ).
 *
 * ຕອນແກ້ໄຂ ຖ້າຜູ້ໃຊ້ບໍ່ໄດ້ແຕະອັດຕາ ແລະ ບໍ່ໄດ້ປ່ຽນສະກຸນ ຢ່າສົ່ງຄືນ:
 * ຟອມສະແດງອັດຕາເປັນກີບ (1 ບາດ = 675.48 ກີບ) ແລະ server ແປງຄືນເປັນ
 * "ຕໍ່ 1 USD" ດ້ວຍອັດຕາ LAK ປັດຈຸບັນ — ສົ່ງທຸກເທື່ອ = ແກ້ແຕ່ຫົວຂໍ້ກໍຂະຍັບ
 * snapshot ຂອງລາຍການ. ປຽບທຽບເປັນຕົວເລກ ເພາະຊ່ອງໃສ່ຈຸດຫຼັກພັນ ("22,239").
 */
export function rateToSend(
    value: string,
    original?: { rate: string; currency: string; newCurrency: string },
): string | null {
    if (!value) {
        return null;
    }

    if (
        original &&
        original.currency === original.newCurrency &&
        Number(value.replace(/[,\s]/g, '')) === Number(original.rate)
    ) {
        return null;
    }

    return value;
}
