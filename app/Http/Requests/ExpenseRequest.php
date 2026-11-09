<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Expense;
use App\Services\ExchangeRateService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * ກົດການກວດ input ຮ່ວມຂອງການສ້າງ/ແກ້ລາຍຈ່າຍ.
 *
 * ໜ້າທີ່ສຳຄັນທີ່ສຸດຂອງຊັ້ນນີ້ບໍ່ແມ່ນການກວດ type ແຕ່ຄື **ການຈັບ rate ທີ່ພິມຜິດ**
 * ກ່ອນມັນຈະກາຍເປັນຕົວເລກຖາວອນໃນລາຍງານ (docs/architecture.md ພາກ 1.4 ຂໍ້ 2).
 */
abstract class ExpenseRequest extends FormRequest
{
    /** ລາຍການທີ່ກຳລັງແກ້ (null ຕອນສ້າງໃໝ່) — ໃຊ້ເປັນຄ່າ fallback ຕອນກວດ rate */
    abstract protected function existingExpense(): ?Expense;

    protected function prepareForValidation(): void
    {
        $normalized = [];

        // "12,500,000" ແລະ "12 500 000" ຄືສິ່ງທີ່ຄົນພິມຈິງ — ລ້າງກ່ອນກວດ
        if (is_string($amount = $this->input('amount'))) {
            $normalized['amount'] = str_replace([',', ' ', "\u{00A0}"], '', $amount);
        }

        if (is_string($currency = $this->input('currency_code'))) {
            $normalized['currency_code'] = strtoupper(trim($currency));
        }

        foreach (['fx_rate_per_usd', 'fx_rate_lak'] as $field) {
            if (is_string($rate = $this->input($field))) {
                $normalized[$field] = str_replace([',', ' '], '', $rate);
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function baseRules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'payer_id' => ['nullable', 'integer', 'exists:payers,id'],
            'payee_id' => ['nullable', 'integer', 'exists:payees,id'],
            // ບໍ່ໄດ້ບັງຄັບຄວາມສອດຄ່ອງກັບ payment_method ຢູ່ນີ້ —
            // ເບິ່ງ ExpenseService::bankIdFor() ສຳລັບເຫດຜົນ
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],

            'expense_date' => ['date', 'before_or_equal:today'],
            'title' => ['string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'unit' => ['nullable', 'string', 'max:20'],
            'quantity' => ['nullable', 'numeric', 'gt:0', 'max:99999999999'],

            'currency_code' => ['string', 'size:3', 'exists:currencies,code'],
            // ຮູບແບບທົດສະນິຍົມທຳມະດາເທົ່ານັ້ນ — bcmath ຮັບ "1e5" ບໍ່ໄດ້ (ເບິ່ງ DecimalString)
            'amount' => ['string', 'regex:/^\d+(\.\d+)?$/', 'not_in:0,0.0,0.00'],
            'fx_rate_per_usd' => ['nullable', 'string', 'regex:/^\d+(\.\d+)?$/', 'not_in:0'],
            // ອັດຕາເປັນກີບຕາມທີ່ຟອມສະແດງ: LAK = ກີບຕໍ່ 1 USD, THB = ກີບຕໍ່ 1 ບາດ.
            // ExpenseService ແປງເປັນ fx_rate_per_usd ຜ່ານ CurrencyConverter
            'fx_rate_lak' => ['nullable', 'string', 'regex:/^\d+(\.\d+)?$/', 'not_in:0', 'prohibits:fx_rate_per_usd'],

            'payment_method' => ['nullable', 'in:cash,bank_transfer,mobile_banking,cheque,credit,other'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:5000'],

            // ຜູ້ໃຊ້ຢືນຢັນວ່າ rate ຜິດປົກກະຕິນັ້ນຖືກຕ້ອງແທ້ (ເບິ່ງ withValidator)
            'confirm_unusual_rate' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // input ພື້ນຖານຍັງຜິດຢູ່ → ຂໍ້ຄວາມເລື່ອງ rate ຈະເປັນສຽງລົບກວນ
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->assertRateIsSane($validator);
        });
    }

    /**
     * ນີ້ຄືກົນໄກທີ່ຈະຈັບ "rate 2117 ແທນ 21170" — ຄວາມຜິດພາດອັນດຽວທີ່ເຮັດໃຫ້
     * ລາຍງານໃນ Excel ຜິດໄປ 5,314 USD.
     *
     * ເຕືອນແລ້ວຍັງຢືນຢັນໄດ້ (`confirm_unusual_rate`) ເພາະບາງເທື່ອຜູ້ໃຊ້ແລກເງິນ
     * ໃນອັດຕາທີ່ຜິດປົກກະຕິແທ້ — ບໍ່ຄວນ block ຈົນເຮັດວຽກບໍ່ໄດ້.
     */
    private function assertRateIsSane(Validator $validator): void
    {
        // ຟອມສົ່ງເປັນກີບ; API ແບບເດີມສົ່ງ "ຕໍ່ 1 USD" — ກວດຕາມແບບທີ່ສົ່ງມາ
        $field = is_string($this->input('fx_rate_lak')) && $this->input('fx_rate_lak') !== ''
            ? 'fx_rate_lak'
            : 'fx_rate_per_usd';
        $rate = $this->input($field);

        if (! is_string($rate) || $rate === '' || $this->boolean('confirm_unusual_rate')) {
            return;
        }

        $existing = $this->existingExpense();
        $currency = $this->input('currency_code') ?? $existing?->currency_code;
        $date = $this->input('expense_date') ?? $existing?->expense_date?->toDateString();

        if (! is_string($currency) || ! is_string($date)) {
            return;
        }

        $fx = app(ExchangeRateService::class);
        $result = $field === 'fx_rate_lak'
            ? $fx->sanityCheckLakQuote($currency, $rate, Carbon::parse($date))
            : $fx->sanityCheck($currency, $rate, Carbon::parse($date));

        if (! $result['ok']) {
            $validator->errors()->add(
                $field,
                // ຂໍ້ຄວາມນີ້ສະແດງໃຫ້ຜູ້ໃຊ້ໂດຍກົງ — ອ້າງເຖິງກ່ອງໝາຍໃນຟອມ ບໍ່ແມ່ນຊື່ field ຂອງ API
                $result['message'].' ຖ້າອັດຕານີ້ຖືກຕ້ອງແທ້ ໃຫ້ໝາຍເລືອກ "ຢືນຢັນວ່າອັດຕານີ້ຖືກຕ້ອງ" ແລ້ວບັນທຶກຄືນ.'
            );
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.regex' => 'ຈຳນວນເງິນຕ້ອງເປັນຕົວເລກທຳມະດາ ເຊັ່ນ 12500000 ຫຼື 12,500,000',
            'amount.not_in' => 'ຈຳນວນເງິນຕ້ອງບໍ່ເປັນສູນ',
            'fx_rate_per_usd.regex' => 'ອັດຕາແລກປ່ຽນຕ້ອງເປັນຕົວເລກທຳມະດາ ເຊັ່ນ 21760 ຫຼື 32.22',
            'fx_rate_lak.regex' => 'ອັດຕາແລກປ່ຽນຕ້ອງເປັນຕົວເລກທຳມະດາ ເຊັ່ນ 22239 ຫຼື 675.48',
            'currency_code.exists' => 'ບໍ່ຮູ້ຈັກສະກຸນເງິນນີ້',
            'expense_date.before_or_equal' => 'ວັນທີຈ່າຍຕ້ອງບໍ່ຢູ່ໃນອະນາຄົດ',
            'quantity.gt' => 'ຈຳນວນຕ້ອງຫຼາຍກວ່າ 0',
        ];
    }
}
