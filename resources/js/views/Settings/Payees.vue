<template>
    <AdminLayout>
        <SettingsPage
            v-model:search="search"
            title="settings.payees.title"
            :icon="FormIcon"
            description="settings.payees.description"
            :error="listError"
            :can-create="canWrite"
            create-label="settings.payees.create"
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

            <!--
                ຕາຕະລາງນີ້ແບ່ງໜ້າຢູ່ server (ຮ້ານໃໝ່ເພີ່ມທຸກເທື່ອທີ່ຈ່າຍ) —
                ບອກໃຫ້ຮູ້ວ່າຍັງມີອີກ ດີກວ່າຕັດຖິ້ມແບບງຽບໆແລ້ວໃຫ້ຄິດວ່າບໍ່ມີ
            -->
            <p
                v-if="total > items.length"
                class="px-5 py-3 text-theme-xs text-gray-500 dark:text-gray-400"
            >
                {{
                    t('settings.payees.truncated', {
                        shown: items.length,
                        total,
                    })
                }}
            </p>
        </SettingsPage>

        <FormModal
            :open="modalOpen"
            :icon="FormIcon"
            :title="editing ? 'settings.payees.edit' : 'settings.payees.create'"
            subtitle="settings.payees.formHint"
            :saving="saving"
            :error="error"
            @close="closeModal"
            @submit="submit"
        >
            <div class="grid gap-5 sm:grid-cols-2">
                <TextField
                    v-model="form.name"
                    label="settings.payees.name"
                    name="name"
                    :error="error"
                    required
                />

                <SelectField
                    v-model="form.type"
                    label="settings.payees.type"
                    name="type"
                    :error="error"
                    :options="typeOptions"
                />

                <TextField
                    v-model="form.phone"
                    label="settings.payees.phone"
                    name="phone"
                    :error="error"
                />

                <TextField
                    v-model="form.tax_id"
                    label="settings.payees.taxId"
                    name="tax_id"
                    :error="error"
                />

                <TextField
                    v-model="form.bank_name"
                    label="settings.payees.bankName"
                    name="bank_name"
                    :error="error"
                />

                <!--
                    ເລກບັນຊີເຂົ້າລະຫັດໄວ້ ແລະ server ບໍ່ເຄີຍສົ່ງເລກເຕັມກັບມາ
                    ຈຶ່ງເຕີມຄ່າເກົ່າໃສ່ຊ່ອງນີ້ບໍ່ໄດ້. ປະຫວ່າງໄວ້ = ບໍ່ປ່ຽນ.
                -->
                <TextField
                    v-model="form.bank_account_no"
                    label="settings.payees.bankAccount"
                    name="bank_account_no"
                    :error="error"
                    :placeholder="accountPlaceholder"
                    :hint="
                        editing && editing.bank_account_no_masked
                            ? 'settings.payees.bankAccountKeepHint'
                            : 'settings.payees.bankAccountHint'
                    "
                />

                <label
                    v-if="editing?.bank_account_no_masked"
                    class="flex items-center gap-3 sm:col-span-2"
                >
                    <input
                        v-model="form.clear_bank_account"
                        type="checkbox"
                        class="h-5 w-5 rounded border-gray-300 text-error-500 focus:ring-error-500/10 dark:border-gray-700"
                    />
                    <span class="text-sm text-gray-700 dark:text-gray-400">{{
                        t('settings.payees.clearBankAccount')
                    }}</span>
                </label>

                <div class="sm:col-span-2">
                    <TextField
                        v-model="form.note"
                        label="settings.payees.note"
                        name="note"
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
                        t('settings.payees.isActiveHint')
                    }}</span>
                </label>
            </div>
        </FormModal>

        <ConfirmDialog
            :open="removing !== null"
            title="settings.payees.deleteTitle"
            :message="
                t('settings.payees.deleteMessage', {
                    name: removing?.name ?? '',
                })
            "
            :saving="saving"
            :error="removeError"
            @cancel="cancelRemove"
            @confirm="doRemove"
        />
    </AdminLayout>
</template>

<script setup lang="ts">
import type { ColDef, ICellRendererParams } from 'ag-grid-community';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import DataGrid from '@/components/common/DataGrid.vue';
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
import type { Payee } from '@/types/settings';
import FormIcon from '~icons/tabler/user-dollar';

