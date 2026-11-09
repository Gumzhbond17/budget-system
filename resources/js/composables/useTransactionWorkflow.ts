import { computed, ref, shallowRef } from 'vue';
import { api, toApiError } from '@/api/client';
import type { ApiError } from '@/api/client';
import type { EntryPermissions, Expense, Revenue } from '@/types/transactions';

/**
 * ຂັ້ນຕອນອະນຸມັດຂອງທຸລະກຳ — ໃຊ້ຮ່ວມກັນລະຫວ່າງລາຍຮັບ ແລະ ລາຍຈ່າຍ.
 *
 *   ຮ່າງ ──submit──→ ສົ່ງແລ້ວ ──approve──→ ອະນຸມັດແລ້ວ (ລັອກ)
 *                        │                       │
 *                        └──reject──→ ປະຕິເສດ ←──reopen (ຜູ້ດູແລລະບົບ)
 *                                       │
 *                                       └──submit──→ ສົ່ງແລ້ວ
 *
 * ໜ້າຕ່າງຢືນຢັນມີ **ສະເພາະ** approve, reject ແລະ reopen:
 *   - approve ລັອກລາຍການ (`Expense::isLocked()`) — ມີແຕ່ຜູ້ດູແລລະບົບທີ່ປົດໄດ້.
 *     ຄຳຖາມ 1 ຄັ້ງຖືກກວ່າ.
 *   - reject / reopen ຕ້ອງມີເຫດຜົນ (server ບັງຄັບ 3–2000 ຕົວອັກສອນ) ຈຶ່ງຕ້ອງມີບ່ອນພິມ.
 *   - submit ຍ້ອນຄືນໄດ້ (ຜູ້ອະນຸມັດປະຕິເສດແລ້ວກັບມາແກ້ໄດ້) — ກົດແລ້ວໄປເລີຍ.
 */
export type WorkflowAction = 'submit' | 'approve' | 'reject' | 'reopen';

/** ການກະທຳທີ່ຕ້ອງຜ່ານໜ້າຕ່າງຢືນຢັນ */
export type DialogAction = Exclude<WorkflowAction, 'submit'>;

/**
 * ແຖວທີ່ຜ່ານຂັ້ນຕອນອະນຸມັດໄດ້.
 *
 * export ໄວ້ເພາະຖັນປຸ່ມຢູ່ຕາຕະລາງ (`TransactionActionsCell`) ໃຊ້ຮ່ວມກັນ
 * ລະຫວ່າງລາຍຮັບ ແລະ ລາຍຈ່າຍ ຈຶ່ງຕ້ອງອ້າງເຖິງ union ອັນດຽວກັນນີ້.
 */
export type WorkflowEntry = Expense | Revenue;

/**
 * ສິ່ງທີ່ນ້ອຍທີ່ສຸດທີ່ composable ນີ້ຕ້ອງການຈິງ — `uuid` ສຳລັບຍິງ request
 * ແລະ `can` ສຳລັບຕັດສິນວ່າມີປຸ່ມໃຫ້ກົດບໍ.
 *
 * ປະກາດເປັນໂຄງສ້າງ ບໍ່ແມ່ນ `Expense | Revenue` ເພາະຄິວລໍຖ້າອະນຸມັດ
 * (`PendingApproval`) ໃຊ້ຂັ້ນຕອນອັນດຽວກັນ ແຕ່ມີ field ໜ້ອຍກວ່າ — ບັງຄັບ
 * ໃຫ້ມັນມີຄົບທຸກ field ຂອງທຸລະກຳເຕັມພຽງເພື່ອກົດປຸ່ມ ແມ່ນເກີນຄວາມຈຳເປັນ.
 */
export interface WorkflowCapable {
    uuid: string;
    can: EntryPermissions;
}

type Entry = WorkflowCapable;

/**
 * ມີປຸ່ມຂັ້ນຕອນອະນຸມັດໃຫ້ກົດບໍ ສຳລັບຜູ້ໃຊ້ປັດຈຸບັນ.
 *
 * ໃຊ້ຕັດສິນວ່າຈະສະແດງ **ກອບ** ທີ່ຫຸ້ມປຸ່ມບໍ (ແຖບທ້າຍໜ້າຕ່າງລາຍລະອຽດ) —
 * ຕົວປຸ່ມເອງ WorkflowActions ຕັດສິນເປັນອັນໆດ້ວຍ `can` ອັນດຽວກັນ.
 */
