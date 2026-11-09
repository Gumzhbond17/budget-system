<template>
    <AdminLayout>
        <SettingsPage
            v-model:search="search"
            title="settings.companies.title"
            :icon="FormIcon"
            description="settings.companies.description"
            :error="listError"
            :can-create="canCreate"
            :create-label="
                auth.isPlatformAdmin
                    ? 'settings.companies.create'
                    : 'settings.companies.createStore'
            "
            @create="openCreate"
        >
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
            :title="
                editing
                    ? 'settings.companies.edit'
                    : auth.isPlatformAdmin
                      ? 'settings.companies.create'
                      : 'settings.companies.createStore'
            "
            subtitle="settings.companies.formHint"
            :saving="saving"
            :error="error"
            @close="closeModal"
            @submit="submit"
        >
            <div class="grid gap-5 sm:grid-cols-2">
                <!--
          ລະຫັດບໍ່ບັງຄັບອີກຕໍ່ໄປ — server ສ້າງໃຫ້ຈາກຊື່ຖ້າປະຫວ່າງໄວ້.
          ນີ້ຄືສິ່ງທີ່ເຮັດໃຫ້ "ໃສ່ແຕ່ຊື່ຮ້ານ" ເປັນໄປໄດ້ຈິງ.
        -->
                <TextField
                    v-model="form.code"
                    label="settings.companies.code"
                    name="code"
                    :error="error"
                    :hint="
                        editing
                            ? 'settings.companies.codeHint'
                            : 'settings.companies.codeAutoHint'
                    "
                />
                <TextField
                    v-model="form.name"
                    label="settings.companies.name"
                    name="name"
                    :error="error"
                    required
                />

                <TextField
                    v-model="form.name_en"
                    label="settings.companies.nameEn"
                    name="name_en"
                    :error="error"
                />

                <SelectField
                    v-model="form.type"
                    label="settings.companies.type"
                    name="type"
                    :error="error"
                    :options="typeOptions"
                />

                <SelectField
                    v-model="form.parent_uuid"
                    label="settings.companies.parent"
                    name="parent_uuid"
                    :error="error"
                    :options="parentOptions"
                    nullable
                    null-label="settings.companies.noParent"
                    hint="settings.companies.parentHint"
                />

                <!--
          ຊ່ອງການເກັບເງິນ — ຜູ້ດູແລລະບົບເທົ່ານັ້ນ.
          ສາຂາ/ຮ້ານໃຊ້ແພັກເກັດຂອງອົງກອນແມ່ (Company::billingRoot) ຈຶ່ງບໍ່ມີ
          ຫຍັງໃຫ້ຕັ້ງ ແລະ server ກໍຕັດຊ່ອງເຫຼົ່ານີ້ຖິ້ມຢູ່ແລ້ວ — ສະແດງໄປກໍມີແຕ່
          ຈະໃຫ້ຄົນຄິດວ່າຕໍ່ອາຍຸໃຫ້ຕົນເອງໄດ້
        -->
                <template v-if="auth.isPlatformAdmin">
                    <SelectField
                        v-model="form.plan"
                        label="settings.companies.plan"
                        name="plan"
                        :error="error"
                        :options="planOptions"
                    />

                    <!--
              ສະແດງຊ່ອງວັນທີໃຫ້ກົງກັບ plan ທີ່ເລືອກ — ຖ້າສະແດງທັງສອງພ້ອມກັນ
              ຜູ້ດູແລຈະປ້ອນຜິດຊ່ອງ ແລ້ວສົງໄສວ່າເປັນຫຍັງອາຍຸບໍ່ປ່ຽນ.
            -->
                    <DatePicker
                        v-if="form.plan === 'trial'"
                        v-model="form.trial_ends_at"
                        label="settings.companies.trialEndsAt"
                        name="trial_ends_at"
                        :error="error"
                        hint="settings.companies.trialHint"
                    />

                    <DatePicker
                        v-else-if="form.plan === 'active'"
                        v-model="form.access_expires_at"
                        label="settings.companies.expiresAt"
                        name="access_expires_at"
                        :error="error"
                        hint="settings.companies.expiresHint"
                    />
                </template>

                <TextField
                    v-model="form.phone"
                    label="settings.companies.phone"
                    name="phone"
                    :error="error"
                />
                <TextField
                    v-model="form.email"
                    type="email"
                    label="settings.companies.email"
                    name="email"
                    :error="error"
                />
                <TextField
                    v-model="form.tax_id"
                    label="settings.companies.taxId"
                    name="tax_id"
                    :error="error"
                />

                <div class="sm:col-span-2">
                    <TextField
                        v-model="form.address"
                        label="settings.companies.address"
                        name="address"
                        :error="error"
                    />
                </div>

                <label class="flex items-center gap-3 sm:col-span-2">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-5 w-5 rounded border-gray-300 text-brand-500 focus:ring-brand-500/10 dark:border-gray-700"
                    />
                    <span class="text-sm text-gray-700 dark:text-gray-400">{{
                        t('settings.companies.isActive')
                    }}</span>
                </label>
            </div>
        </FormModal>

        <ConfirmDialog
            :open="removing !== null"
            title="settings.companies.deleteTitle"
            :message="
                t('settings.companies.deleteMessage', {
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
import DataGrid from '@/components/common/DataGrid.vue';
import DatePicker from '@/components/forms/DatePicker.vue';
import AccessBadgeCell from '@/components/grid/AccessBadgeCell.vue';
import ActionsCell from '@/components/grid/ActionsCell.vue';
import BadgeCell from '@/components/grid/BadgeCell.vue';
import TwoLineCell from '@/components/grid/TwoLineCell.vue';
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
import type { Company } from '@/types/settings';
import FormIcon from '~icons/tabler/building-community';

const { t } = useI18n();
const auth = useAuthStore();

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
} = useCrud<Company & Record<string, unknown>>('/companies', 'uuid', {
    describe: (company) => company.name,
});

const search = ref('');
const listError = computed(() =>
    loading.value || modalOpen.value ? null : (error.value?.message ?? null),
);

/**
 * ຜູ້ດູແລລະບົບສ້າງອົງກອນໄດ້; ຜູ້ໃຊ້ທີ່ສັງກັດອົງກອນໃດໜຶ່ງສ້າງສາຂາ/ຮ້ານ
 * ພາຍໃຕ້ອົງກອນຕົນໄດ້ (CompanyPolicy::create).
 *
 * ຜູ້ໃຊ້ທີ່ຍັງບໍ່ໄດ້ຈັດສັນເຂົ້າອົງກອນໃດ ສ້າງບໍ່ໄດ້ — ບໍ່ມີບ່ອນໃຫ້ແປະ.
 */
const canCreate = computed(
    () => auth.isPlatformAdmin || Boolean(auth.user?.company),
);

// ພິມແລ້ວຢຸດ 300ms ຈຶ່ງຍິງ — ບໍ່ດັ່ງນັ້ນຈະຍິງ 1 request ຕໍ່ 1 ຕົວອັກສອນ
useDebouncedSearch(search, (term) =>
    fetchAll(
        term
            ? { search: term, include_inactive: true }
            : { include_inactive: true },
    ),
);

// ຢຸດຕອນຟອມ/ກ່ອງຢືນຢັນເປີດ — ດຶງທັບຕອນນັ້ນຈະເຮັດໃຫ້ແຖວທີ່ກຳລັງແກ້ຫາຍໄປ
useAutoRefresh(refresh, {
    paused: () => modalOpen.value || removing.value !== null,
});

// ---------- ຕາຕະລາງ ----------

const getRowId = (params: { data: Company }) => params.data.uuid;

const columnDefs = computed<ColDef[]>(() => [
    {
        field: 'name',
        headerName: t('settings.companies.name'),
        flex: 2,
        minWidth: 200,
        cellRenderer: TwoLineCell,
        cellRendererParams: {
            secondary: (params: ICellRendererParams<Company>) =>
                params.data?.code ?? null,
            depth: (params: ICellRendererParams<Company>) =>
                params.data?.depth ?? 0,
        },
        // ຮຽງຕາມຊື່ຈະທຳລາຍໂຄງສ້າງແມ່-ລູກ: server ຮຽງຕາມ `path` ມາໃຫ້ແລ້ວ
        // ແລະ ການຍໍ້ໜ້າມີຄວາມໝາຍສະເພາະເມື່ອລູກຢູ່ຕິດກັບແມ່ຂອງມັນ
        sortable: false,
    },
    {
        field: 'type',
        headerName: t('settings.companies.type'),
        width: 140,
        cellRenderer: BadgeCell,
        valueGetter: (params) =>
            params.data ? t(`settings.companyType.${params.data.type}`) : '',
    },
    {
        colId: 'access',
        headerName: t('settings.access'),
        width: 150,
        cellRenderer: AccessBadgeCell,
        // ກັ່ນຕອງ/ຮຽງດ້ວຍແຜນ — ສະຖານະທີ່ເຫັນເປັນຜົນລວມຂອງສາມ field
        valueGetter: (params) => params.data?.plan ?? '',
    },
    {
        field: 'projects_count',
        headerName: t('settings.companies.projects'),
        width: 120,
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
        valueFormatter: (params) => params.value ?? '—',
    },
    {
        field: 'users_count',
        headerName: t('settings.companies.users'),
        width: 110,
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
        valueFormatter: (params) => params.value ?? '—',
    },
    {
        ...actionColDef,
        colId: 'actions',
        headerName: t('common.actions'),
        width: 140,
        cellRenderer: ActionsCell,
        cellRendererParams: {
            // ຖັນນີ້ສະແດງໃຫ້ທຸກຄົນແລ້ວ ແຕ່ປຸ່ມມາຈາກ `can` ຂອງແຖວ — ຜູ້ດູແລ
            // ລະດັບອົງກອນຈະເຫັນປຸ່ມສະເພາະສາຂາ/ຮ້ານຂອງຕົນ ສ່ວນແຖວອົງກອນເອງ
            // ຈະບໍ່ມີປຸ່ມ. ຫ້າມຄິດຈາກ `type` + `is_platform_admin` ຢູ່ນີ້:
            // ກົດຈິງຕ້ອງທຽບ `path` ກັບອົງກອນຂອງຜູ້ໃຊ້ ເຊິ່ງ SPA ບໍ່ມີໃນມື
            actions: (params: ICellRendererParams<Company>) => {
                const company = params.data;

                if (!company) {
                    return [];
                }

                return [
                    ...(company.can?.update
                        ? [
                              {
                                  label: t('common.edit'),
                                  onClick: () => openEdit(company),
                              },
                          ]
                        : []),
                    ...(company.can?.delete
                        ? [
                              {
                                  label: t('common.delete'),
                                  danger: true,
                                  onClick: () => confirmRemove(company),
                              },
                          ]
                        : []),
                ];
            },
        },
    },
]);

// ---------- ຟອມ ----------
const modalOpen = ref(false);
const editing = ref<Company | null>(null);
const removing = ref<Company | null>(null);

const blankForm = {
    code: '',
    name: '',
    name_en: '',
    // ຜູ້ໃຊ້ທຳມະດາສ່ວນຫຼາຍມາເພື່ອເພີ່ມ "ຮ້ານ" — ຕັ້ງເປັນຄ່າເລີ່ມຕົ້ນໃຫ້ເລີຍ
    type: auth.isPlatformAdmin ? 'internal' : 'store',
    parent_uuid: '',
    plan: 'trial',
    trial_ends_at: '',
    access_expires_at: '',
    phone: '',
    email: '',
    tax_id: '',
    address: '',
    is_active: true,
};

const form = reactive({ ...blankForm });

/**
 * ຜູ້ໃຊ້ທຳມະດາສ້າງໄດ້ແຕ່ສາຂາ/ຮ້ານ — ອົງກອນລະດັບເທິງຖືແພັກເກັດ ແລະ ວັນໝົດອາຍຸ
 * ຈຶ່ງເປັນວຽກຂອງຜູ້ດູແລລະບົບ (server ບັງຄັບອີກຊັ້ນ — ເບິ່ງ CompanyController)
 */
const typeOptions = computed(() =>
    auth.isPlatformAdmin
        ? [
              { value: 'internal', label: 'settings.companyType.internal' },
              { value: 'branch', label: 'settings.companyType.branch' },
              {
                  value: 'third_party',
                  label: 'settings.companyType.third_party',
              },
              { value: 'store', label: 'settings.companyType.store' },
          ]
        : [
              { value: 'store', label: 'settings.companyType.store' },
              { value: 'branch', label: 'settings.companyType.branch' },
          ],
);

const planOptions = [
    { value: 'trial', label: 'settings.plan.trial' },
    { value: 'active', label: 'settings.plan.active' },
    { value: 'suspended', label: 'settings.plan.suspended' },
];

/** ຕົນເອງບໍ່ຄວນຢູ່ໃນລາຍການ "ອົງກອນແມ່" ຂອງຕົນເອງ (server ກໍປະຕິເສດຢູ່ແລ້ວ) */
const parentOptions = computed(() =>
    items.value
        .filter((company) => company.uuid !== editing.value?.uuid)
        .map((company) => ({
            value: company.uuid,
            label: company.name,
            translate: false,
        })),
);

function openCreate(): void {
    clearError();
    editing.value = null;
    Object.assign(form, blankForm);
    modalOpen.value = true;
}

function openEdit(company: Company): void {
    clearError();
    editing.value = company;
    Object.assign(form, {
        ...blankForm,
        ...company,
        name_en: company.name_en ?? '',
        parent_uuid: company.parent_uuid ?? '',
        trial_ends_at: company.trial_ends_at ?? '',
        access_expires_at: company.access_expires_at ?? '',
        phone: company.phone ?? '',
        email: company.email ?? '',
        tax_id: company.tax_id ?? '',
        address: company.address ?? '',
    });
    modalOpen.value = true;
}

function closeModal(): void {
    modalOpen.value = false;
    clearError();
}

async function submit(): Promise<void> {
    // ຊ່ອງຫວ່າງ → null ບໍ່ດັ່ງນັ້ນ "" ຈະຕົກ validation `date` ຢູ່ server
    const payload = Object.fromEntries(
        Object.entries({ ...form }).map(([key, value]) => [
            key,
            value === '' ? null : value,
        ]),
    );

    if (await save(payload, editing.value)) {
        closeModal();
    }
}

function confirmRemove(company: Company): void {
    clearError();
    removing.value = company;
}

async function doRemove(): Promise<void> {
    const target = removing.value;

    if (target && (await remove(target))) {
        removing.value = null;
    }
}

// ປ່ຽນ plan ແລ້ວລ້າງວັນທີຂອງ plan ອື່ນ — ກັນຄ່າຄ້າງທີ່ບໍ່ມີຜົນຫຍັງ
watch(
    () => form.plan,
    (plan) => {
        if (plan !== 'trial') {
            form.trial_ends_at = '';
        }

        if (plan !== 'active') {
            form.access_expires_at = '';
        }
    },
);
</script>
