/**
 * ຮູບແບບຂໍ້ມູນຂອງໜ້າຕັ້ງຄ່າ — ຕ້ອງກົງກັບ App\Http\Resources\*Resource.php
 *
 * ⚠️ ບໍ່ມີ `id` ຂອງອົງກອນ/ໂຄງການ: API ອ້າງອີງດ້ວຍ uuid ສະເໝີ (ກັນ enumeration).
 * ຜູ້ໃຊ້ຍັງໃຊ້ id ຢູ່ ເພາະຕາຕະລາງ users ບໍ່ມີ uuid.
 */

/** `store` = ຮ້ານ — ໜ່ວຍທີ່ບັນທຶກລາຍຮັບ-ລາຍຈ່າຍໄດ້ໂດຍກົງ */
export type CompanyType = 'internal' | 'branch' | 'third_party' | 'store';
export type CompanyPlan = 'trial' | 'active' | 'suspended';

/** ລະດັບສິດຕໍ່ໂຄງການ ຮຽງຈາກສູງຫາຕ່ຳ */
export type ProjectRole = 'full_access' | 'authorize' | 'modify' | 'read_only';

export const PROJECT_ROLES: ProjectRole[] = [
    'full_access',
    'authorize',
    'modify',
    'read_only',
];

export interface Company {
    uuid: string;
    parent_uuid?: string | null;
    depth: number;
    code: string;
    name: string;
    name_en: string | null;
    type: CompanyType;

    plan: CompanyPlan;
    trial_ends_at: string | null;
    access_expires_at: string | null;
    /** ອັນທີ່ມີຜົນຈິງຕາມ plan — ຄິດຢູ່ server, ຢ່າເລືອກເອງຈາກສອງ field ຂ້າງເທິງ */
    effective_expires_at: string | null;
    /** ຄິດຢູ່ຝັ່ງ server — ຫ້າມຄິດຄືນຈາກວັນທີຢູ່ browser (timezone ຕ່າງກັນ) */
    has_active_access: boolean;
    expires_in_days: number | null;

    tax_id: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    is_active: boolean;
    note: string | null;

    projects_count?: number;
    users_count?: number;
    children_count?: number;

    /**
     * ສິດຂອງຜູ້ໃຊ້ປັດຈຸບັນຕໍ່ແຖວນີ້ — ມາຈາກ CompanyPolicy.
     * ຫ້າມຄິດຄືນຈາກ `type` + `is_platform_admin`: ກົດຈິງຕ້ອງທຽບ `path`
     * ກັບອົງກອນຂອງຜູ້ໃຊ້ ເຊິ່ງ SPA ບໍ່ມີຂໍ້ມູນນັ້ນ.
     */
    can?: {
        update: boolean;
        delete: boolean;
    };
}

export interface Money {
    minor: number;
    decimal: string;
    currency: string;
    decimals: number;
}

/**
 * ສະກຸນເງິນ (ISO-4217). `code` ຄື primary key — ບໍ່ມີ id.
 *
 * ⚠️ `decimal_places` ຕາຍຕົວຫຼັງສ້າງແລ້ວ: ມັນຄືຄວາມໝາຍຂອງທຸກຄ່າ minor ທີ່ເກັບ
 * ໄວ້ແລ້ວ (LAK 0 → 12,500,000 = ກີບ). ຟອມແກ້ໄຂຈຶ່ງ disable ຊ່ອງນີ້.
 */
export interface Currency {
    code: string;
    name_lo: string;
    name_en: string;
    symbol: string;
    decimal_places: number;
    is_active: boolean;
    sort_order: number;
    /** ຈຳນວນແຖວທີ່ອ້າງເຖິງ (ລວມອັດຕາແລກປ່ຽນ) — ມີສະເພາະເມື່ອຂໍ `with_usage` */
    usage_count?: number;
}

/** ແຫຼ່ງທີ່ມາຂອງອັດຕາ — ຕ້ອງກົງກັບ enum ໃນ migration */
export type ExchangeRateSource = 'manual' | 'bcel' | 'bol' | 'api';

/**
 * ອັດຕາແລກປ່ຽນອ້າງອີງ. `rate_per_usd` = "ຈຳນວນສະກຸນຕໍ່ 1 USD" ສະເໝີ (ສິ່ງທີ່ເກັບ).
 *
 * ໜ້າຈໍໃຊ້ `rate_lak` = ກີບຕໍ່ 1 `quote_code`: ແຖວ LAK ຄື "1 USD = 22,239 ກີບ",
 * ແຖວ THB ຄື "1 THB = 675.48 ກີບ". null = ແຖວເກົ່າທີ່ຄິດເປັນກີບບໍ່ໄດ້.
 *
 * ⚠️ ເປັນ string ບໍ່ແມ່ນ number: ຄໍລຳເປັນ decimal(24,10) ແລະ 21760 ກັບ 32.22
 * ຢູ່ຄໍລຳດຽວກັນ — JSON number ຈະເສຍ precision.
 */
