<template>
    <AdminLayout>
        <SettingsPage
            v-model:search="search"
            title="settings.users.title"
            :icon="FormIcon"
            description="settings.users.description"
            :error="listError"
            :can-create="auth.isPlatformAdmin"
            create-label="settings.users.create"
            @create="openCreate"
        >
            <template #filters>
                <SearchableSelect
                    v-if="auth.isPlatformAdmin"
                    v-model="companyFilter"
                    variant="filter"
                    class="sm:w-64"
                    :aria-label="t('settings.users.filterByCompany')"
                    :options="companyOptions"
                    nullable
                    null-label="settings.users.allCompanies"
                />
            </template>

            <DataGrid
                :column-defs="columnDefs"
                :row-data="items"
                :loading="loading"
                :get-row-id="getRowId"
                :row-height="60"
                :empty-label="t('settings.empty')"
            />
        </SettingsPage>

        <FormModal
            :open="modalOpen"
            :icon="FormIcon"
            :title="editing ? 'settings.users.edit' : 'settings.users.create'"
            subtitle="settings.users.formHint"
            :saving="saving"
            :error="error"
            @close="closeModal"
            @submit="submit"
        >
            <div class="grid gap-5 sm:grid-cols-2">
                <TextField
                    v-model="form.name"
                    label="settings.users.name"
                    name="name"
                    :error="error"
                    required
                />
                <TextField
                    v-model="form.email"
                    type="email"
                    label="settings.users.email"
                    name="email"
                    :error="error"
                    required
                />

                <div class="sm:col-span-2">
                    <TextField
                        v-model="form.password"
                        type="password"
                        label="settings.users.password"
                        name="password"
                        :error="error"
                        :required="!editing"
                        :hint="
                            editing
                                ? 'settings.users.passwordEditHint'
                                : 'settings.users.passwordHint'
                        "
                    />
                </div>

                <div class="sm:col-span-2">
                    <SelectField
                        v-model="form.company_uuid"
                        label="settings.users.company"
                        name="company_uuid"
                        :error="error"
                        :options="companyOptions"
                        nullable
                        null-label="settings.users.noCompany"
                        hint="settings.users.companyHint"
                        :disabled="form.is_platform_admin"
                    />
                </div>

                <label class="flex items-start gap-3 sm:col-span-2">
                    <input
                        v-model="form.is_platform_admin"
                        type="checkbox"
                        class="mt-0.5 h-5 w-5 rounded border-gray-300 text-brand-500 focus:ring-brand-500/10 dark:border-gray-700"
                    />
                    <span>
                        <span
                            class="block text-sm text-gray-700 dark:text-gray-400"
                        >
                            {{ t('settings.users.isPlatformAdmin') }}
                        </span>
                        <span
                            class="block text-theme-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ t('settings.users.isPlatformAdminHint') }}
                        </span>
                    </span>
                </label>

                <!--
          ອາຍຸລາຍຄົນ — ຜູ້ດູແລລະບົບບໍ່ໝົດອາຍຸ ຈຶ່ງບໍ່ມີຫຍັງໃຫ້ຕັ້ງ.
          ເຊື່ອງໄປເລີຍດີກວ່າສະແດງຊ່ອງທີ່ບັນທຶກແລ້ວບໍ່ມີຜົນ.
        -->
                <div v-if="!form.is_platform_admin" class="sm:col-span-2">
                    <DatePicker
                        v-model="form.access_expires_at"
                        label="settings.users.expiresAt"
                        name="access_expires_at"
                        :error="error"
                        hint="settings.users.expiresAtHint"
                    />

                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <button
                            v-for="days in EXTEND_PRESETS"
                            :key="days"
                            type="button"
                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-theme-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-white/5"
                            @click="extendBy(days)"
                        >
                            {{ t('settings.users.extendBy', { days }) }}
                        </button>

                        <button
                            v-if="form.access_expires_at"
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-theme-xs font-medium text-gray-500 hover:text-error-500 dark:text-gray-400"
                            @click="form.access_expires_at = ''"
                        >
                            {{ t('settings.users.clearExpiry') }}
                        </button>
                    </div>

                    <!--
            ອາຍຸບັນຊີ "ຫຍໍ້ໃຫ້ສັ້ນລົງ" ໄດ້ຢ່າງດຽວ — ຖ້າຕັ້ງໄກກວ່າອົງກອນ
            ຈະບໍ່ມີຜົນເລີຍ. ຕ້ອງບອກຢູ່ນີ້ ບໍ່ດັ່ງນັ້ນຜູ້ດູແລຈະບັນທຶກແລ້ວ
            ສົງໄສວ່າເປັນຫຍັງຜູ້ໃຊ້ຍັງເຂົ້າບໍ່ໄດ້.
          -->
                    <p
                        v-if="companyCeiling"
                        class="mt-2 text-theme-xs"
                        :class="
                            exceedsCompanyCeiling
                                ? 'text-warning-600 dark:text-warning-400'
                                : 'text-gray-500 dark:text-gray-400'
                        "
                    >
                        {{
                            exceedsCompanyCeiling
                                ? t('settings.users.beyondCompanyExpiry', {
                                      date: formatDate(companyCeiling),
                                  })
                                : t('settings.users.companyExpiry', {
                                      date: formatDate(companyCeiling),
                                  })
                        }}
                    </p>
                </div>

                <label class="flex items-center gap-3 sm:col-span-2">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-5 w-5 rounded border-gray-300 text-brand-500 focus:ring-brand-500/10 dark:border-gray-700"
                    />
                    <span class="text-sm text-gray-700 dark:text-gray-400">{{
                        t('settings.users.isActive')
                    }}</span>
                </label>
            </div>
        </FormModal>

        <ConfirmDialog
            :open="removing !== null"
            title="settings.users.deleteTitle"
            :message="
                t('settings.users.deleteMessage', {
                    name: removing?.name ?? '',
                })
            "
            :saving="saving"
            @cancel="removing = null"
            @confirm="doRemove"
        />
    </AdminLayout>
