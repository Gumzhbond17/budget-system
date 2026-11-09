<?php

declare(strict_types=1);

/**
 * ທຸກຕົວອັກສອນທີ່ template ລາຍງານພິມອອກມາ ຕ້ອງມີຢູ່ໃນ Phetsarath OT.
 *
 * ເປັນຫຍັງຕ້ອງມີ test ນີ້: PDF ຝັງຟອນ **ດຽວ** ຈຶ່ງບໍ່ມີ fallback ໃຫ້ຕົກໄປໃຊ້.
 * ຕົວອັກສອນທີ່ຟອນບໍ່ມີ (ເຊັ່ນ '−' U+2212 ຫຼື '✓' U+2713 ທີ່ເຄີຍໃຊ້ຢູ່) ຈະອອກ
 * ເປັນຊ່ອງຫວ່າງ **ໂດຍບໍ່ມີ error ໃດເລີຍ** — build ຜ່ານ, test ຜ່ານ, PDF ສ້າງໄດ້
 * ແຕ່ຄົນອ່ານຍອດຕິດລົບເປັນຍອດບວກ. ຈັບໄດ້ດ້ວຍຕາເທົ່ານັ້ນ ຖ້າບໍ່ມີ test ນີ້.
 *
 * ຖ້າ test ນີ້ລົ້ມ: ໃຊ້ຕົວອັກສອນອື່ນທີ່ຟອນມີ (ASCII '-' ແທນ '−', ຄຳລາວແທນ '✓')
 * ຫຼື ຝັງຟອນທີສອງເຂົ້າ ReportPdfRenderer.
 */

/**
 * ອ່ານ cmap ຂອງໄຟລ໌ TTF ແລ້ວຄືນຊຸດ codepoint ທີ່ຟອນຮອງຮັບ.
 *
 * ອ່ານ format 4 (Windows Unicode BMP) ເຊິ່ງພຽງພໍ — ຕົວອັກສອນລາວ ແລະ
 * ເຄື່ອງໝາຍທີ່ລາຍງານໃຊ້ ຢູ່ໃນ BMP ໝົດ.
 *
 * @return list<array{int, int}> ຄູ່ [start, end] ຂອງແຕ່ລະຊ່ວງ
 */
function laoFontRanges(): array
{
    static $ranges = null;

    if ($ranges !== null) {
        return $ranges;
    }

    $data = (string) file_get_contents(
        resource_path('css/fonts/'.config('pdf.font.file'))
    );

    $numTables = unpack('n', substr($data, 4, 2))[1];
    $cmapOffset = null;

    for ($i = 0; $i < $numTables; $i++) {
        $record = substr($data, 12 + $i * 16, 16);

        if (substr($record, 0, 4) === 'cmap') {
            $cmapOffset = unpack('N', substr($record, 8, 4))[1];
            break;
        }
    }

    expect($cmapOffset)->not->toBeNull('ຟອນບໍ່ມີຕາຕະລາງ cmap');

    $subtableCount = unpack('n', substr($data, $cmapOffset + 2, 2))[1];
    $subtableOffset = null;

    for ($i = 0; $i < $subtableCount; $i++) {
        $record = substr($data, $cmapOffset + 4 + $i * 8, 8);
        $platform = unpack('n', substr($record, 0, 2))[1];
        $encoding = unpack('n', substr($record, 2, 2))[1];

        if ($platform === 3 && in_array($encoding, [1, 10], true)) {
            $subtableOffset = $cmapOffset + unpack('N', substr($record, 4, 4))[1];
            break;
        }
    }

    expect($subtableOffset)->not->toBeNull('ຟອນບໍ່ມີ cmap ຂອງ Windows Unicode');

    $segCountX2 = unpack('n', substr($data, $subtableOffset + 6, 2))[1];
    $segCount = intdiv($segCountX2, 2);
    $ranges = [];

    for ($i = 0; $i < $segCount; $i++) {
        $ranges[] = [
            unpack('n', substr($data, $subtableOffset + 16 + $segCountX2 + $i * 2, 2))[1],
            unpack('n', substr($data, $subtableOffset + 14 + $i * 2, 2))[1],
        ];
    }

    return $ranges;
}

function fontHasCodepoint(int $codepoint): bool
{
    foreach (laoFontRanges() as [$start, $end]) {
        if ($codepoint >= $start && $codepoint <= $end) {
            return true;
        }
    }

    return false;
}

it('ຟອນມີຕົວອັກສອນທີ່ template ລາຍງານໃຊ້ຄົບ', function (string $template) {
    $source = (string) file_get_contents(resource_path("views/reports/{$template}"));

    // ຄຳອະທິບາຍ Blade ບໍ່ໄດ້ຖືກພິມອອກມາ — ແລະ ຄຳອະທິບາຍຂອງພວກເຮົາເອງ
    // ອ້າງເຖິງຕົວອັກສອນທີ່ຫ້າມໃຊ້ ຈຶ່ງຕ້ອງຕັດອອກກ່ອນ
    $rendered = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $source);

    $missing = [];

    foreach (preg_split('//u', $rendered, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
        $codepoint = mb_ord($char, 'UTF-8');

        // ຂ້າມຕົວຄວບຄຸມ (ຂຶ້ນແຖວ, tab) — ບໍ່ໄດ້ວາດເປັນ glyph
        if ($codepoint < 0x20 || fontHasCodepoint($codepoint)) {
            continue;
        }

        $missing[sprintf('U+%04X (%s)', $codepoint, $char)] = true;
    }

    expect(array_keys($missing))->toBe(
        [],
        "ຟອນບໍ່ມີຕົວອັກສອນເຫຼົ່ານີ້ — ໃນ PDF ຈະອອກເປັນຊ່ອງຫວ່າງ ({$template})"
    );
})->with([
    'layout.blade.php',
    'expenses.blade.php',
    'revenues.blade.php',
    'ledger.blade.php',
]);

it('ຢືນຢັນວ່າ test ນີ້ຈັບຕົວອັກສອນທີ່ຫາຍໄປໄດ້ຈິງ', function () {
    // ສອງຕົວນີ້ຄືຕົວທີ່ເຄີຍຫຼຸດອອກໄປຈິງ — ຖ້າມື້ໃດຟອນມີແລ້ວ test ນີ້ຈະລົ້ມ
    // ແລ້ວຄ່ອຍລຶບຂໍ້ຈຳກັດອອກ
    expect(fontHasCodepoint(0x2212))->toBeFalse('− MINUS SIGN')
        ->and(fontHasCodepoint(0x2713))->toBeFalse('✓ CHECK MARK')
        // ຕົວທີ່ໃຊ້ແທນຕ້ອງມີແນ່ນອນ
        ->and(fontHasCodepoint(0x002D))->toBeTrue('- HYPHEN')
        ->and(fontHasCodepoint(0x2014))->toBeTrue('— EM DASH');
});