export interface ExchangeRate {
    id: number;
    currency_code: string;
    effective_date: string;
    rate_per_usd: string;
    rate_lak: string | null;
    quote_code: string;
    source: ExchangeRateSource;
    created_by_name?: string | null;
    created_at: string | null;
}

/** ຄຳຕອບຂອງ /exchange-rates/lookup — ໃຊ້ບອກວ່າ "ມື້ນີ້ຕັ້ງແລ້ວບໍ" */
export interface RateLookup {
    /** ຈຳນວນສະກຸນຕໍ່ 1 USD */
    rate: string | null;
    /** ກີບຕໍ່ 1 `quote_code` — ສິ່ງທີ່ສະແດງ ແລະ ເຕີມໃສ່ຟອມ */
    rate_lak?: string | null;
    quote_code?: string;
    currency: string;
    /** ວັນທີຂອງແຖວທີ່ຖືກໃຊ້ຈິງ — ຕ່າງຈາກມື້ນີ້ = ຍັງໃຊ້ອັດຕາເກົ່າຢູ່ */
    effective_date: string | null;
}

/**
 * ໝວດລາຍຈ່າຍ — ຮອງຮັບແມ່-ລູກຊັ້ນດຽວ.
 *
 * ໃຊ້ `id` ບໍ່ແມ່ນ uuid: ຕາຕະລາງນີ້ບໍ່ມີ uuid ແລະ ບໍ່ແມ່ນຂໍ້ມູນລັບ.
 */
export interface ExpenseCategory {
    id: number;
    parent_id: number | null;
    code: string;
    name_lo: string;
    name_en: string | null;
    color: string;
    icon: string | null;
    is_active: boolean;
    sort_order: number;
    /** ມີຄ່າສະເພາະຕອນດຶງລາຍການ — ໃຊ້ຕັດສິນວ່າລຶບໄດ້ບໍ */
    expenses_count?: number;
    children_count?: number;
}

/** ຜູ້ຈ່າຍ / ແຫຼ່ງທຶນ — ອອກເງິນໃຫ້ລາຍຈ່າຍ ຫຼື ເປັນຜູ້ສົ່ງລາຍຮັບເຂົ້າມາ */
export interface Payer {
    id: number;
    name: string;
    type: 'company' | 'individual' | null;
    contact_phone: string | null;
    note: string | null;
    is_active: boolean;
    expenses_count?: number;
    revenues_count?: number;
}

/**
 * ຜູ້ຮັບເງິນ (ຮ້ານຄ້າ/ບຸກຄົນ/ລັດ).
 *
 * ⚠️ ບໍ່ມີ `bank_account_no`: ເລກເຕັມບໍ່ເຄີຍອອກຈາກ server — ມີແຕ່ຄ່າທີ່ mask
 * ແລ້ວ. ຟອມແກ້ໄຂຈຶ່ງເຕີມເລກເກົ່າຄືນບໍ່ໄດ້ (ເບິ່ງ Payees.vue).
 */
export interface Payee {
    id: number;
    name: string;
    type: 'company' | 'individual' | 'government' | null;
    phone: string | null;
    bank_name: string | null;
    bank_account_no_masked: string | null;
    tax_id: string | null;
    note: string | null;
    is_active: boolean;
    expenses_count?: number;
}

/**
 * ທະນາຄານ — ຂໍ້ມູນຫຼັກ ພ້ອມໂລໂກ້ 1 ຮູບຕໍ່ 1 ແຖວ.
 *
 * ⚠️ ບໍ່ມີ `logo_path`: path ບໍ່ເຄີຍອອກຈາກ server. `logo_url` ຄື signed URL
 * ອາຍຸ 24 ຊົ່ວໂມງ ທີ່ຖືກສ້າງໃໝ່ທຸກເທື່ອທີ່ດຶງລາຍການ.
 */
export interface Bank {
    id: number;
    code: string;
    name_lo: string;
    name_en: string | null;
    short_name: string | null;
    swift_code: string | null;
    note: string | null;
    is_active: boolean;
    sort_order: number;
    /** ຕ່າງຈາກ `logo_url !== null`: ບອກວ່າ "ມີໄຟລ໌ຢູ່" ບໍ່ແມ່ນ "URL ຍັງໃຊ້ໄດ້" */
    has_logo: boolean;
    logo_url: string | null;
    logo_updated_at: string | null;
    /** ມີຄ່າສະເພາະຕອນຂໍ `with_usage` — ໃຊ້ຕັດສິນວ່າລຶບໄດ້ບໍ */
    expenses_count?: number;
    revenues_count?: number;
}

