<template>
    <AdminLayout>
        <SettingsPage
            v-model:search="search"
            title="settings.payers.title"
            :icon="FormIcon"
            description="settings.payers.description"
            :error="listError"
            :can-create="canWrite"
            create-label="settings.payers.create"
            @create="openCreate"
        >
            <DataGrid
                :column-defs="columnDefs"
                :row-data="items"
                :loading="loading"
                :get-row-id="getRowId"
                :row-height="56"
                :empty-label="t('settings.empty')"
            />
        </SettingsPage>

        <FormModal
            :open="modalOpen"
            :icon="FormIcon"
            :title="editing ? 'settings.payers.edit' : 'settings.payers.create'"
            subtitle="settings.payers.formHint"
            :saving="saving"
            :error="error"
            @close="closeModal"
            @submit="submit"
        >
            <div class="grid gap-5 sm:grid-cols-2">
                <TextField
                    v-model="form.name"
                    label="settings.payers.name"
                    name="name"
                    :error="error"
                    required
                    hint="settings.payers.nameHint"
                />

                <SelectField
                    v-model="form.type"
                    label="settings.payers.type"
                    name="type"
                    :error="error"
                    :options="typeOptions"
                />

                <TextField
                    v-model="form.contact_phone"
                    label="settings.payers.phone"
                    name="contact_phone"
                    :error="error"
                />

                <div class="sm:col-span-2">
                    <TextField
                        v-model="form.note"
                        label="settings.payers.note"
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
                        t('settings.payers.isActiveHint')
                    }}</span>
                </label>
            </div>
        </FormModal>

        <ConfirmDialog
            :open="removing !== null"
            title="settings.payers.deleteTitle"
            :message="
                t('settings.payers.deleteMessage', {
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
import type { Payer } from '@/types/settings';
import FormIcon from '~icons/tabler/wallet';

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
} = useCrud<Payer & Record<string, unknown>>('/payers', 'id', {
    describe: (payer) => payer.name,
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

// include_inactive: ໜ້ານີ້ຄືບ່ອນດຽວທີ່ເປີດແຖວທີ່ຢຸດໃຊ້ແລ້ວກັບມາໄດ້
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

/** ລາຍຈ່າຍ + ລາຍຮັບ — ທັງສອງຊີ້ມາທີ່ payer_id ຈຶ່ງກັນການລຶບໄດ້ທັງຄູ່ */
function usageCount(payer: Payer): number {
    return (payer.expenses_count ?? 0) + (payer.revenues_count ?? 0);
}

// ---------- ຕາຕະລາງ ----------

/** ຄີຄົງທີ່ — ຮອບໂຫຼດຄືນທຸກ 30 ວິນາທີບໍ່ຄວນສ້າງທຸກແຖວໃໝ່ */
const getRowId = (params: { data: Payer }) => String(params.data.id);

const columnDefs = computed<ColDef[]>(() => [
    {
        field: 'name',
        headerName: t('settings.payers.name'),
        flex: 2,
        minWidth: 180,
        cellRenderer: TwoLineCell,
        cellRendererParams: {
            secondary: (params: ICellRendererParams<Payer>) =>
                params.data?.note ?? null,
        },
    },
    {
        field: 'type',
        headerName: t('settings.payers.type'),
        width: 130,
        cellRenderer: BadgeCell,
        // ກັ່ນຕອງ ແລະ ຮຽງດ້ວຍຄຳທີ່ຜູ້ໃຊ້ເຫັນ ບໍ່ແມ່ນຄ່າດິບ 'company' —
        // ບໍ່ດັ່ງນັ້ນພິມ "ບໍລິສັດ" ໃສ່ຊ່ອງກັ່ນຕອງແລ້ວຈະບໍ່ໄດ້ຫຍັງເລີຍ
        valueGetter: (params) =>
            t(`settings.partyType.${params.data?.type ?? 'company'}`),
    },
    {
        field: 'contact_phone',
        headerName: t('settings.payers.phone'),
        width: 140,
        valueFormatter: (params) => params.value ?? '—',
    },
    {
        headerName: t('settings.payers.usedBy'),
        colId: 'usage',
        width: 110,
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
        valueGetter: (params) => (params.data ? usageCount(params.data) : 0),
    },
    {
        field: 'is_active',
        headerName: t('settings.payers.isActive'),
        width: 130,
        cellRenderer: BadgeCell,
        cellRendererParams: {
            color: (params: ICellRendererParams<Payer>) =>
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
                      actions: (params: ICellRendererParams<Payer>) => {
                          const payer = params.data;

                          if (!payer) {
                              return [];
                          }

                          const used = usageCount(payer);

                          return [
                              {
                                  label: t('common.edit'),
                                  onClick: () => openEdit(payer),
                              },
                              {
                                  label: t('common.delete'),
                                  danger: true,
                                  disabled: used > 0,
                                  title:
                                      used > 0
                                          ? t('settings.payers.inUseHint', {
                                                count: used,
                                            })
                                          : t('common.delete'),
                                  onClick: () => confirmRemove(payer),
                              },
                          ];
                      },
                  },
              },
          ]
        : []),
]);

// ---------- ຟອມ ----------
const modalOpen = ref(false);
const editing = ref<Payer | null>(null);
const removing = ref<Payer | null>(null);

const blankForm = {
    name: '',
    type: 'company',
    contact_phone: '',
    note: '',
    is_active: true,
};

const form = reactive({ ...blankForm });

const typeOptions = [
    { value: 'company', label: 'settings.partyType.company' },
    { value: 'individual', label: 'settings.partyType.individual' },
];

function openCreate(): void {
    clearError();
    editing.value = null;
    Object.assign(form, blankForm);
    modalOpen.value = true;
}

function openEdit(payer: Payer): void {
    clearError();
    editing.value = payer;
    Object.assign(form, {
        ...blankForm,
        ...payer,
        type: payer.type ?? 'company',
        contact_phone: payer.contact_phone ?? '',
        note: payer.note ?? '',
    });
    modalOpen.value = true;
}

function closeModal(): void {
    modalOpen.value = false;
    clearError();
}

async function submit(): Promise<void> {
    const payload = {
        name: form.name,
        type: form.type,
        // "" → null ບໍ່ດັ່ງນັ້ນຈະເກັບ string ຫວ່າງແທນທີ່ຈະເປັນ "ບໍ່ໄດ້ລະບຸ"
        contact_phone: form.contact_phone || null,
        note: form.note || null,
        is_active: form.is_active,
    };

    if (await save(payload, editing.value)) {
        closeModal();
    }
}

function confirmRemove(payer: Payer): void {
    clearError();
    removing.value = payer;
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
</script>
