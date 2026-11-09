<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Money\CurrencyConverter;
use App\Domain\Money\DecimalString;
use App\Models\ExchangeRate;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use RuntimeException;

final class ExchangeRateService
{
    public function __construct(private CurrencyConverter $converter) {}

    /**
     * ຫາ rate ຂອງສະກຸນ ໃນວັນທີທີ່ກຳນົດ.
     *
     * ກົນລະຍຸດ: ຫາ rate ຂອງວັນນັ້ນກ່ອນ, ຖ້າບໍ່ມີໃຫ້ຖອຍຫຼັງຫາ rate ຫຼ້າສຸດກ່ອນວັນນັ້ນ
     * (ບໍ່ເອົາ rate ອະນາຄົດ — ຈະເຮັດໃຫ້ຍອດຍ້ອນຫຼັງປ່ຽນ)
     *
     * ແຖວທີ່ຕັ້ງເປັນກີບ (`rate_lak`, ເຊັ່ນ 1 ບາດ = 675.48 ກີບ) ຄິດເປັນ "ຕໍ່ 1 USD"
     * ດ້ວຍອັດຕາ LAK ຂອງ **ວັນທີທີ່ຖາມ**: ອັດຕາບາດ-ກີບຄົງທີ່ຈົນກວ່າຈະຕັ້ງໃໝ່
     * ເຖິງວ່າອັດຕາ USD ຈະປ່ຽນ — ຄືກັບທີ່ຄົນອ່ານປ້າຍ BCEL.
     *
     * @return numeric-string rate ໃນຮູບແບບ "ຈຳນວນ currency ຕໍ່ 1 USD"
     *
     * @throws RuntimeException ຖ້າບໍ່ພົບ rate ຫຼື ຄ່າໃນ DB ໃຊ້ກັບ bcmath ບໍ່ໄດ້
     */
    public function rateFor(string $currency, CarbonInterface $date): string
    {
        $currency = strtoupper($currency);

        if ($currency === 'USD') {
            return '1.0000000000';
        }

        $row = $this->rowFor($currency, $date);

        if ($currency !== 'LAK' && $row['rate_lak'] !== null) {
            return $this->converter->ratePerUsdFromLakQuote(
                $currency, $row['rate_lak'], $this->rateFor('LAK', $date),
            );
        }

        return $row['rate_per_usd'];
    }

    /**
     * ອັດຕາເປັນກີບ — ສິ່ງທີ່ໜ້າຈໍສະແດງ ແລະ ຄົນພິມ.
     *
     *   LAK / USD: ກີບຕໍ່ 1 USD
     *   ອື່ນ:      ກີບຕໍ່ 1 ໜ່ວຍ (THB: ກີບຕໍ່ 1 ບາດ)
     *
     * @return numeric-string
     *
     * @throws RuntimeException ຖ້າບໍ່ພົບ rate
     */
    public function lakQuoteFor(string $currency, CarbonInterface $date): string
    {
        $currency = strtoupper($currency);

        if (in_array($currency, ['USD', 'LAK'], true)) {
            return $this->rateFor('LAK', $date);
        }

        $row = $this->rowFor($currency, $date);

        // ແຖວເກົ່າທີ່ບໍ່ມີ rate_lak (ບໍ່ມີອັດຕາ LAK ຕອນ migrate) → ຄິດຈາກ rate_per_usd
        return $row['rate_lak']
            ?? $this->converter->lakQuote($currency, $row['rate_per_usd'], $this->rateFor('LAK', $date));
    }

    /**
     * ອັດຕາທີ່ຄົນພິມເປັນກີບ → "ຈຳນວນສະກຸນຕໍ່ 1 USD" ສຳລັບ snapshot.
     *
     * @param  numeric-string  $lakQuote
     * @return numeric-string
     *
     * @throws RuntimeException ຖ້າຕ້ອງໃຊ້ອັດຕາ LAK ແຕ່ຫາບໍ່ພົບ
     */
    public function ratePerUsdFromLakQuote(string $currency, string $lakQuote, CarbonInterface $date): string
    {
        $currency = strtoupper($currency);

        // LAK: quote ຄືກີບຕໍ່ 1 USD ຢູ່ແລ້ວ — ບໍ່ຕ້ອງຫາອັດຕາອ້າງອີງ.
        // ຖ້າໄປຫາ ຄົນທີ່ພິມອັດຕາເອງໃນມື້ທີ່ຍັງບໍ່ມີອັດຕາໃນລະບົບຈະບັນທຶກບໍ່ໄດ້.
        $lakPerUsd = in_array($currency, ['USD', 'LAK'], true)
            ? $lakQuote
            : $this->rateFor('LAK', $date);

        return $this->converter->ratePerUsdFromLakQuote($currency, $lakQuote, $lakPerUsd);
    }

    /**
     * ກວດວ່າ rate ທີ່ຜູ້ໃຊ້ປ້ອນສົມເຫດສົມຜົນບໍ.
     *
     * ນີ້ຄື guard rail ທີ່ຈະຈັບຂໍ້ຜິດພາດແບບ "rate 2117 ແທນ 21170" ທີ່ພົບໃນ Excel
     * (ຄວາມຜິດພາດອັນດຽວນັ້ນເຮັດໃຫ້ຍອດຜິດໄປ 5,314 USD)
     *
     * @param  string  $rate  ຈຳນວນສະກຸນຕໍ່ 1 USD
     * @return array{ok: bool, message: ?string}
     */
    public function sanityCheck(string $currency, string $rate, CarbonInterface $date): array
    {
        $currency = strtoupper($currency);

        /** @var array<string, array{int|float, int|float}> $bounds */
        $bounds = config('currency.sane_bounds', []);   // ['LAK' => [15000, 35000], 'THB' => [25, 45]]

        return $this->check(
            (float) $rate,
            $bounds[$currency] ?? null,
            fn () => $this->rateFor($currency, $date),
            fn (float $min, float $max) => "ອັດຕາແລກປ່ຽນ {$rate} ຢູ່ນອກຂອບເຂດປົກກະຕິຂອງ {$currency} ({$min}–{$max}). ",
        );
    }