</template>

<script setup lang="ts">
import type { ColDef, ICellRendererParams } from 'ag-grid-community';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import { api } from '@/api/client';
import DataGrid from '@/components/common/DataGrid.vue';
import SearchableSelect from '@/components/common/SearchableSelect.vue';
import DatePicker from '@/components/forms/DatePicker.vue';
import ActionsCell from '@/components/grid/ActionsCell.vue';
import BadgeCell from '@/components/grid/BadgeCell.vue';
import TwoLineCell from '@/components/grid/TwoLineCell.vue';
import UserAccessCell from '@/components/grid/UserAccessCell.vue';
import AdminLayout from '@/components/layout/AdminLayout.vue';
import ConfirmDialog from '@/components/settings/ConfirmDialog.vue';
import FormModal from '@/components/settings/FormModal.vue';
import SelectField from '@/components/settings/SelectField.vue';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import TextField from '@/components/settings/TextField.vue';
import { useAutoRefresh } from '@/composables/useAutoRefresh';
import { useCrud } from '@/composables/useCrud';
import { useDebouncedSearch } from '@/composables/useDebouncedSearch';
import { actionColDef } from '@/lib/ag-grid';
import { useAuthStore } from '@/stores/auth';
import type { AppUser, Company } from '@/types/settings';
import { formatDate } from '@/utils/date';
import FormIcon from '~icons/tabler/user-cog';

const { t } = useI18n();
const auth = useAuthStore();
const route = useRoute();

const {
    items,
    loading,
    saving,
    error,
    fetchAll,
    refresh,
    save,
    remove,
    clearError,
} = useCrud<AppUser & Record<string, unknown>>('/users', 'id', {
    describe: (user) => user.name,
});

const search = ref('');

/**
 * `?company=<uuid>` ມາຈາກປຸ່ມ "ຍັງບໍ່ມີຜູ້ໃຊ້" ໃນ ProjectMembersModal —
 * ເປີດມາແລ້ວເຫັນສະເພາະຜູ້ໃຊ້ຂອງອົງກອນນັ້ນ ຈຶ່ງບໍ່ຕ້ອງໄປຫາໃນ dropdown ຄືນ.
 */
const companyFilter = ref(
    typeof route.query.company === 'string' ? route.query.company : '',
);
const companies = ref<Company[]>([]);

const modalOpen = ref(false);
const editing = ref<AppUser | null>(null);
const removing = ref<AppUser | null>(null);

const listError = computed(() =>
    loading.value || modalOpen.value ? null : (error.value?.message ?? null),
);

function load(): void {
    const params: Record<string, string | boolean> = { include_inactive: true };

    if (search.value) {
        params.search = search.value;
    }

    if (companyFilter.value) {
        params.company_uuid = companyFilter.value;
    }

    fetchAll(params);
}

useDebouncedSearch(search, load);
watch(companyFilter, load);

