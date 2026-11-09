<?php

use App\Domain\Money\CurrencyConverter;
use App\Domain\Money\DecimalString;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ອັດຕາທີ່ຄົນພິມເປັນ "ກີບຕໍ່ 1 ໜ່ວຍ" (1 ບາດ = 675.48 ກີບ) — ແບບທີ່ປ້າຍ BCEL ສະແດງ.
 *
 * `rate_per_usd` ຍັງຢູ່ ແລະ ຄວາມໝາຍບໍ່ປ່ຽນ ("ຈຳນວນສະກຸນຕໍ່ 1 USD"). ສິ່ງທີ່
 * snapshot ໃສ່ລາຍການ ແລະ ລາຍງານທັງໝົດຍັງໃຊ້ຄ່ານັ້ນ.
 *
 * ແຖວ LAK ບໍ່ມີ `rate_lak` (null): ອັດຕາຂອງມັນ "ກີບຕໍ່ 1 USD" ຢູ່ໃນ
 * `rate_per_usd` ແລ້ວ — ເກັບສອງບ່ອນ = ສອງແຫຼ່ງຄວາມຈິງ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            // ກີບຕໍ່ 1 ໜ່ວຍຂອງ currency_code ຕາມທີ່ຄົນພິມ. ເມື່ອມີຄ່າ
            // ExchangeRateService::rateFor() ຄິດ rate_per_usd ຈາກຄ່ານີ້ ÷ ອັດຕາ
            // LAK ຂອງວັນທີລາຍການ — 1 ບາດ = 675.48 ກີບ ຄົງຢູ່ຈົນກວ່າຈະຕັ້ງໃໝ່
            // ເຖິງວ່າອັດຕາ USD ຈະປ່ຽນ.
            $table->decimal('rate_lak', 24, 10)->nullable()->after('rate_per_usd');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->dropColumn('rate_lak');
        });
    }

    /**
     * ແຖວເກົ່າ (THB ຯລຯ) ໄດ້ rate_lak = ອັດຕາ LAK ທີ່ມີຜົນໃນວັນດຽວກັນ ÷ rate_per_usd.
     *
     * ປັດເປັນ 2 ຕຳແໜ່ງ ຄືກັບທີ່ BCEL ປະກາດ: 22239 ÷ 32.9233 = 675.4791 → 675.48
     * ເຊິ່ງກໍຄືຄ່າທີ່ຜູ້ໃຊ້ພິມເອງໃນເດືອນ 08/2026. ຄວາມຕ່າງຂອງ rate_per_usd
     * ທີ່ຕາມມາ < 0.0001 ບາດ/USD ແລະ ບໍ່ກະທົບລາຍການທີ່ບັນທຶກໄປແລ້ວ (snapshot).
     *
     * ແຖວທີ່ບໍ່ມີອັດຕາ LAK ກ່ອນວັນນັ້ນ → ປະ null ໄວ້: rateFor() ໃຊ້ rate_per_usd
     * ເດີມຕໍ່ໄປ ຄືກັບກ່ອນ migration.
     */
    private function backfill(): void
    {
        $rows = DB::table('exchange_rates')
            ->whereNotIn('currency_code', ['LAK', 'USD'])
            ->get(['id', 'currency_code', 'effective_date', 'rate_per_usd']);

        foreach ($rows as $row) {
            $lakPerUsd = DB::table('exchange_rates')
                ->where('currency_code', 'LAK')
                ->where('effective_date', '<=', $row->effective_date)
                ->orderByDesc('effective_date')
                ->value('rate_per_usd');

            if ($lakPerUsd === null) {
                continue;
            }

            $ratePerUsd = DecimalString::assert((string) $row->rate_per_usd, 'rate_per_usd');

            if (bccomp($ratePerUsd, '0', 10) <= 0) {
                continue;
            }

            // ຜ່ານ CurrencyConverter — CLAUDE.md: ການແປງສະກຸນເກີດຂຶ້ນບ່ອນດຽວ
            $quote = app(CurrencyConverter::class)->lakQuote(
                (string) $row->currency_code,
                $ratePerUsd,
                DecimalString::assert((string) $lakPerUsd, 'LAK rate_per_usd'),
            );
            $rateLak = bcadd($quote, '0.005', 2);

            DB::table('exchange_rates')->where('id', $row->id)->update(['rate_lak' => $rateLak]);
        }
    }
};
