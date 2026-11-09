<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * ຈຸດດຽວທີ່ຕັ້ງຄ່າ mPDF ໃນລະບົບນີ້.
 *
 * ⚠️ ຂໍ້ຈຳກັດທີ່ຕ້ອງຮູ້ (docs/architecture.md ພາກ 6): mPDF ວາງວັນນະຍຸດລາວໄດ້
 * ດີກວ່າ DomPDF ຫຼາຍເມື່ອເປີດ OpenType Layout ແຕ່ຍັງບໍ່ຖືກ 100% ໃນຄຳທີ່ມີ
 * ຕີນອັກສອນຊ້ອນເລິກ. ຖ້າຮອດຈຸດທີ່ຮັບບໍ່ໄດ້ ທາງອອກຄື Chromium (spatie/laravel-pdf)
 * ເຊິ່ງໃຊ້ HarfBuzz ອັນດຽວກັບ browser — ແກ້ຢູ່ຄລາສນີ້ບ່ອນດຽວ ທຸກລາຍງານໄດ້ນຳ.
 *
 * ສ້າງແບບ synchronous ໃນ request ເລີຍ — ບໍ່ຜ່ານ queue ເພື່ອໃຫ້ກົດແລ້ວໄດ້ໄຟລ໌ທັນທີ.
 * ເພດານແຖວ (`config('pdf.max_rows')`) ຄືສິ່ງທີ່ເຮັດໃຫ້ປອດໄພ.
 */
final class ReportPdfRenderer
{
    /**
     * @param  array<string, mixed>  $data
     * @return string ໄບຕ໌ຂອງໄຟລ໌ PDF
     */
    public function render(string $view, array $data): string
    {
        $pdf = $this->mpdf();
        $pdf->WriteHTML(View::make($view, $data)->render());

        return (string) $pdf->Output('', Destination::STRING_RETURN);
    }

    private function mpdf(): Mpdf
    {
        $tempDir = (string) config('pdf.temp_dir');

        // mPDF ຂຽນ cache ຂອງຟອນລົງທີ່ນີ້ — ຖ້າບໍ່ມີໂຟນເດີມັນຈະ throw
        File::ensureDirectoryExists($tempDir);

        /** @var array<string, mixed> $fontDirs */
        $fontDirs = (new ConfigVariables)->getDefaults()['fontDir'];
        /** @var array<string, mixed> $fontData */
        $fontData = (new FontVariables)->getDefaults()['fontdata'];

        $family = (string) config('pdf.font.family');

        return new Mpdf([
            'mode' => 'utf-8',
            // ນອນ: ຕາຕະລາງລາຍງານມີ 9–11 ຄໍລຳ ຕັ້ງແລ້ວລົ້ນແນ່ນອນ (ພາກ 6)
            'format' => 'A4-L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 14,
            'margin_bottom' => 16,
            'margin_header' => 6,
            'margin_footer' => 8,

            'tempDir' => $tempDir,

            'fontDir' => [...(array) $fontDirs, (string) config('pdf.font.dir')],
            'fontdata' => [
                ...(array) $fontData,
                $family => [
                    'R' => (string) config('pdf.font.file'),
                    // 0xFF = ເປີດ OpenType Layout ທັງໝົດ. ນີ້ຄືສິ່ງດຽວທີ່ເຮັດໃຫ້
                    // ສະຫຼະລອຍ ແລະ ວັນນະຍຸດລາວວາງຖືກຕຳແໜ່ງ — ຫ້າມເອົາອອກ.
                    'useOTL' => 0xFF,
                ],
            ],
            'default_font' => $family,
            'default_font_size' => 8,
        ]);
    }
}
