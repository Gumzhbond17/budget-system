<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Project;
use App\Models\Revenue;
use App\Models\User;
use App\Services\Reports\ExpenseReportPdf;
use App\Services\Reports\ExpenseReportService;
use App\Services\Reports\GeneralLedgerService;
use App\Services\Reports\LedgerReportPdf;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportPdfLink;
use App\Services\Reports\RevenueReportPdf;
use App\Services\Reports\RevenueReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ເປີດເບິ່ງລາຍງານເປັນ PDF ຜ່ານ URL ທີ່ມີລາຍເຊັນ.
 *
 * ⚠️ ເສັ້ນທາງເຫຼົ່ານີ້ຢູ່ນອກ `auth:sanctum` ໂດຍເຈດຕະນາ — ເບິ່ງເຫດຜົນເຕັມຢູ່
 * {@see ReportPdfLink}. ສະຫຼຸບ: ການເປີດ URL ໃນແທັບໃໝ່
 * (ໂດຍສະເພາະຕອນໂຫຼດຄືນ) ບໍ່ມີ header `Referer` ຈຶ່ງເຮັດໃຫ້ Sanctum ບໍ່ເປີດ
 * session ແລ້ວຕອບ 401 ທັງທີ່ຜູ້ໃຊ້ຍັງ login ຢູ່.
 *
 * ສິດເຂົ້າເຖິງມາຈາກສອງຊັ້ນ: ລາຍເຊັນ (ພິສູດວ່າ URL ອອກໂດຍລະບົບ ແລະ ຍັງບໍ່ໝົດອາຍຸ)
 * ບວກກັບການກວດ Policy ຊ້ຳຈາກ id ຜູ້ໃຊ້ທີ່ຝັງມາ.
 */
final class ReportExportController extends Controller
{
    public function __construct(
        private ExpenseReportService $expenseReports,
        private RevenueReportService $revenueReports,
        private GeneralLedgerService $ledger,
        private ExpenseReportPdf $expensePdf,
        private RevenueReportPdf $revenuePdf,
        private LedgerReportPdf $ledgerPdf,
    ) {}

    public function expenses(Request $request, Project $project): Response
    {
        $user = $this->authorizeSignedUser($request, $project, Expense::class);
        $filters = ReportFilters::fromArray($request->query());

        $this->assertPrintable($this->expenseReports->rowCount($project, $filters));

        return $this->inline(
            $this->expensePdf->render($project, $filters, $user),
            $this->expensePdf->filename($project),
        );
    }

    public function revenues(Request $request, Project $project): Response
    {
        $user = $this->authorizeSignedUser($request, $project, Revenue::class);
        $filters = ReportFilters::fromArray($request->query());

        $this->assertPrintable($this->revenueReports->rowCount($project, $filters));

        return $this->inline(
            $this->revenuePdf->render($project, $filters, $user),
            $this->revenuePdf->filename($project),
        );
    }

    public function ledger(Request $request, Project $project): Response
    {
        // GL ໃຊ້ ExpensePolicy ເປັນປະຕູ — RevenuePolicy ມີກົດດຽວກັນ (ຄືກັບ LedgerController)
        $user = $this->authorizeSignedUser($request, $project, Expense::class);
        $filters = ReportFilters::fromArray($request->query());

        $this->assertPrintable($this->ledger->rowCount($project, $filters));

        return $this->inline(
            $this->ledgerPdf->render($project, $filters, $user),
            $this->ledgerPdf->filename($project),
        );
    }

    /**
     * ດຶງຜູ້ໃຊ້ທີ່ຝັງໃນ URL ແລ້ວກວດ Policy ຊ້ຳ.
     *
     * ລາຍເຊັນພິສູດພຽງວ່າ URL ນີ້ອອກໂດຍລະບົບ ແລະ ຍັງບໍ່ໝົດອາຍຸ — ບໍ່ໄດ້ພິສູດວ່າ
     * ຜູ້ນັ້ນ **ຍັງ** ມີສິດຢູ່. ຄົນທີ່ຖືກຖອດອອກຈາກໂຄງການຫຼັງໄດ້ URL ໄປແລ້ວ
     * ຕ້ອງໃຊ້ບໍ່ໄດ້ອີກ ຈຶ່ງກວດຢູ່ນີ້ທຸກເທື່ອ.
     *
     * @param  class-string  $model
     */
    private function authorizeSignedUser(Request $request, Project $project, string $model): User
    {
        $user = User::query()->find($request->integer('user'));

        abort_if($user === null, 403);
        abort_unless($user->can('viewAny', [$model, $project]), 403);

        return $user;
    }

    /**
     * `inline` ບໍ່ແມ່ນ `attachment`: ຜູ້ໃຊ້ຕ້ອງໄດ້ **ເບິ່ງກ່ອນ** ວ່າຕົວກັ່ນຕອງ
     * ໃຫ້ລາຍງານທີ່ຕ້ອງການແທ້ບໍ ແລ້ວຈຶ່ງຄ່ອຍບັນທຶກ/ພິມຈາກຕົວອ່ານ PDF ຂອງ browser.
     * ຖ້າບັງຄັບດາວໂຫຼດເລີຍ ຄົນຈະໄດ້ໄຟລ໌ຂີ້ເຫຍື້ອເຕັມເຄື່ອງຕອນລອງປັບຕົວກັ່ນຕອງ.
     *
     * ຊື່ໄຟລ໌ຍັງສົ່ງໄປນຳ — ເປັນຊື່ທີ່ຕັ້ງໃຫ້ຕອນກົດ "ບັນທຶກ" ໃນຕົວອ່ານ.
     */
    private function inline(string $pdf, string $filename): Response
    {
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    /**
     * ນັບກ່ອນສ້າງ — ດີກວ່າດຶງແຖວເປັນແສນເຂົ້າ memory ແລ້ວຈຶ່ງຕາຍກາງທາງ.
     *
     * ໜ້າຈໍປິດປຸ່ມພິມໄວ້ຢູ່ແລ້ວເມື່ອເກີນເພດານ ແຕ່ຍັງກວດຢູ່ນີ້ອີກ: URL ທີ່ເຊັນແລ້ວ
     * ຍັງໃຊ້ໄດ້ 30 ນາທີ ແລະ ຂໍ້ມູນອາດເພີ່ມຂຶ້ນໃນລະຫວ່າງນັ້ນ.
     */
    private function assertPrintable(int $rows): void
    {
        $max = (int) config('pdf.max_rows');

        if ($rows > $max) {
            throw new HttpException(422, sprintf(
                'ລາຍງານນີ້ມີ %s ລາຍການ ເກີນເພດານ %s ລາຍການຕໍ່ໄຟລ໌. ກະລຸນາແຄບຊ່ວງວັນທີ ຫຼື ເລືອກຜູ້ຈ່າຍ ແລ້ວລອງໃໝ່.',
                number_format($rows),
                number_format($max),
            ));
        }
    }
}