    /**
     * `sanityCheck()` ສຳລັບອັດຕາທີ່ພິມເປັນກີບ (ໜ້າຕັ້ງອັດຕາ ແລະ ຟອມທຸລະກຳ).
     *
     * ຂອບເຂດ ແລະ ຂໍ້ຄວາມເປັນກີບ — ຄົນພິມ 675.48 ຕ້ອງບໍ່ໄດ້ຄຳເຕືອນເວົ້າເຖິງ 32.92.
     *
     * @param  string  $lakQuote  ກີບຕໍ່ 1 ໜ່ວຍ (LAK: ກີບຕໍ່ 1 USD)
     * @return array{ok: bool, message: ?string}
     */
    public function sanityCheckLakQuote(string $currency, string $lakQuote, CarbonInterface $date): array
    {
        $currency = strtoupper($currency);
        $unit = $currency === 'LAK' ? 'USD' : $currency;

        /** @var array<string, array{int|float, int|float}> $bounds */
        $bounds = config('currency.sane_bounds_lak', []);

        return $this->check(
            (float) $lakQuote,
            $bounds[$currency] ?? null,
            fn () => $this->lakQuoteFor($currency, $date),
            fn (float $min, float $max) => "ອັດຕາແລກປ່ຽນ {$lakQuote} ກີບ ຕໍ່ 1 {$unit} ຢູ່ນອກຂອບເຂດປົກກະຕິ ({$min}–{$max}). ",
        );
    }

    /**
     * @param  array{int|float, int|float}|null  $bounds
     * @param  Closure(): string  $reference
     * @param  Closure(float, float): string  $outOfBounds
     * @return array{ok: bool, message: ?string}
     */
    private function check(float $rate, ?array $bounds, Closure $reference, Closure $outOfBounds): array
    {
        // ຂອບເຂດຕັ້ງໃນ config ເພື່ອປັບໄດ້ໂດຍບໍ່ຕ້ອງ deploy — ບໍ່ມີ = ບໍ່ກວດ
        if ($bounds === null) {
            return ['ok' => true, 'message' => null];
        }

        [$min, $max] = $bounds;

        if ($rate < $min || $rate > $max) {
            return [
                'ok' => false,
                'message' => $outOfBounds((float) $min, (float) $max).'ກະລຸນາກວດຄືນ — ອາດພິມຂາດ/ເກີນຕົວເລກ.',
            ];
        }

        // ກວດການຫ່າງຈາກ rate ອ້າງອີງເກີນ 10%
        try {
            $referenceRate = (float) $reference();
            $deviation = abs($rate - $referenceRate) / $referenceRate;

            if ($deviation > 0.10) {
                return [
                    'ok' => false,
                    'message' => sprintf(
                        'ອັດຕາທີ່ປ້ອນຕ່າງຈາກອັດຕາອ້າງອີງ %.1f%% (%s vs %s)',
                        $deviation * 100, $rate, $referenceRate
                    ),
                ];
            }
        } catch (RuntimeException) {
            // ບໍ່ມີ rate ອ້າງອີງ — ຂ້າມການປຽບທຽບ ແຕ່ຍັງຜ່ານ bounds check ແລ້ວ
        }

        return ['ok' => true, 'message' => null];
    }

    /**
     * ແຖວທີ່ມີຜົນໃນວັນນັ້ນ (cache 6 ຊົ່ວໂມງ).
     *
     * @return array{rate_per_usd: numeric-string, rate_lak: numeric-string|null}
     *
     * @throws RuntimeException
     */
    private function rowFor(string $currency, CarbonInterface $date): array
    {
        // ໂທເຄັນຢູ່ໃນ key: ຕັ້ງອັດຕາໃໝ່ຜ່ານໜ້າຈັດການແລ້ວເຫັນຜົນທັນທີ
        // ບໍ່ຕ້ອງລໍ 6 ຊົ່ວໂມງໃຫ້ cache ໝົດອາຍຸ (ເບິ່ງ ExchangeRate::cacheToken)
        $key = "fx:row:{$currency}:".ExchangeRate::cacheToken($currency).":{$date->toDateString()}";

        /** @var array{rate_per_usd: mixed, rate_lak: mixed} $row */
        $row = Cache::remember($key, now()->addHours(6), function () use ($currency, $date): array {
            $row = ExchangeRate::query()
                ->effectiveOn($currency, $date)
                ->first(['rate_per_usd', 'rate_lak']);

            if ($row === null) {
                throw new RuntimeException(
                    "ບໍ່ພົບອັດຕາແລກປ່ຽນ {$currency} ສຳລັບວັນທີ {$date->toDateString()}"
                );
            }

            return [
                'rate_per_usd' => (string) $row->rate_per_usd,
                'rate_lak' => $row->rate_lak === null ? null : (string) $row->rate_lak,
            ];
        });

        // ຄ່າທີ່ອອກຈາກນີ້ໄປເຂົ້າ bcmath ໂດຍກົງ — ຮັບປະກັນຮູບແບບກ່ອນສົ່ງຄືນ
        try {
            return [
                'rate_per_usd' => DecimalString::assert((string) $row['rate_per_usd'], "Exchange rate for {$currency}"),
                'rate_lak' => $row['rate_lak'] === null
                    ? null
                    : DecimalString::assert((string) $row['rate_lak'], "LAK rate for {$currency}"),
            ];
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }
    }
}