const { t } = useI18n();
const auth = useAuthStore();

const {
    items,
    total,
    loading,
    saving,
    error,
    fetchAll,
    refresh,
    save,
    remove,
    clearError,
} = useCrud<Payee & Record<string, unknown>>('/payees', 'id', {
    describe: (payee) => payee.name,
});

/** server ຄິດໃຫ້ຈາກ MasterDataPolicy — ຜູ້ດູແລລະບົບບໍ່ໄດ້ຖືກຍົກເວັ້ນ */
const canWrite = computed(() => auth.user?.can_manage_master_data === true);

const search = ref('');
const listError = computed(() =>
    loading.value || modalOpen.value || removing.value !== null
        ? null
        : (error.value?.message ?? null),
);

/** 409 ຕອນລຶບ — ສະແດງໃນກ່ອງຢືນຢັນ ບໍ່ແມ່ນແທນທີ່ຕາຕະລາງທັງໜ້າ */
const removeError = computed(() =>
    removing.value === null ? null : (error.value?.message ?? null),
);

// per_page ສູງສຸດທີ່ server ຍອມ — ຂໍໃຫ້ຄົບເທົ່າທີ່ຈະຄົບໄດ້ໃນເທື່ອດຽວ
const PER_PAGE = 200;

useDebouncedSearch(search, (term) =>
    fetchAll(
        term
            ? { search: term, include_inactive: true, per_page: PER_PAGE }
            : { include_inactive: true, per_page: PER_PAGE },
    ),
);

// ຢຸດຕອນຟອມ/ກ່ອງຢືນຢັນເປີດ — ດຶງທັບຕອນນັ້ນຈະເຮັດໃຫ້ແຖວທີ່ກຳລັງແກ້ຫາຍໄປ
useAutoRefresh(refresh, {
    paused: () => modalOpen.value || removing.value !== null,
});

// ---------- ຟອມ ----------
const modalOpen = ref(false);
const editing = ref<Payee | null>(null);
const removing = ref<Payee | null>(null);

const blankForm = {
    name: '',
    type: 'company',
    phone: '',
    bank_name: '',
    bank_account_no: '',
    clear_bank_account: false,
    tax_id: '',
    note: '',
    is_active: true,
};

const form = reactive({ ...blankForm });

const typeOptions = [
    { value: 'company', label: 'settings.partyType.company' },
    { value: 'individual', label: 'settings.partyType.individual' },
    { value: 'government', label: 'settings.partyType.government' },
];

/** ຊ່ອງເລກບັນຊີຫວ່າງສະເໝີ — ສະແດງຄ່າທີ່ mask ໄວ້ເປັນ placeholder ແທນ */
const accountPlaceholder = computed(
    () => editing.value?.bank_account_no_masked ?? '',
);

function openCreate(): void {
    clearError();
    editing.value = null;
    Object.assign(form, blankForm);
    modalOpen.value = true;
}

