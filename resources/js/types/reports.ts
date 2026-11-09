import type { LedgerEntry, LedgerTotals } from '@/types/ledger';
import type { Money } from '@/types/settings';
import type { Expense, Revenue } from '@/types/transactions';

/** ຮູບແບບຂໍ້ມູນລາຍງານ — ຕ້ອງກົງກັບ ReportController */

export interface ReportStatusTotal {
    status: 'draft' | 'submitted' | 'approved' | 'rejected';
    count: number;
    total_usd: Money;
}

export interface ReportSummary {
    item_count: number;
    total_usd: Money;
    total_lak: Money;
    missing_evidence_count: number;
    by_status: ReportStatusTotal[];
}

export interface PayerBreakdown {
    payer_id: number | null;
    label: string;
    count: number;
    usd: Money;
    lak: Money;
    percentage: number;
}

/**
 * ສະຫຼຸບຕາມໝວດ — ຄືນຈາກ `reports/by-category`.
 *
 * `color` ມາຈາກ `expense_categories.color` ໃນຖານຂໍ້ມູນ ບໍ່ແມ່ນ hardcode ຢູ່ Vue
 * (docs/architecture.md ພາກ 2.3) — ສີຈຶ່ງຕິດກັບໝວດ ບໍ່ແມ່ນຕິດກັບລຳດັບ:
 * ກັ່ນຕອງແລ້ວໝວດທີ່ຍັງເຫຼືອບໍ່ປ່ຽນສີ.
 */
export interface CategoryBreakdown {
    category_id: number | null;
    label: string;
    color: string;
    count: number;
    usd: Money;
    lak: Money;
    percentage: number;
}

/**
 * ແຖວດຽວຂອງວົງກົມສະຫຼຸບ — ຮູບແບບກາງທີ່ທັງ CategoryBreakdown (ຕາມໝວດ) ແລະ
 * PayerBreakdown (ຕາມແຫຼ່ງທຶນ) ຖືກແປງມາໃສ່ ກ່ອນສົ່ງໃຫ້ BreakdownDonut.
 *
 * ມີຢູ່ເພື່ອໃຫ້ວົງກົມບໍ່ຕ້ອງຮູ້ຈັກວ່າຂໍ້ມູນມາຈາກໝວດ ຫຼື ຈາກແຫຼ່ງທຶນ.
 */
export interface BreakdownRow {
    label: string;
    /** ສີຈາກຖານຂໍ້ມູນ (ໝວດມີ). null = ໃຫ້ວົງກົມເລືອກຈາກຈານສີຕາມ `seed` */
    color: string | null;
    /**
     * ຕົວກຳນົດສີທີ່ໝັ້ນຄົງຕໍ່ entity (id ຂອງໝວດ/ແຫຼ່ງທຶນ) — ບໍ່ແມ່ນລຳດັບ
     * ໃນ array. ຄ່າຕິດລົບ = ບໍ່ໄດ້ລະບຸ → ສີເທົາສະຫງວນ. ເບິ່ງ charts/palette.ts
     */
    seed: number;
    count: number;
    usd: Money;
    percentage: number;
}

/**
 * ລາຍງານສະບັບເຕັມ — ສ່ວນສະຫຼຸບ ແລະ ສ່ວນລະອຽດ ມາຈາກ request ດຽວ ຈຶ່ງບໍ່ມີທາງ
 * ທີ່ຍອດລວມກັບແຖວທີ່ສະແດງຈະມາຈາກຕົວກັ່ນຕອງຄົນລະຊຸດ.
 *
 * `rows` ຫວ່າງເປົ່າເມື່ອ row_count > max_rows — ເບິ່ງ ReportController.
 */
interface BaseReport {
    summary: ReportSummary;
    by_payer: PayerBreakdown[];
    row_count: number;
    max_rows: number;
    /**
     * URL ທີ່ເຊັນແລ້ວສຳລັບເປີດ PDF ໃນແທັບໃໝ່ — null = ພິມບໍ່ໄດ້
     * (ບໍ່ມີແຖວ ຫຼື ເກີນເພດານ). ຫ້າມປະກອບ URL ນີ້ເອງຢູ່ browser:
     * ເສັ້ນທາງ PDF ພິສູດສິດດ້ວຍລາຍເຊັນ ບໍ່ແມ່ນ session (ເບິ່ງ ReportPdfLink).
     */
    pdf_url: string | null;
}

export interface ExpenseReport extends BaseReport {
    expenses: Expense[];
}

export interface RevenueReport extends BaseReport {
    revenues: Revenue[];
}

/** ສະຫຼຸບຕໍ່ເດືອນຂອງລາຍງານລາຍຮັບ-ລາຍຈ່າຍ */
export interface LedgerMonth {
    /** "YYYY-MM" */
    month: string;
    count: number;
    revenue_usd: Money;
    expense_usd: Money;
    net_usd: Money;
    revenue_lak: Money;
    expense_lak: Money;
    net_lak: Money;
    /** ຍອດຄົງເຫຼືອທ້າຍເດືອນ — ກົງກັບ balance ຂອງແຖວສຸດທ້າຍຂອງເດືອນນັ້ນ */
    balance_usd: Money;
    balance_lak: Money;
}

export interface LedgerReport {
    entries: LedgerEntry[];
    monthly: LedgerMonth[];
    /** null ເມື່ອ row_count > max_rows — ບໍ່ໄດ້ດຶງແຖວມາຄິດເລີຍ */
    totals: LedgerTotals | null;
    row_count: number;
    max_rows: number;
    /** ເບິ່ງ BaseReport.pdf_url */
    pdf_url: string | null;
}
