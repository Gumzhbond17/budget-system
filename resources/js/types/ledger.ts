import type { Money } from '@/types/settings';

/** ຮູບແບບຂໍ້ມູນຂອງບັນຊີແຍກປະເພດ — ຕ້ອງກົງກັບ App\Services\Reports\GeneralLedgerService */

export type LedgerEntryType = 'revenue' | 'expense';

export type EntryStatus = 'draft' | 'submitted' | 'approved' | 'rejected';

export interface LedgerEntry {
    uuid: string;
    doc_no: string | null;
    date: string;
    title: string;
    type: LedgerEntryType;
    status: EntryStatus;
    /** ຜູ້ຈ່າຍ (ລາຍຮັບ) ຫຼື ຜູ້ຮັບເງິນ (ລາຍຈ່າຍ) */
    counterparty: string | null;

    /** ຈຳນວນເດີມໃນສະກຸນທີ່ບັນທຶກ */
    amount: Money;
    amount_usd: Money;
    amount_lak: Money;

    /** ຍອດຄົງເຫຼືອຫຼັງແຖວນີ້ — ຄິດຢູ່ server ຫ້າມສະສົມເອງຢູ່ browser */
    balance_usd: Money;
    balance_lak: Money;
}

export interface LedgerTotals {
    entry_count: number;
    revenue_usd: Money;
    expense_usd: Money;
    revenue_lak: Money;
    expense_lak: Money;
    /** ລາຍຮັບ − ລາຍຈ່າຍ. ຕິດລົບ = ຈ່າຍເກີນເງິນທີ່ຮັບມາ */
    balance_usd: Money;
    balance_lak: Money;
    /** null = ຍັງບໍ່ໄດ້ຕັ້ງງົບ ຫຼື ຕັ້ງໄວ້ເປັນສະກຸນອື່ນ — ໃຊ້ `budget_currency` ແຍກ */
    budget_usd: Money | null;
    budget_remaining_usd: Money | null;
    budget_lak: Money | null;
    budget_remaining_lak: Money | null;
    /** ສະກຸນທີ່ງົບຕັ້ງໄວ້ — null = ຍັງບໍ່ໄດ້ຕັ້ງງົບເລີຍ */
    budget_currency: string | null;
}

export interface Ledger {
    entries: LedgerEntry[];
    totals: LedgerTotals;
}
