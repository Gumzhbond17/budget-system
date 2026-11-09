import type { EntryStatus } from '@/types/ledger';
import type { Money } from '@/types/settings';

/** ຮູບແບບຂໍ້ມູນທຸລະກຳ — ຕ້ອງກົງກັບ ExpenseResource / RevenueResource / AttachmentResource */

export type EvidenceKind =
    | 'transfer_slip'
    | 'receipt'
    | 'invoice'
    | 'quotation'
    | 'contract'
    | 'photo'
    | 'other';

export interface Attachment {
    uuid: string;
    kind: EvidenceKind;
    original_name: string;
    mime_type: string;
    size_bytes: number;
    width: number | null;
    height: number | null;
    is_image: boolean;
    /** signed URL ໝົດອາຍຸໃນ 5 ນາທີ — ຫ້າມ cache ໄວ້ດົນ */
    url: string;
    thumb_url: string | null;
    url_expires_in: number;
    checksum_short: string;
    uploaded_at: string | null;
    uploader?: { id: number; name: string };
}

interface NamedRef {
    id: number;
    name: string;
}

/**
 * ສິດຂອງຜູ້ໃຊ້ປັດຈຸບັນຕໍ່ 1 ແຖວ — ຄຳນວນໂດຍ ExpensePolicy / RevenuePolicy
 * ແລ້ວສົ່ງມາກັບແຖວເລີຍ (ເບິ່ງ trait ExposesWorkflowPermissions).
 *
 * ຫ້າມສ້າງຄ່າເຫຼົ່ານີ້ຂຶ້ນເອງຢູ່ Vue ຈາກ `status` + `my_role`: ກົດ
 * "ຜູ້ສ້າງອະນຸມັດລາຍການຕົນເອງບໍ່ໄດ້" ຕ້ອງໃຊ້ `created_by` ທີ່ API ບໍ່ສົ່ງອອກ.
 */
export interface EntryPermissions {
    update: boolean;
    delete: boolean;
    submit: boolean;
    approve: boolean;
    reject: boolean;
    /** ຍົກເລີກການອະນຸມັດ ສົ່ງກັບໃຫ້ຜູ້ບັນທຶກແກ້ — ຜູ້ດູແລລະບົບເທົ່ານັ້ນ */
    reopen: boolean;
}

export interface Revenue {
    uuid: string;
    doc_no: string | null;
    received_date: string;
    title: string;
    amount: Money;
    amount_usd: Money;
    amount_lak: Money;
    fx_rate_per_usd: string;
    fx_rate_lak_per_usd: string;
    /** ອັດຕາເປັນກີບ: LAK = ກີບຕໍ່ 1 USD, ສະກຸນອື່ນ = ກີບຕໍ່ 1 ໜ່ວຍ */
    fx_rate_lak: string;
    status: EntryStatus;
    is_locked: boolean;
    approved_at: string | null;
    rejection_reason: string | null;
    can: EntryPermissions;
    reference: string | null;
    note: string | null;
    attachments_count: number;
    payer?: NamedRef | null;
    bank?: BankRef | null;
}

/**
 * ທະນາຄານທີ່ໂອນຜ່ານ — ຮູບຫຍໍ້ຂອງ `App\Models\Bank` ທີ່ຕິດມາກັບທຸລະກຳ.
 *
 * ບໍ່ມີ `logo_url`: ນັ້ນເປັນ signed URL ທີ່ຕ້ອງເຊັນໃໝ່ຕໍ່ 1 ແຖວ ເຊິ່ງແພງ
 * ໂດຍບໍ່ຈຳເປັນ — ຕາຕະລາງທຸລະກຳສະແດງແຕ່ຊື່ (ເບິ່ງ ExpenseResource).
 */
export interface BankRef {
    id: number;
    code: string;
    name_lo: string;
    short_name: string | null;
}

export interface Expense {
    uuid: string;
    doc_no: string | null;
    expense_date: string;
    title: string;
    description: string | null;
    unit: string;
    quantity: string;
    amount: Money;
    amount_usd: Money;
    amount_lak: Money;
    fx_rate_per_usd: string;
    fx_rate_lak_per_usd: string;
    /** ອັດຕາເປັນກີບ: LAK = ກີບຕໍ່ 1 USD, ສະກຸນອື່ນ = ກີບຕໍ່ 1 ໜ່ວຍ */
    fx_rate_lak: string;
    payment_method: string;
    payment_reference: string | null;
    paid_at: string | null;
    status: EntryStatus;
    is_locked: boolean;
    approved_at: string | null;
    rejection_reason: string | null;
    can: EntryPermissions;
    attachments_count: number;
    has_evidence: boolean;
    note: string | null;
    category?: {
        id: number;
        code: string;
        name_lo: string;
        color: string;
    } | null;
    payer?: NamedRef | null;
    payee?: NamedRef | null;
    bank?: BankRef | null;
}

export interface CurrencyOption {
    code: string;
    name_lo: string;
    decimal_places: number;
}

/**
 * ລາຍການທີ່ລໍຖ້າຜູ້ໃຊ້ປັດຈຸບັນອະນຸມັດ — ຄູ່ກັບ `PendingApprovalResource`.
 *
 * ບໍ່ແມ່ນ `Expense | Revenue`: ຮູບຮ່າງນີ້ຮຽບກວ່າ (ບໍ່ມີ fx rate, ຫຼັກຖານ,
 * ໝວດ) ແຕ່ມີສອງຢ່າງທີ່ສອງອັນນັ້ນບໍ່ມີ — ຊື່ຜູ້ສົ່ງ ແລະ ໂຄງການ — ເພາະ
 * ກະດິ່ງແຈ້ງເຕືອນສະແດງຂ້າມໂຄງການ.
 */
export interface PendingApproval {
    type: 'expense' | 'revenue';
    uuid: string;
    doc_no: string | null;
    title: string;
    /** ວັນທີຂອງລາຍການ — ລວມ `expense_date` ກັບ `received_date` ເປັນຊື່ດຽວ */
    date: string;
    amount: Money;
    amount_usd: Money;
    submitted_at: string | null;
    submitted_by: string | null;
    project?: {
        uuid: string;
        code: string;
        name: string;
    };
    attachments_count: number;
    can: EntryPermissions;
}
