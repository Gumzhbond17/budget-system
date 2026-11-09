<template>
    <AdminLayout>
        <SettingsPage
            title="settings.currencies.title"
            :icon="FormIcon"
            description="settings.currencies.description"
            :error="listError"
            :can-create="canWrite"
            create-label="settings.currencies.create"
            :searchable="false"
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
            :title="
                editing
                    ? 'settings.currencies.edit'
                    : 'settings.currencies.create'
            "
            :subtitle="
                editing
                    ? 'settings.currencies.editHint'
                    : 'settings.currencies.createHint'
            "
            :saving="saving"
            :error="error"
            @close="closeModal"
            @submit="submit"
        >
            <div class="grid gap-5 sm:grid-cols-2">
                <TextField
                    v-model="form.code"
                    label="settings.currencies.code"
                    name="code"
                    :error="error"
                    required
                    :disabled="editing !== null"
                    placeholder="LAK"
                    :hint="
                        editing
                            ? 'settings.currencies.codeLocked'
                            : 'settings.currencies.codeHint'
                    "
                />

                <TextField
                    v-model="form.symbol"
                    label="settings.currencies.symbol"
                    name="symbol"
                    :error="error"
                    required
                    placeholder="₭"
                />

                <TextField
                    v-model="form.name_lo"
                    label="settings.currencies.nameLo"
                    name="name_lo"
                    :error="error"
                    required
                />

                <TextField
                    v-model="form.name_en"
                    label="settings.currencies.nameEn"
                    name="name_en"
                    :error="error"
                    required
                />

                <!--
                    ຊ່ອງທີ່ອັນຕະລາຍທີ່ສຸດໃນໜ້ານີ້ — ຕັ້ງໄດ້ເທື່ອດຽວຕອນສ້າງ.
                    ແກ້ພາຍຫຼັງ = ຍອດເງິນທຸກແຖວທີ່ເກັບໄວ້ແລ້ວປ່ຽນຄວາມໝາຍທັນທີ
                    ໂດຍບໍ່ມີແຖວໃດຖືກແກ້ (server ກໍປະຕິເສດຢູ່ແລ້ວ — ນີ້ພຽງແຕ່
                    ບໍ່ໃຫ້ຜູ້ໃຊ້ພິມລົງໄປແລ້ວຄ່ອຍໄດ້ 422)
                -->
                <SelectField
                    v-model="form.decimal_places"
                    label="settings.currencies.decimals"
                    name="decimal_places"
                    :error="error"
                    required
                    :disabled="editing !== null"
                    :options="decimalOptions"
                    :hint="
                        editing
                            ? 'settings.currencies.decimalsLocked'
                            : 'settings.currencies.decimalsHint'
                    "
                />

                <TextField
                    v-model="form.sort_order"
                    label="settings.currencies.sortOrder"
                    name="sort_order"
                    type="number"
                    :error="error"
                    hint="settings.currencies.sortOrderHint"
                />

                <label class="flex items-center gap-3 sm:col-span-2">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-5 w-5 rounded border-gray-300 text-brand-500 focus:ring-brand-500/10 dark:border-gray-700"
                    />
                    <span class="text-sm text-gray-700 dark:text-gray-400">{{
                        t('settings.currencies.isActiveHint')
                    }}</span>
                </label>
            </div>
        </FormModal>

        <ConfirmDialog
            :open="removing !== null"
            title="settings.currencies.deleteTitle"
            :message="
                t('settings.currencies.deleteMessage', {
                    code: removing?.code ?? '',
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
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import DataGrid from '@/components/common/DataGrid.vue';
import ActionsCell from '@/components/grid/ActionsCell.vue';
import BadgeCell from '@/components/grid/BadgeCell.vue';
import CodeCell from '@/components/grid/CodeCell.vue';
import TwoLineCell from '@/components/grid/TwoLineCell.vue';
import AdminLayout from '@/components/layout/AdminLayout.vue';
import ConfirmDialog from '@/components/settings/ConfirmDialog.vue';
import FormModal from '@/components/settings/FormModal.vue';
import SelectField from '@/components/settings/SelectField.vue';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import TextField from '@/components/settings/TextField.vue';
import { useAutoRefresh } from '@/composables/useAutoRefresh';
import { useCrud } from '@/composables/useCrud';
import { actionColDef } from '@/lib/ag-grid';
import { useAuthStore } from '@/stores/auth';
import type { Currency } from '@/types/settings';
import FormIcon from '~icons/tabler/coins';

/**
 * ສະກຸນທີ່ລະບົບໃຊ້ເປັນໂຄງສ້າງ ບໍ່ແມ່ນຂໍ້ມູນ:
 *   USD = pivot ທີ່ທຸກອັດຕາອ້າງອີງ
 *   LAK = ສະກຸນລາຍງານ ທີ່ CurrencyConverter ຄິດໃສ່ທຸກແຖວ
 * server ປະຕິເສດການລຶບຢູ່ແລ້ວ (409) — ຢູ່ນີ້ພຽງແຕ່ບອກລ່ວງໜ້າ
 */
const STRUCTURAL_CODES = ['USD', 'LAK'];

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
} = useCrud<Currency & Record<string, unknown>>('/currencies', 'code', {
    describe: (currency) => `${currency.code} — ${currency.name_lo}`,
});

const canWrite = computed(() => auth.user?.can_manage_master_data === true);

const listError = computed(() =>
    loading.value || modalOpen.value || removing.value !== null
        ? null
        : (error.value?.message ?? null),
);

const removeError = computed(() =>
    removing.value === null ? null : (error.value?.message ?? null),
);

// include_inactive: ໜ້ານີ້ຄືບ່ອນດຽວທີ່ເປີດສະກຸນທີ່ຢຸດໃຊ້ແລ້ວກັບມາໄດ້
// with_usage: ຕ້ອງຮູ້ວ່າແຖວໃດລຶບໄດ້ ກ່ອນຈະສະແດງປຸ່ມລຶບ
onMounted(() => fetchAll({ include_inactive: true, with_usage: true }));

// ຢຸດຕອນຟອມ/ກ່ອງຢືນຢັນເປີດ — ດຶງທັບຕອນນັ້ນຈະເຮັດໃຫ້ແຖວທີ່ກຳລັງແກ້ຫາຍໄປ
useAutoRefresh(refresh, {
    paused: () => modalOpen.value || removing.value !== null,
});

function isStructural(currency: Currency): boolean {
    return STRUCTURAL_CODES.includes(currency.code);
}

function isDeletable(currency: Currency): boolean {
    return !isStructural(currency) && (currency.usage_count ?? 0) === 0;
}

function deleteHint(currency: Currency): string {
    if (isStructural(currency)) {
        return t('settings.currencies.coreHint', { code: currency.code });
    }

    return isDeletable(currency)
        ? t('common.delete')
        : t('settings.currencies.inUseHint', {
              count: currency.usage_count ?? 0,
          });
}

/**
 * ຕົວຢ່າງວ່າ 1 ຫົວໜ່ວຍຍ່ອຍ (minor unit) ຂອງສະກຸນນີ້ຄືເທົ່າໃດ.
 *
 * ນີ້ຄືສິ່ງທີ່ເຮັດໃຫ້ decimal_places ຈັບຕ້ອງໄດ້: ຄົນຕັ້ງຄ່າເຫັນ "1 ₭" ທຽບກັບ
 * "0.01 $" ແລ້ວຈຶ່ງເຂົ້າໃຈວ່າເປັນຫຍັງແກ້ພາຍຫຼັງບໍ່ໄດ້.
 */
function minorUnitExample(currency: Currency): string {
    const one = (1 / 10 ** currency.decimal_places).toFixed(
        currency.decimal_places,
    );

    return `1 minor = ${one} ${currency.code}`;
}

// ---------- ຕາຕະລາງ ----------

const getRowId = (params: { data: Currency }) => params.data.code;

const columnDefs = computed<ColDef[]>(() => [
    {
        field: 'code',
        headerName: t('settings.currencies.code'),
        width: 140,
        cellRenderer: CodeCell,
        cellRendererParams: {
            badge: (params: ICellRendererParams<Currency>) =>
                params.data && isStructural(params.data)
                    ? {
                          label: t('settings.currencies.core'),
                          color: 'info' as const,
                      }
                    : null,
        },
    },
    {
        colId: 'name',
        headerName: t('settings.currencies.name'),
        flex: 2,
        minWidth: 180,
        cellRenderer: TwoLineCell,
        cellRendererParams: {
            secondary: (params: ICellRendererParams<Currency>) =>
                params.data?.name_en ?? null,
        },
        valueGetter: (params) =>
            params.data ? `${params.data.symbol} ${params.data.name_lo}` : '',
    },
    {
        field: 'decimal_places',
        headerName: t('settings.currencies.decimals'),
        width: 110,
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
    },
    {
        colId: 'example',
        headerName: t('settings.currencies.example'),
        width: 190,
        cellClass: 'font-mono text-theme-xs text-gray-500 dark:text-gray-400',
        valueGetter: (params) =>
            params.data ? minorUnitExample(params.data) : '',
    },
    {
        colId: 'usage',
        headerName: t('settings.currencies.usedBy'),
        width: 110,
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
        valueGetter: (params) => params.data?.usage_count ?? 0,
    },
    {
        field: 'is_active',
        headerName: t('settings.currencies.isActive'),
        width: 130,
        cellRenderer: BadgeCell,
        cellRendererParams: {
            color: (params: ICellRendererParams<Currency>) =>
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
                      actions: (params: ICellRendererParams<Currency>) => {
                          const currency = params.data;

                          if (!currency) {
                              return [];
                          }

                          return [
                              {
                                  label: t('common.edit'),
                                  onClick: () => openEdit(currency),
                              },
                              {
                                  label: t('common.delete'),
                                  danger: true,
                                  disabled: !isDeletable(currency),
                                  title: deleteHint(currency),
                                  onClick: () => confirmRemove(currency),
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
const editing = ref<Currency | null>(null);
const removing = ref<Currency | null>(null);

const blankForm = {
    code: '',
    name_lo: '',
    name_en: '',
    symbol: '',
    // string ບໍ່ແມ່ນ number: <select> ຄືນ string ສະເໝີ
    decimal_places: '2',
    sort_order: '0',
    is_active: true,
};

const form = reactive({ ...blankForm });

const decimalOptions = [
    { value: '0', label: 'settings.currencies.decimals0' },
    { value: '2', label: 'settings.currencies.decimals2' },
    { value: '3', label: 'settings.currencies.decimals3' },
];

function openCreate(): void {
    clearError();
    editing.value = null;
    Object.assign(form, blankForm);
    modalOpen.value = true;
}

function openEdit(currency: Currency): void {
    clearError();
    editing.value = currency;
    Object.assign(form, {
        ...blankForm,
        ...currency,
        decimal_places: String(currency.decimal_places),
        sort_order: String(currency.sort_order),
    });
    modalOpen.value = true;
}

function closeModal(): void {
    modalOpen.value = false;
    clearError();
}

async function submit(): Promise<void> {
    const payload: Record<string, unknown> = {
        name_lo: form.name_lo,
        name_en: form.name_en,
        symbol: form.symbol,
        sort_order: Number(form.sort_order) || 0,
        is_active: form.is_active,
    };

    // code ກັບ decimal_places ສົ່ງສະເພາະຕອນສ້າງ — ຕອນແກ້ server ຮັບແຕ່ຄ່າເກົ່າ
    // ຄືນມາເທົ່ານັ້ນ ຈຶ່ງບໍ່ຕ້ອງສົ່ງໄປໃຫ້ເປັນພາລະ
    if (!editing.value) {
        payload.code = form.code;
        payload.decimal_places = Number(form.decimal_places);
    }

    if (await save(payload, editing.value)) {
        closeModal();
    }
}

function confirmRemove(currency: Currency): void {
    clearError();
    removing.value = currency;
}

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