export function hasWorkflowActions(entry: Entry | null): boolean {
    if (entry === null) {
        return false;
    }

    return (
        entry.can.submit ||
        entry.can.approve ||
        entry.can.reject ||
        entry.can.reopen
    );
}

interface Options<T extends Entry> {
    /** ເອົາແຖວທີ່ server ຕອບກັບມາໄປທັບແຖວເກົ່າໃນຕາຕະລາງ */
    onUpdated: (entry: T) => void;
}

export function useTransactionWorkflow<T extends Entry>(
    resource: 'expenses' | 'revenues',
    options: Options<T>,
) {
    /** uuid ຂອງແຖວທີ່ກຳລັງສົ່ງ request — ໃຊ້ປິດປຸ່ມສະເພາະແຖວນັ້ນ */
    const pending = ref<string | null>(null);

    /**
     * null = ບໍ່ມີໜ້າຕ່າງຢືນຢັນເປີດຢູ່.
     *
     * `shallowRef` ບໍ່ແມ່ນ `ref`: ນອກຈາກຈະຖືກກວ່າ (ບໍ່ຕ້ອງ reactive ເລິກທຸກ
     * ຊັ້ນຂອງແຖວທີ່ອ່ານຢ່າງດຽວ) ຍັງຮັກສາ type `T` ໄວ້ຄືເກົ່າ — `ref` ຈະຫໍ່
     * ດ້ວຍ `UnwrapRef<T>` ແລ້ວ TypeScript ຈະບໍ່ຮັບຄືນເປັນ `T` ອີກ
     */
    const dialog = shallowRef<{
        action: DialogAction;
        entry: T;
    } | null>(null);

    const error = ref<ApiError | null>(null);

    const saving = computed(() => pending.value !== null);

    async function run(
        action: WorkflowAction,
        entry: T,
        payload?: Record<string, string>,
    ): Promise<boolean> {
        pending.value = entry.uuid;
        error.value = null;

        try {
            const { data } = await api.post<{ data: T }>(
                `/${resource}/${entry.uuid}/${action}`,
                payload,
            );

            options.onUpdated(data.data);

            return true;
        } catch (e) {
            error.value = toApiError(e);

            return false;
        } finally {
            pending.value = null;
        }
    }

    /**
     * ສົ່ງອະນຸມັດ — ບໍ່ຖາມຢືນຢັນ (ຍ້ອນຄືນໄດ້).
     *
     * @returns true ເມື່ອສຳເລັດ — ຜູ້ເອີ້ນເອົາໄປຕັດສິນວ່າຈະແຈ້ງ toast ບໍ
     */
    function submit(entry: T): Promise<boolean> {
        return run('submit', entry);
    }

    function askApprove(entry: T): void {
        error.value = null;
        dialog.value = { action: 'approve', entry };
    }

    function askReject(entry: T): void {
        error.value = null;
        dialog.value = { action: 'reject', entry };
    }

    /** ຍົກເລີກການອະນຸມັດ — ຖາມເຫດຜົນ ເພາະຜູ້ບັນທຶກຕ້ອງຮູ້ວ່າໃຫ້ແກ້ຫຍັງ */
    function askReopen(entry: T): void {
        error.value = null;
        dialog.value = { action: 'reopen', entry };
    }

    function closeDialog(): void {
        dialog.value = null;
        error.value = null;
    }

    /**
     * ຢືນຢັນສິ່ງທີ່ຢູ່ໃນໜ້າຕ່າງ. ປິດໜ້າຕ່າງສະເພາະຕອນສຳເລັດ — ຖ້າລົ້ມ
     * (ເຊັ່ນ ຄົນອື່ນອະນຸມັດຕັດໜ້າໄປແລ້ວ → 422) ຄ້າງໄວ້ພ້ອມຂໍ້ຄວາມ ຜູ້ໃຊ້
     * ຈຶ່ງເຫັນວ່າເປັນຫຍັງ ແທນທີ່ໜ້າຕ່າງຈະຫາຍໄປງຽບໆ.
     */
    async function confirmDialog(reason?: string): Promise<T | null> {
        const current = dialog.value;

        if (current === null) {
            return null;
        }

        const ok = await run(
            current.action,
            current.entry,
            current.action === 'approve' ? undefined : { reason: reason ?? '' },
        );

        if (!ok) {
            return null;
        }

        const done = current.entry;
        dialog.value = null;

        return done;
    }

    return {
        pending,
        dialog,
        error,
        saving,
        submit,
        askApprove,
        askReject,
        askReopen,
        closeDialog,
        confirmDialog,
    };
}