// ຢຸດຕອນຟອມ/ກ່ອງຢືນຢັນເປີດ — ດຶງທັບຕອນນັ້ນຈະເຮັດໃຫ້ແຖວທີ່ກຳລັງແກ້ຫາຍໄປ
useAutoRefresh(refresh, {
    paused: () => modalOpen.value || removing.value !== null,
});

void (async () => {
    const { data } = await api.get<{ data: Company[] }>('/companies');
    companies.value = data.data;
})();

const companyOptions = computed(() =>
    companies.value.map((company) => ({
        value: company.uuid,
        label: company.name,
        translate: false,
    })),
);

const blankForm = {
    name: '',
    email: '',
    password: '',
    company_uuid: '',
    is_platform_admin: false,
    is_active: true,
    /** '' = ບໍ່ຈຳກັດລາຍຄົນ (ໃຊ້ອາຍຸຂອງອົງກອນຢ່າງດຽວ) */
    access_expires_at: '',
};

const form = reactive({ ...blankForm });

/** ປຸ່ມຕໍ່ອາຍຸດ່ວນ — ໜຶ່ງເດືອນ / ໜຶ່ງໄຕມາດ / ໜຶ່ງປີ */
const EXTEND_PRESETS = [30, 90, 365] as const;

/** "YYYY-MM-DD" ຕາມປະຕິທິນທ້ອງຖິ່ນ — `toISOString()` ແປງເປັນ UTC ກ່ອນ ຈຶ່ງເລື່ອນມື້ໄດ້ */
function toDateInput(date: Date): string {
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

/**
 * ນັບຕໍ່ຈາກວັນທີທີ່ຕັ້ງໄວ້ (ຖ້າຍັງບໍ່ໝົດ) ຫຼື ຈາກມື້ນີ້ —
 * ກົດ "+30 ວັນ" ໃສ່ວັນທີທີ່ຜ່ານໄປແລ້ວຄວນໄດ້ 30 ວັນນັບແຕ່ນີ້ ບໍ່ແມ່ນອາທິດທີ່ແລ້ວ.
 */
function extendBy(days: number): void {
    const today = new Date();
    const current = form.access_expires_at
        ? new Date(`${form.access_expires_at}T00:00:00`)
        : today;

    const base = current > today ? current : today;

    base.setDate(base.getDate() + days);
    form.access_expires_at = toDateInput(base);
}

/** ວັນໝົດອາຍຸຂອງອົງກອນທີ່ເລືອກ — ເພດານທີ່ວັນຂອງບັນຊີຢືດເກີນບໍ່ໄດ້ */
const companyCeiling = computed(() => {
    const company = companies.value.find(
        (item) => item.uuid === form.company_uuid,
    );

    return company?.effective_expires_at ?? null;
});

// ປຽບທຽບ string "YYYY-MM-DD" ສອງອັນ — ຮຽງລຳດັບຖືກຢູ່ແລ້ວ ແລະ ບໍ່ແມ່ນການ
// ປຽບທຽບກັບ "ມື້ນີ້" ຈຶ່ງບໍ່ມີບັນຫາ timezone (ນັ້ນຍັງເປັນວຽກຂອງ server)
const exceedsCompanyCeiling = computed(
    () =>
        Boolean(form.access_expires_at) &&
        companyCeiling.value !== null &&
        form.access_expires_at > companyCeiling.value,
);

// ຜູ້ດູແລລະບົບບໍ່ສັງກັດອົງກອນໃດ — ລ້າງໃຫ້ເອງເພື່ອບໍ່ໃຫ້ຄ່າຄ້າງທີ່ຂັດກັນ
watch(
    () => form.is_platform_admin,
    (isAdmin) => {
        if (isAdmin) {
            form.company_uuid = '';
        }
    },
);

function openCreate(): void {
    clearError();
    editing.value = null;
    Object.assign(form, blankForm, { company_uuid: companyFilter.value });
    modalOpen.value = true;
}

function openEdit(user: AppUser): void {
    clearError();
    editing.value = user;
    Object.assign(form, {
        ...blankForm,
        name: user.name,
        email: user.email,
        password: '',
        company_uuid: user.company?.uuid ?? '',
        is_platform_admin: user.is_platform_admin,
        is_active: user.is_active,
        // ວັນຂອງ **ບັນຊີ** ເທົ່ານັ້ນ — `expires_at` ອາດເປັນວັນຂອງອົງກອນ
        // ເຊິ່ງຖ້າເອົາມາໃສ່ຟອມຈະກາຍເປັນການຕັ້ງຂໍ້ຈຳກັດລາຍຄົນທີ່ບໍ່ເຄີຍມີ
        access_expires_at: user.access.user_expires_at ?? '',
    });
    modalOpen.value = true;
}

function closeModal(): void {
    modalOpen.value = false;
    clearError();
}

async function submit(): Promise<void> {
    const payload: Record<string, unknown> = {
        name: form.name,
        email: form.email,
        company_uuid: form.company_uuid || null,
        is_platform_admin: form.is_platform_admin,
        is_active: form.is_active,
        // ຊ່ອງຫວ່າງ = ຖອດຂໍ້ຈຳກັດລາຍຄົນອອກ → ສົ່ງ null ໃຫ້ຈະແຈ້ງ
        // (ຜູ້ດູແລລະບົບບໍ່ມີວັນໝົດອາຍຸ ຈຶ່ງລ້າງໃຫ້ພ້ອມກັນ)
        access_expires_at: form.is_platform_admin
            ? null
            : form.access_expires_at || null,
    };

    // ຊ່ອງລະຫັດຜ່ານຫວ່າງຕອນແກ້ໄຂ = ບໍ່ປ່ຽນ — ຫ້າມສົ່ງ null ໄປ
    // ບໍ່ດັ່ງນັ້ນ server ຈະຖືວ່າຢາກຕັ້ງລະຫັດຜ່ານເປັນຄ່າຫວ່າງ
    if (form.password) {
        payload.password = form.password;
    }

    if (await save(payload, editing.value)) {
        closeModal();
    }
}

function confirmRemove(user: AppUser): void {
    clearError();
    removing.value = user;
}

async function doRemove(): Promise<void> {
    const target = removing.value;

    if (target && (await remove(target))) {
        removing.value = null;
    }
}

// ---------- ຕາຕະລາງ ----------

const getRowId = (params: { data: AppUser }) => String(params.data.id);

const columnDefs = computed<ColDef[]>(() => [
    {
        field: 'name',
        headerName: t('settings.users.name'),
        flex: 2,
        minWidth: 220,
        cellRenderer: TwoLineCell,
        cellRendererParams: {
            secondary: (params: ICellRendererParams<AppUser>) =>
                params.data?.email ?? null,
            badge: (params: ICellRendererParams<AppUser>) =>
                params.data?.is_platform_admin
                    ? {
                          label: t('settings.users.platformAdmin'),
                          color: 'primary' as const,
                      }
                    : null,
        },
    },
    {
        colId: 'company',
        headerName: t('settings.users.company'),
        flex: 1,
        minWidth: 160,
        valueGetter: (params) => params.data?.company?.name ?? '—',
    },
    {
        colId: 'access',
        headerName: t('settings.access'),
        width: 150,
        cellRenderer: UserAccessCell,
        valueGetter: (params) =>
            params.data?.is_platform_admin
                ? t('settings.users.noExpiry')
                : (params.data?.access.plan ?? ''),
    },
    {
        field: 'is_active',
        headerName: t('settings.users.status'),
        width: 130,
        cellRenderer: BadgeCell,
        cellRendererParams: {
            color: (params: ICellRendererParams<AppUser>) =>
                params.data?.is_active ? 'success' : 'light',
        },
        valueGetter: (params) =>
            t(
                params.data?.is_active
                    ? 'settings.users.active'
                    : 'settings.users.disabled',
            ),
    },
    ...(auth.isPlatformAdmin
        ? [
              {
                  ...actionColDef,
                  colId: 'actions',
                  headerName: t('common.actions'),
                  width: 140,
                  cellRenderer: ActionsCell,
                  cellRendererParams: {
                      actions: (params: ICellRendererParams<AppUser>) => {
                          const user = params.data;

                          if (!user) {
                              return [];
                          }

                          return [
                              {
                                  label: t('common.edit'),
                                  onClick: () => openEdit(user),
                              },
                              {
                                  label: t('common.delete'),
                                  danger: true,
                                  // ລຶບບັນຊີຕົນເອງແລ້ວຈະ logout ທັນທີ ແລະ
                                  // ກັບເຂົ້າມາແກ້ບໍ່ໄດ້ອີກ
                                  disabled: user.id === auth.user?.id,
                                  onClick: () => confirmRemove(user),
                              },
                          ];
                      },
                  },
              },
          ]
        : []),
]);
</script>
