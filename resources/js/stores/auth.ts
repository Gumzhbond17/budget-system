import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import {
    api,
    ensureCsrfCookie,
    resetCsrfCookie,
    toApiError,
    web,
} from '@/api/client';
import type { ApiError } from '@/api/client';
import { useApprovalsStore } from '@/stores/approvals';
import type { AppUser } from '@/types/settings';

export type AuthUser = AppUser;

export const useAuthStore = defineStore('auth', () => {
    const user = ref<AuthUser | null>(null);
    const loading = ref(false);
    const error = ref<ApiError | null>(null);

    /** ຍັງບໍ່ຮູ້ວ່າ login ຢູ່ບໍ — router guard ຕ້ອງລໍຖ້າກ່ອນຕັດສິນ */
    const resolved = ref(false);

    const isAuthenticated = computed(() => user.value !== null);

    /** ຜູ້ດູແລລະບົບ — ຄົນດຽວທີ່ເຫັນເມນູ ອົງກອນ/ໂຄງການ/ຜູ້ໃຊ້ */
    const isPlatformAdmin = computed(
        () => user.value?.is_platform_admin === true,
    );

    /**
     * ອົງກອນຍັງບໍ່ໝົດອາຍຸບໍ — ຄິດຢູ່ຝັ່ງ server ແລ້ວສົ່ງມາ.
     * ຜູ້ໃຊ້ທີ່ຍັງບໍ່ໄດ້ login ຖືວ່າ "ຍັງບໍ່ຮູ້" ຈຶ່ງຄືນ true ເພື່ອບໍ່ໃຫ້
     * router ເຕະໄປໜ້າ "ໝົດອາຍຸ" ກ່ອນທີ່ຈະຮູ້ວ່າເປັນໃຜ.
     */
    const hasActiveAccess = computed(
        () => user.value?.access?.can_access !== false,
    );

    /** ເຫຼືອອີກຈັກວັນຈຶ່ງໝົດອາຍຸ — null = ບໍ່ໝົດອາຍຸ */
    const expiresInDays = computed(
        () => user.value?.access?.expires_in_days ?? null,
    );

    /**
     * ດຶງຜູ້ໃຊ້ປັດຈຸບັນຈາກ session cookie.
     * 401 ບໍ່ແມ່ນ error — ແປວ່າ "ຍັງບໍ່ login" ເທົ່ານັ້ນ.
     *
     * `/user` ຫໍ່ດ້ວຍ `data` ຄືກັບ resource ອື່ນ ແລະ ພ່ວງຂໍ້ມູນອົງກອນ +
     * ອາຍຸການໃຊ້ງານມານຳ ຈຶ່ງເປັນ endpoint ດຽວທີ່ SPA ຕ້ອງການຕອນບູດ.
     */
    async function fetchUser(): Promise<void> {
        try {
            const { data } = await api.get<{ data: AuthUser }>('/user');
            user.value = data.data;
        } catch {
            user.value = null;
        } finally {
            resolved.value = true;
        }
    }

    async function login(
        email: string,
        password: string,
        remember = false,
    ): Promise<boolean> {
        loading.value = true;
        error.value = null;

        try {
            await ensureCsrfCookie();
            await web.post<{ user: AuthUser }>('/login', {
                email,
                password,
                remember,
            });

            // ດຶງຜ່ານ /user ແທນທີ່ຈະໃຊ້ payload ຂອງ /login ໂດຍກົງ —
            // /login ຄືນຜູ້ໃຊ້ແບບຫຍໍ້ ບໍ່ມີອົງກອນ ແລະ ອາຍຸການໃຊ້ງານ
            await fetchUser();

            return user.value !== null;
        } catch (e) {
            error.value = toApiError(e);
            user.value = null;

            return false;
        } finally {
            loading.value = false;
        }
    }

    async function logout(): Promise<void> {
        loading.value = true;

        try {
            await web.post('/logout');
        } catch {
            // session ໝົດອາຍຸໄປແລ້ວກໍຖືວ່າອອກແລ້ວ — ບໍ່ຕ້ອງຂວາງຜູ້ໃຊ້
        } finally {
            user.value = null;
            error.value = null;
            resetCsrfCookie();
            loading.value = false;

            // ອອກຈາກລະບົບບໍ່ໄດ້ໂຫຼດໜ້າຄືນ — state ຂອງ store ອື່ນຍັງຄ້າງຢູ່.
            // ຄິວລໍຖ້າອະນຸມັດເປັນຂໍ້ມູນສ່ວນຕົວ: ຖ້າບໍ່ລ້າງ ຄົນຕໍ່ໄປທີ່ login
            // ໃນແທັບດຽວກັນຈະເຫັນເລກເທິງກະດິ່ງຂອງຄົນກ່ອນ ຈົນກວ່າຈະດຶງຮອບໃໝ່.
            // ເອີ້ນຢູ່ໃນຟັງຊັນ ບໍ່ແມ່ນລະດັບໄຟລ໌ — ກັນບັນຫາລຳດັບການສ້າງ store.
            useApprovalsStore().reset();
        }
    }

    function clearError(): void {
        error.value = null;
    }

    return {
        user,
        loading,
        error,
        resolved,
        isAuthenticated,
        isPlatformAdmin,
        hasActiveAccess,
        expiresInDays,
        fetchUser,
        login,
        logout,
        clearError,
    };
});
