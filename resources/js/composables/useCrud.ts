import { ref } from 'vue';
import type { Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { api, toApiError } from '@/api/client';
import type { ApiError } from '@/api/client';
import { toastSuccess } from '@/utils/toast';

interface CrudOptions<T> {
    /**
     * ຊື່ຂອງແຖວ ສຳລັບເປັນບັນທັດທີສອງຂອງຂໍ້ຄວາມແຈ້ງຜົນ.
     *
     * ມີເພື່ອໃຫ້ toast ບອກໄດ້ວ່າ **ແຖວໃດ** ຖືກບັນທຶກ ບໍ່ແມ່ນແຕ່ "ບັນທຶກແລ້ວ"
     * ລ້າໆ — ສຳຄັນຕອນເປີດຫຼາຍແຖວແກ້ຕິດກັນ. ບໍ່ສົ່ງມາ = ສະແດງແຕ່ຫົວຂໍ້.
     */
    describe?: (item: T) => string | null | undefined;
}

/**
 * ຕັກກະລະບົບ CRUD ທີ່ໜ້າຕັ້ງຄ່າທັງໝົດໃຊ້ຮ່ວມກັນ.
 *
 * ເປັນຫຍັງບໍ່ໃຊ້ Pinia store ຕໍ່ 1 ຊະນິດ: ຂໍ້ມູນເຫຼົ່ານີ້ບໍ່ໄດ້ຖືກໃຊ້ຮ່ວມກັນ
 * ຂ້າມໜ້າ ແລະ ບໍ່ຕ້ອງຄ້າງໄວ້ຫຼັງອອກຈາກໜ້າ — store ຈະກາຍເປັນ cache ທີ່ເກົ່າ
 * ໂດຍບໍ່ມີໃຜລ້າງ. composable ຕາຍພ້ອມ component ພໍດີ.
 *
 * ⚠️ ຕ້ອງເອີ້ນໃນ `<script setup>` ເທົ່ານັ້ນ — ມັນເອີ້ນ `useI18n()` ຢູ່ພາຍໃນ
 * ເພື່ອແປຂໍ້ຄວາມ toast ຕອນບັນທຶກສຳເລັດ.
 */
export function useCrud<T extends Record<string, unknown>>(
    endpoint: string,
    /** ຄີທີ່ໃຊ້ໃນ URL — ອົງກອນ/ໂຄງການໃຊ້ uuid, ຜູ້ໃຊ້ໃຊ້ id */
    idKey: keyof T = 'uuid' as keyof T,
    options: CrudOptions<T> = {},
) {
    const { t } = useI18n();

    const items = ref<T[]>([]) as Ref<T[]>;
    const loading = ref(false);
    const saving = ref(false);
    const error = ref<ApiError | null>(null);

    /**
     * ຈຳນວນທັງໝົດຢູ່ server. endpoint ທີ່ແບ່ງໜ້າ (payees) ຄືນ `meta.total` —
     * ອັນອື່ນຄືນທຸກແຖວ ຈຶ່ງເທົ່າກັບຈຳນວນທີ່ໄດ້ມາ. ໜ້າຈໍໃຊ້ບອກຜູ້ໃຊ້ວ່າຍັງມີ
     * ອີກທີ່ບໍ່ໄດ້ສະແດງ ແທນທີ່ຈະຕັດຖິ້ມແບບງຽບໆ.
     */
    const total = ref(0);

    function idOf(item: T): string {
        return String(item[idKey]);
    }

    /** ຕົວກອງຂອງການດຶງເທື່ອຫຼ້າສຸດ — `refresh()` ໃຊ້ຊ້ຳເພື່ອບໍ່ໃຫ້ການໂຫຼດ
     *  ຄືນອັດຕະໂນມັດລືມຄຳຄົ້ນຫາ/ຕົວກອງທີ່ຜູ້ໃຊ້ຕັ້ງໄວ້ */
    let lastParams: Record<string, string | number | boolean> = {};

    /**
     * ລຳດັບຂອງການດຶງ — ຄຳຕອບຂອງ request ທີ່ເກົ່າກວ່າອັນຫຼ້າສຸດຖືກຖິ້ມ.
     *
     * ຂາດບໍ່ໄດ້ເມື່ອດຶງຄືນຫຼັງບັນທຶກ: ຮອບ 30 ວິນາທີທີ່ຍິງອອກໄປ **ກ່ອນ** ບັນທຶກ
     * ອາດກັບມາ **ຫຼັງ** ແລ້ວທັບແຖວທີ່ຫາກໍແກ້ດ້ວຍຂໍ້ມູນເກົ່າ. ການແກ້ແຖວຢູ່ເຄື່ອງ
     * (`replace`) ກໍນັບເປັນການປ່ຽນ — ຄຳຕອບທີ່ຄ້າງຢູ່ກ່ອນໜ້ານັ້ນໃຊ້ບໍ່ໄດ້ອີກ.
     */
    let fetchSeq = 0;

    async function fetchAll(
        params: Record<string, string | number | boolean> = {},
        { silent = false }: { silent?: boolean } = {},
    ): Promise<void> {
        lastParams = params;
        const seq = ++fetchSeq;

        // ຮອບອັດຕະໂນມັດບໍ່ແຕະ `loading` — ບໍ່ດັ່ງນັ້ນຕາຕະລາງຈະຖືກແທນທີ່
        // ດ້ວຍຄຳວ່າ "ກຳລັງໂຫຼດ..." ທຸກ 30 ວິນາທີ ທັງທີ່ຂໍ້ມູນຢູ່ໜ້າຈໍແລ້ວ
        if (!silent) {
            loading.value = true;
            error.value = null;
        }

        try {
            const { data } = await api.get<{
                data: T[];
                meta?: { total?: number };
            }>(endpoint, { params });

            if (seq !== fetchSeq) {
                return;
            }

            items.value = data.data;
            total.value = data.meta?.total ?? data.data.length;
        } catch (e) {
            // ຮອບອັດຕະໂນມັດລົ້ມ → ຮັກສາແຖວເກົ່າໄວ້ ແລະ ງຽບໄວ້.
            // ເນັດສະດຸດເທື່ອດຽວບໍ່ຄວນລ້າງຕາຕະລາງທີ່ຜູ້ໃຊ້ກຳລັງອ່ານຢູ່ ຫຼື
            // ເດັ້ງແຖບແດງຂຶ້ນມາເອງ. ຄວາມຜິດພາດຈິງຈະປາກົດຕອນຜູ້ໃຊ້ລົງມືເອງ
            // (ຄົ້ນຫາ, ບັນທຶກ, ລຶບ) ເຊິ່ງເປັນຕອນທີ່ລາວລໍຄຳຕອບຢູ່ແລ້ວ.
            if (silent || seq !== fetchSeq) {
                return;
            }

            error.value = toApiError(e);
            items.value = [];
            total.value = 0;
        } finally {
            // ອັນຫຼ້າສຸດປິດ `loading` ສະເໝີ (ງຽບ ຫຼື ບໍ່ກໍຕາມ) — ຖ້າອັນທີ່ສະແດງ
            // "ກຳລັງໂຫຼດ" ຖືກຮອບງຽບແທນທີ່ ບໍ່ດັ່ງນັ້ນຈະຄ້າງໝຸນຕະຫຼອດໄປ
            if (seq === fetchSeq) {
                loading.value = false;
            }
        }
    }

    /** ດຶງຄືນແບບງຽບ ດ້ວຍຕົວກອງເດີມ — ໃຊ້ກັບ useAutoRefresh */
    function refresh(): Promise<void> {
        return fetchAll(lastParams, { silent: true });
    }

    /**
     * ສ້າງ ຫຼື ແກ້ໄຂ — ຄືນແຖວທີ່ບັນທຶກແລ້ວ, ຫຼື null ເມື່ອລົ້ມເຫຼວ.
     *
     * ຄືນແຖວ (ບໍ່ແມ່ນ boolean) ເພາະຫຼາຍໜ້າຕ້ອງເຮັດຕໍ່ຫຼັງບັນທຶກ ໂດຍໃຊ້ id
     * ຂອງແຖວທີ່ຫາກໍ່ສ້າງ — ເຊັ່ນ Banks.vue ທີ່ຕ້ອງສົ່ງໂລໂກ້ (multipart)
     * ໄປ endpoint ຕ່າງຫາກຫຼັງແຖວມີ id ແລ້ວ.
     *
     * ໜ້າອື່ນຂຽນວ່າ `if (await save(...))` ຢູ່ແລ້ວ ເຊິ່ງຍັງໃຊ້ໄດ້ຄືເກົ່າ:
     * object = truthy, null = falsy.
     *
     * ແກ້ແຖວໃນ array ທັນທີ (ບໍ່ກະພິບ, ບໍ່ເສຍຕຳແໜ່ງ scroll) ແລ້ວ**ດຶງລາຍການ
     * ຄືນແບບງຽບ**ຕາມຫຼັງ: ສິ່ງທີ່ server ຄິດ (ລຳດັບ, ຈຳນວນທີ່ໃຊ້, ສິດ `can`,
     * ແຖວອື່ນທີ່ປ່ຽນຕາມ ເຊັ່ນ ໝວດແມ່-ລູກ) ຈຶ່ງຖືກຕ້ອງທັນທີ ບໍ່ຕ້ອງລໍ 30 ວິນາທີ.
     */
    async function save(
        payload: Record<string, unknown>,
        existing: T | null = null,
    ): Promise<T | null> {
        saving.value = true;
        error.value = null;

        try {
            if (existing) {
                const { data } = await api.patch<{ data: T }>(
                    `${endpoint}/${idOf(existing)}`,
                    payload,
                );
                const index = items.value.findIndex(
                    (item) => idOf(item) === idOf(existing),
                );

                if (index !== -1) {
                    items.value[index] = data.data;
                }

                announce('updated', data.data);
                void refresh();

                return data.data;
            }

            const { data } = await api.post<{ data: T }>(endpoint, payload);
            items.value.unshift(data.data);

            announce('created', data.data);
            void refresh();

            return data.data;
        } catch (e) {
            error.value = toApiError(e);

            return null;
        } finally {
            saving.value = false;
        }
    }

    /**
     * ແຈ້ງຜົນສຳເລັດເປັນ toast.
     *
     * ຢູ່ໃນ composable ບໍ່ແມ່ນໃນແຕ່ລະໜ້າ ເພື່ອໃຫ້ທຸກໜ້າຕັ້ງຄ່າແຈ້ງຄືກັນໝົດ
     * ໂດຍບໍ່ມີໜ້າໃດລືມ — ແລະ ຍິງທັນທີຫຼັງ server ຕອບ ບໍ່ແມ່ນຫຼັງຟອມປິດ:
     * ບາງໜ້າ (Banks, Credentials) ຍັງເຮັດວຽກຕໍ່ຫຼັງບັນທຶກ ແລະ ອາດປະຟອມ
     * ເປີດໄວ້ຖ້າຂັ້ນຕອນນັ້ນລົ້ມ — ຕອນນັ້ນແຖວກໍຖືກບັນທຶກໄປແລ້ວຈິງ.
     */
    function announce(event: 'created' | 'updated', item: T): void {
        toastSuccess(
            t(`settings.${event}`),
            // null → undefined: Swal ຈະສະແດງຄຳວ່າ "null" ອອກມາຖ້າສົ່ງ null ໄປ
            options.describe?.(item) ?? undefined,
        );
    }

    /**
     * ທັບແຖວທີ່ມີຢູ່ແລ້ວດ້ວຍສະບັບໃໝ່ຈາກ server.
     *
     * ໃຊ້ຫຼັງ request ທີ່ບໍ່ໄດ້ຜ່ານ `save` ແຕ່ຄືນແຖວມາໃຫ້ຄືກັນ —
     * ເຊັ່ນ ການອັບ/ລຶບໂລໂກ້ ທີ່ເປັນ endpoint ຂອງຕົນເອງ.
     */
    function replace(item: T): void {
        const index = items.value.findIndex((row) => idOf(row) === idOf(item));

        if (index !== -1) {
            items.value[index] = item;
        }

        // ເຊັ່ນ Banks: ບັນທຶກ (ດຶງຄືນອັນທີ 1 ອອກໄປ) → ອັບໂລໂກ້ → replace.
        // ອັນທີ 1 ຍັງບໍ່ມີໂລໂກ້ — ດຶງໃໝ່ຢູ່ນີ້ໃຫ້ມັນກາຍເປັນຄຳຕອບທີ່ເກົ່າ ແລະ ຖືກຖິ້ມ
        void refresh();
    }

    async function remove(item: T): Promise<boolean> {
        saving.value = true;
        error.value = null;

        try {
            await api.delete(`${endpoint}/${idOf(item)}`);
            items.value = items.value.filter((row) => idOf(row) !== idOf(item));
            void refresh();

            return true;
        } catch (e) {
            error.value = toApiError(e);

            return false;
        } finally {
            saving.value = false;
        }
    }

    function clearError(): void {
        error.value = null;
    }

    return {
        items,
        total,
        loading,
        saving,
        error,
        fetchAll,
        refresh,
        save,
        replace,
        remove,
        clearError,
    };
}