function openEdit(payee: Payee): void {
    clearError();
    editing.value = payee;
    Object.assign(form, {
        ...blankForm,
        ...payee,
        type: payee.type ?? 'company',
        phone: payee.phone ?? '',
        bank_name: payee.bank_name ?? '',
        // ຫວ່າງສະເໝີ: ບໍ່ມີເລກເຕັມໃຫ້ເຕີມ ແລະ ຫວ່າງ = ບໍ່ປ່ຽນ
        bank_account_no: '',
        clear_bank_account: false,
        tax_id: payee.tax_id ?? '',
        note: payee.note ?? '',
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
        type: form.type,
        phone: form.phone || null,
        bank_name: form.bank_name || null,
        tax_id: form.tax_id || null,
        note: form.note || null,
        is_active: form.is_active,
    };

    /**
     * ສາມກໍລະນີ — server ແຕະ column ນີ້ສະເພາະເມື່ອ key ຢູ່ໃນ payload:
     *   ພິມເລກໃໝ່     → ສົ່ງເລກນັ້ນ (ທັບຂອງເກົ່າ)
     *   ຕິ໊ກ "ລຶບເລກ" → ສົ່ງ null (ລ້າງອອກ)
     *   ປະຫວ່າງໄວ້     → ບໍ່ສົ່ງ key ເລີຍ (ຄ່າເກົ່າຄົງໄວ້)
     */
    if (form.bank_account_no) {
        payload.bank_account_no = form.bank_account_no;
    } else if (form.clear_bank_account) {
        payload.bank_account_no = null;
    }

    if (await save(payload, editing.value)) {
        closeModal();
    }
}

function confirmRemove(payee: Payee): void {
    clearError();
    removing.value = payee;
}

/** ປິດກ່ອງແລ້ວລ້າງ error ນຳ — ບໍ່ດັ່ງນັ້ນ 409 ເກົ່າຈະໄປໂຜ່ຢູ່ເທິງຕາຕະລາງ */
function cancelRemove(): void {
    removing.value = null;
    clearError();
}

async function doRemove(): Promise<void> {
    const target = removing.value;

    // ລົ້ມເຫຼວ (409) → ປະກ່ອງເປີດໄວ້ ໃຫ້ removeError ບອກເຫດຜົນ
    if (target && (await remove(target))) {
        removing.value = null;
    }
}

// ---------- ຕາຕະລາງ ----------

const getRowId = (params: { data: Payee }) => String(params.data.id);

const columnDefs = computed<ColDef[]>(() => [
    {
        field: 'name',
        headerName: t('settings.payees.name'),
        flex: 2,
        minWidth: 200,
        cellRenderer: TwoLineCell,
        cellRendererParams: {
            secondary: (params: ICellRendererParams<Payee>) =>
                params.data?.tax_id
                    ? `${t('settings.payees.taxId')}: ${params.data.tax_id}`
                    : null,
        },
    },
    {
        field: 'type',
        headerName: t('settings.payees.type'),
        width: 130,
        cellRenderer: BadgeCell,
        valueGetter: (params) =>
            t(`settings.partyType.${params.data?.type ?? 'company'}`),
    },
    {
        colId: 'bank',
        headerName: t('settings.payees.bank'),
        flex: 1,
        minWidth: 170,
        cellRenderer: TwoLineCell,
        cellRendererParams: {
            // ເລກເຕັມບໍ່ເຄີຍອອກຈາກ server — ມີແຕ່ 4 ຕົວທ້າຍ
            secondary: (params: ICellRendererParams<Payee>) =>
                params.data?.bank_account_no_masked ?? null,
        },
        valueGetter: (params) => params.data?.bank_name ?? '—',
    },
    {
        field: 'phone',
        headerName: t('settings.payees.phone'),
        width: 140,
        valueFormatter: (params) => params.value ?? '—',
    },
    {
        field: 'expenses_count',
        headerName: t('settings.payees.usedBy'),
        width: 110,
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
        valueFormatter: (params) => params.value ?? '—',
    },
    {
        field: 'is_active',
        headerName: t('settings.payees.isActive'),
        width: 130,
        cellRenderer: BadgeCell,
        cellRendererParams: {
            color: (params: ICellRendererParams<Payee>) =>
                params.data?.is_active ? 'success' : 'light',
        },
        valueGetter: (params) =>
            t(
                params.data?.is_active
                    ? 'settings.partyStatus.active'
                    : 'settings.partyStatus.inactive',
            ),
    },
    ...(canWrite.value
        ? [
              {
                  ...actionColDef,
                  colId: 'actions',
                  headerName: t('common.actions'),
                  width: 140,
                  cellRenderer: ActionsCell,
                  cellRendererParams: {
                      actions: (params: ICellRendererParams<Payee>) => {
                          const payee = params.data;

                          if (!payee) {
                              return [];
                          }

                          const used = payee.expenses_count ?? 0;

                          return [
                              {
                                  label: t('common.edit'),
                                  onClick: () => openEdit(payee),
                              },
                              {
                                  label: t('common.delete'),
                                  danger: true,
                                  disabled: used > 0,
                                  title:
                                      used > 0
                                          ? t('settings.payees.inUseHint', {
                                                count: used,
                                            })
                                          : t('common.delete'),
                                  onClick: () => confirmRemove(payee),
                              },
                          ];
                      },
                  },
              },
          ]
        : []),
]);
</script>
