<?php

declare(strict_types=1);

use App\Models\Payee;
use Illuminate\Support\Facades\DB;

/**
 * ພາກ 9 ຂອງ docs ບັງຄັບວ່າ `bank_account_no` ຕ້ອງເຂົ້າລະຫັດ ແລະ mask ໃນ response.
 * ໄຟລ໌ Excel ເດີມມີໃບໂອນທີ່ເຫັນເລກບັນຊີເຕັມ — ນີ້ຄືສິ່ງທີ່ຕ້ອງບໍ່ເກີດຊ້ຳ.
 */
it('ເກັບເລກບັນຊີແບບເຂົ້າລະຫັດໃນ DB', function () {
    $payee = Payee::factory()->withBankAccount('0101234567890')->create();

    $stored = DB::table('payees')->where('id', $payee->id)->value('bank_account_no');

    expect($stored)->toBeString()
        ->not->toContain('0101234567890')
        ->and(strlen((string) $stored))->toBeGreaterThan(60);   // ເຫດຜົນທີ່ຄໍລຳຕ້ອງເປັນ text
});

it('ຖອດລະຫັດຄືນເປັນຄ່າເດີມຜ່ານ model', function () {
    $payee = Payee::factory()->withBankAccount('0101234567890')->create();

    expect($payee->fresh()->bank_account_no)->toBe('0101234567890');
});

it('mask ເຫຼືອແຕ່ 4 ຕົວທ້າຍ', function () {
    $payee = Payee::factory()->withBankAccount('0101234567890')->create();

    expect($payee->maskedBankAccountNo())->toBe('•••••••••7890');
});

it('ຄືນ null ເມື່ອບໍ່ມີເລກບັນຊີ', function () {
    expect(Payee::factory()->create()->maskedBankAccountNo())->toBeNull();
});

it('ບໍ່ຮົ່ວເລກບັນຊີອອກ JSON', function () {
    $payee = Payee::factory()->withBankAccount('0101234567890')->create();

    expect($payee->toArray())->not->toHaveKey('bank_account_no')
        ->and(json_encode($payee))->not->toContain('0101234567890');
});