/**
 * ບັນຊີເຂົ້າໃຊ້ລະບົບ — ສະເພາະຜູ້ດູແລລະບົບ.
 *
 * ⚠️ **ບໍ່ມີ `password`** ແລະ ຈະບໍ່ມີວັນມີ: ຄ່າຈິງມາຈາກ
 * POST /credentials/{uuid}/reveal ເທົ່ານັ້ນ ແລະ ຖືກເກັບໄວ້ໃນ component
 * ຊົ່ວຄາວ — ຫ້າມຂຽນກັບຄືນໃສ່ object ນີ້ ບໍ່ດັ່ງນັ້ນລະຫັດຈະຄ້າງຢູ່ໜ່ວຍ
 * ຄວາມຈຳຂອງໜ້າຕະຫຼອດເວລາທີ່ເປີດຢູ່ (ແລະ ໂຜ່ອອກໃນ Vue devtools).
 */
export interface Credential {
    uuid: string;
    title: string;
    url_link: string | null;
    username: string | null;
    /** ຄ່າແທນຄົງທີ່ຈາກ server — ບໍ່ໄດ້ບອກຄວາມຍາວຂອງລະຫັດຈິງ */
    password_masked: string;
    note: string | null;
    is_active: boolean;
    sort_order: number;
    created_by_name?: string | null;
    created_at: string | null;
    /** null = ຍັງບໍ່ມີໃຜເປີດເບິ່ງ; ບໍ່ມີ key = ບໍ່ໄດ້ຂໍມາ */
    last_revealed_at?: string | null;
}

export interface Project {
    uuid: string;
    code: string;
    name: string;
    description: string | null;
    location: string | null;
    capacity_kw: number | null;
    status: 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled';
    start_date: string | null;
    end_date: string | null;
    budget: Money;
    company?: Company;
    members_count?: number;
    expenses_count?: number;
    my_role: ProjectRole | null;
}

export interface UserAccess {
    can_access: boolean;
    plan: CompanyPlan | null;
    /** ວັນທີທີ່ມີຜົນຈິງ — ອັນທີ່ມາຮອດກ່ອນລະຫວ່າງອົງກອນ ກັບ ບັນຊີ */
    expires_at: string | null;
    expires_in_days: number | null;
    /** ວັນທີທີ່ຕັ້ງໄວ້ຕໍ່ບັນຊີນີ້ໂດຍກົງ — null = ບໍ່ໄດ້ຈຳກັດລາຍຄົນ */
    user_expires_at: string | null;
    /** ວັນທີຂອງອົງກອນ — ເພດານທີ່ບັນຊີຢືດເກີນບໍ່ໄດ້ */
    company_expires_at: string | null;
}

export interface AppUser {
    id: number;
    name: string;
    email: string;
    /** signed URL ອາຍຸ 24 ຊົ່ວໂມງ — null = ບໍ່ມີຮູບ (ສະແດງຕົວອັກສອນຫຍໍ້ແທນ) */
    avatar_url?: string | null;
    is_platform_admin: boolean;
    is_active: boolean;
    last_login_at: string | null;
    created_at: string | null;
    /** ແກ້ຂໍ້ມູນຫຼັກ (ໝວດ, ຜູ້ຈ່າຍ, ຜູ້ຮັບເງິນ) ໄດ້ບໍ — ຈາກ MasterDataPolicy */
    can_manage_master_data?: boolean;
    company?: Company;
    access: UserAccess;
    memberships?: ProjectMember[];
}

export interface ProjectMember {
    role: ProjectRole;
    user?: Pick<AppUser, 'id' | 'name' | 'email' | 'is_active'>;
    project?: Pick<Project, 'uuid' | 'code' | 'name'>;
    assigned_at: string | null;
}

/** ຮູບແບບ paginate ມາດຕະຖານຂອງ Laravel resource collection */
export interface Paginated<T> {
    data: T[];
    meta?: PageMeta;
}

/**
 * ສ່ວນ `meta` ຂອງ Laravel paginator.
 *
 * `from`/`to` ເປັນ null ເມື່ອໜ້ານັ້ນບໍ່ມີແຖວເລີຍ (ຕາຕະລາງຫວ່າງ ຫຼື ຂໍໜ້າ
 * ທີ່ເກີນຈຳນວນທີ່ມີ) — ຢ່າຄິດວ່າມັນເປັນຕົວເລກສະເໝີ.
 */
export interface PageMeta {
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
    from: number | null;
    to: number | null;
}
