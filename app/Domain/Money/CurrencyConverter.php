<?php

declare(strict_types=1);

namespace App\Domain\Money;

/**
 * ຈຸດດຽວໃນລະບົບທີ່ແປງສະກຸນເງິນ.
 *
 * ເດີມ code ນີ້ຢູ່ໃນ ExpenseService ແຕ່ພໍ budget_items ຕ້ອງແປງເປັນ USD ນຳ
 * ມັນຈະກາຍເປັນສອງສຳເນົາທັນທີ — ຊຶ່ງຄືຄວາມສ່ຽງ inconsistency ອັນດຽວກັນທີ່
 * ພາກ 2.1.ຄ ພະຍາຍາມກັນ. ດັ່ງນັ້ນຈຶ່ງດຶງອອກມາເປັນ collaborator ດຽວ.
 *
 * ຂໍ້ຕົກລົງທີ່ບໍ່ມີວັນປ່ຽນ: rate = "ຈຳນວນສະກຸນຕໍ່ 1 USD" ສະເໝີ
 * ດັ່ງນັ້ນ usd = amount / rate  (ຫານ ບໍ່ແມ່ນຄູນ).
 */
final class CurrencyConverter
{
    /**
     * ແປງເປັນ USD cents.
     *
     * @param  numeric-string  $ratePerUsd
     */
    public function toUsdMinor(Money $amount, string $ratePerUsd): int
    {
        if ($amount->currency === 'USD') {
            return $amount->minor;
        }

        // ຄິດດ້ວຍ bcmath ທີ່ scale ສູງ ແລ້ວຄ່ອຍປັດເສດຄັ້ງດຽວຕອນສຸດທ້າຍ
        $usdMajor = bcdiv($amount->toDecimal(), $ratePerUsd, 10);   // "574.4485294117"
        $usdMinor = bcmul($usdMajor, '100', 4);                     // "57444.8529"

        return (int) round((float) $usdMinor);                      // 57445 cents = $574.45
    }

    /**
     * ແປງເປັນກີບ ຜ່ານ USD ເປັນສະກຸນກາງ (pivot).
     *
     * ⚠️ THB→LAK ຈຶ່ງປັດເສດ 2 ຄັ້ງ — ຄວາມຕ່າງ < 1 ກີບ ຕໍ່ລາຍການ, ຍອມຮັບໄດ້.
     *    ຖ້າຕ້ອງການ precision ສູງກວ່າ ໃຫ້ເກັບ rate ຄູ່ຕົງໃນ exchange_rates ແທນ.
     *
     * @param  numeric-string  $ratePerUsd
     * @param  numeric-string  $lakRatePerUsd
     */
    public function toLakMinor(Money $amount, string $ratePerUsd, string $lakRatePerUsd): int
    {
        if ($amount->currency === 'LAK') {
            return $amount->minor;
        }

        $usdMajor = bcdiv($amount->toDecimal(), $ratePerUsd, 10);
        $lakMajor = bcmul($usdMajor, $lakRatePerUsd, 4);

        return (int) round((float) $lakMajor);   // LAK decimals = 0
    }

    /**
     * ອັດຕາທີ່ຄົນພິມ ("ກີບຕໍ່ 1 ໜ່ວຍ") → ອັດຕາທີ່ລະບົບເກັບ ("ຈຳນວນສະກຸນຕໍ່ 1 USD").
     *
     * ຄົນລາວອ່ານອັດຕາຈາກປ້າຍ BCEL ເປັນກີບສະເໝີ: "1 USD = 22,239 ກີບ",
     * "1 ບາດ = 675.48 ກີບ". ໜ້າຈໍຈຶ່ງຮັບແບບນັ້ນ ແລ້ວແປງຢູ່ນີ້ບ່ອນດຽວ —
     * ສິ່ງທີ່ຖືກ snapshot ແລະ ລາຍງານຍັງເປັນ "ຕໍ່ 1 USD" ຄືເກົ່າ.
     *
     *   LAK: quote ຄື ກີບຕໍ່ 1 USD ຢູ່ແລ້ວ  → ຄືນຕາມເດີມ
     *   USD: rate ຕໍ່ຕົວມັນເອງ            → 1
     *   ອື່ນ: ກີບຕໍ່ 1 ໜ່ວຍ                → (ກີບຕໍ່ 1 USD) ÷ (ກີບຕໍ່ 1 ໜ່ວຍ)
     *         THB: 22239 ÷ 675.48 = 32.9232841239 ບາດຕໍ່ 1 USD
     *
     * @param  numeric-string  $lakQuote
     * @param  numeric-string  $lakPerUsd
     * @return numeric-string
     */
    public function ratePerUsdFromLakQuote(string $currency, string $lakQuote, string $lakPerUsd): string
    {
        return match (strtoupper($currency)) {
            'USD' => '1.0000000000',
            'LAK' => bcadd($lakQuote, '0', 10),
            default => bcdiv($lakPerUsd, $lakQuote, 10),
        };
    }

    /**
     * ທິດກັບກັນຂອງ `ratePerUsdFromLakQuote()` — ສະແດງອັດຕາທີ່ເກັບໄວ້ເປັນກີບ.
     *
     * ປັດເປັນ 6 ຕຳແໜ່ງ: 22239 ÷ 32.9232841239 ອອກມາ 675.4799999998 ເພາະ
     * ການຫານຮອບທຳອິດຕັດເສດ. ຄົນພິມ 675.48 ກໍຕ້ອງເຫັນ 675.48 ຄືນ.
     *
     * @param  numeric-string  $ratePerUsd
     * @param  numeric-string  $lakPerUsd
     * @return numeric-string
     */
    public function lakQuote(string $currency, string $ratePerUsd, string $lakPerUsd): string
    {
        return match (strtoupper($currency)) {
            'USD' => bcadd($lakPerUsd, '0', 6),
            'LAK' => bcadd($ratePerUsd, '0', 6),
            // ຄ່າບວກສະເໝີ → ບວກເຄິ່ງໜ່ວຍແລ້ວຕັດ = ປັດເຄິ່ງຂຶ້ນ
            default => bcadd(bcdiv($lakPerUsd, $ratePerUsd, 12), '0.0000005', 6),
        };
    }
}
