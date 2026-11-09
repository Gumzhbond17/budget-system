<template>
    <AdminLayout>
        <SettingsPage
            v-model:search="search"
            title="settings.categories.title"
            :icon="FormIcon"
            description="settings.categories.description"
            :error="listError"
            :can-create="canWrite"
            create-label="settings.categories.create"
            @create="openCreate"
        >
            <DataGrid
                :column-defs="columnDefs"
                :row-data="ordered"
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
                    ? 'settings.categories.edit'
                    : 'settings.categories.create'
            "
            subtitle="settings.categories.formHint"
            :saving="saving"
            :error="error"
            @close="closeModal"
            @submit="submit"
        >
            <div class="grid gap-5 sm:grid-cols-2">
                <TextField
                    v-model="form.code"
                    label="settings.categories.code"
                    name="code"
                    :error="error"
                    required
                    hint="settings.categories.codeHint"
                />
                <TextField
                    v-model="form.name_lo"
                    label="settings.categories.nameLo"
                    name="name_lo"
                    :error="error"
                    required
                />
                <TextField
                    v-model="form.name_en"
                    label="settings.categories.nameEn"
                    name="name_en"
                    :error="error"
                />

                <SelectField
                    v-model="form.parent_id"
                    label="settings.categories.parent"
                    name="parent_id"
                    :error="error"
                    :options="parentOptions"
                    nullable
                    null-label="settings.categories.noParent"
                    hint="settings.categories.parentHint"
                />

                <!--
                    ໃຊ້ input[type=color] ຄູ່ກັບຊ່ອງ text: ຕົວເລືອກສີເລືອກງ່າຍ
                    ແຕ່ຄົນທີ່ມີລະຫັດສີຂອງ brand ຢູ່ແລ້ວກໍວາງໃສ່ໄດ້ເລີຍ.
                -->
                <div>
                    <span
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        {{ t('settings.categories.color') }}
                    </span>
                    <div class="flex items-center gap-3">
                        <input
                            v-model="form.color"
                            type="color"
                            :aria-label="t('settings.categories.color')"
                            class="h-11 w-14 shrink-0 cursor-pointer rounded-lg border border-gray-300 bg-transparent dark:border-gray-700"
                        />
                        <input
                            v-model="form.color"
                            type="text"
                            name="color"
                            placeholder="#6366f1"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 font-mono text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>
                    <p v-if="colorError" class="mt-1.5 text-sm text-error-500">
                        {{ colorError }}
                    </p>
                    <p
                        v-else
                        class="mt-1.5 text-theme-xs text-gray-500 dark:text-gray-400"
                    >
                        {{ t('settings.categories.colorHint') }}
                    </p>
                </div>

                <TextField
                    v-model="form.sort_order"
                    type="number"
                    label="settings.categories.sortOrder"
                    name="sort_order"
                    :error="error"
                    hint="settings.categories.sortOrderHint"
                />

                <label class="flex items-center gap-3 sm:col-span-2">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-5 w-5 rounded border-gray-300 text-brand-500 focus:ring-brand-500/10 dark:border-gray-700"
                    />
                    <span class="text-sm text-gray-700 dark:text-gray-400">{{
                        t('settings.categories.isActiveHint')
                    }}</span>
                </label>
            </div>
        </FormModal>

        <ConfirmDialog
            :open="removing !== null"
            title="settings.categories.deleteTitle"
            :message="
                t('settings.categories.deleteMessage', {
                    name: removing?.name_lo ?? '',
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
import { firstError } from '@/api/client';
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
import type { ExpenseCategory } from '@/types/settings';
import FormIcon from '~icons/tabler/category';

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
} = useCrud<ExpenseCategory & Record<string, unknown>>('/categories', 'id', {
    describe: (category) => category.name_lo,
});

/**
 * ຂຽນໄດ້ = ເປັນ full_access ຫຼື modify ຢູ່ໂຄງການໃດໜຶ່ງ. server ຄິດໃຫ້ແລ້ວ
 * ສົ່ງມາກັບ /user — ຜູ້ດູແລລະບົບບໍ່ໄດ້ຖືກຍົກເວັ້ນ (ເບິ່ງ MasterDataPolicy).
 */
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

// include_inactive: ໜ້ານີ້ຄືບ່ອນດຽວທີ່ເປີດໝວດທີ່ຢຸດໃຊ້ແລ້ວກັບມາໄດ້
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

/**
 * ຮຽງໃຫ້ໝວດລູກຢູ່ໃຕ້ແມ່ຂອງຕົນ. server ຮຽງຕາມ sort_order ແບບຮາບແລ້ວ ຈຶ່ງຈັດ
 * ຕົ້ນໄມ້ຢູ່ນີ້. ລູກທີ່ແມ່ບໍ່ຢູ່ໃນລາຍການ (ຖືກ search ຕັດອອກ) ຕໍ່ທ້າຍໄວ້ —
 * ບໍ່ດັ່ງນັ້ນມັນຈະຫາຍໄປທັງທີ່ກົງກັບຄຳຄົ້ນຫາ.
 */
const ordered = computed<ExpenseCategory[]>(() => {
    const roots = items.value.filter((c) => c.parent_id === null);
    const ids = new Set(items.value.map((c) => c.id));
    const result: ExpenseCategory[] = [];

    for (const root of roots) {
        result.push(root);
        result.push(...items.value.filter((c) => c.parent_id === root.id));
    }

    const orphans = items.value.filter(
        (c) => c.parent_id !== null && !ids.has(c.parent_id),
    );

    return [...result, ...orphans];
});

/** ມີລາຍຈ່າຍ ຫຼື ໝວດລູກຢູ່ນຳ = ລຶບບໍ່ໄດ້ (server ກໍປະຕິເສດດ້ວຍ 409) */
function isDeletable(category: ExpenseCategory): boolean {
    return (
        (category.expenses_count ?? 0) === 0 &&
        (category.children_count ?? 0) === 0
    );
}

function deleteHint(category: ExpenseCategory): string {
    if (isDeletable(category)) {
        return t('common.delete');
    }

    return (category.expenses_count ?? 0) > 0
        ? t('settings.categories.inUseHint', {
              count: category.expenses_count ?? 0,
          })
        : t('settings.categories.hasChildrenHint');
}

// ---------- ຕາຕະລາງ ----------

const getRowId = (params: { data: ExpenseCategory }) => String(params.data.id);

const columnDefs = computed<ColDef[]>(() => [
    {
        field: 'name_lo',
        headerName: t('settings.categories.name'),
        flex: 2,
        minWidth: 200,
        cellRenderer: TwoLineCell,
        cellRendererParams: {
            secondary: (params: ICellRendererParams<ExpenseCategory>) =>
                params.data?.name_en ?? null,
            depth: (params: ICellRendererParams<ExpenseCategory>) =>
                params.data?.parent_id ? 1 : 0,
            dotColor: (params: ICellRendererParams<ExpenseCategory>) =>
                params.data?.color ?? null,
        },
        // `ordered` ວາງໝວດລູກໄວ້ໃຕ້ແມ່ຂອງມັນ — ຮຽງໃໝ່ຈະແຍກສອງອັນອອກຈາກກັນ
        // ແລ້ວການຍໍ້ໜ້າຈະຊີ້ໄປຫາແຖວທີ່ບໍ່ກ່ຽວຂ້ອງ
        sortable: false,
    },
    {
        field: 'code',
        headerName: t('settings.categories.code'),
        width: 150,
        cellClass: 'font-mono text-theme-xs',
    },
    {
        field: 'color',
        headerName: t('settings.categories.color'),
        width: 120,
        cellClass: 'font-mono text-theme-xs',
    },
    {
        field: 'sort_order',
        headerName: t('settings.categories.sortOrder'),
        width: 110,
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
    },
    {
        field: 'expenses_count',
        headerName: t('settings.categories.usedBy'),
        width: 110,
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
        valueFormatter: (params) => params.value ?? '—',
    },
    {
        field: 'is_active',
        headerName: t('settings.categories.isActive'),
        width: 130,
        cellRenderer: BadgeCell,
        cellRendererParams: {
            color: (params: ICellRendererParams<ExpenseCategory>) =>
                params.data?.is_active ? 'success' : 'light',
        },
        valueGetter: (params) =>
            t(
                params.data?.is_active
                    ? 'settings.categories.active'
                    : 'settings.categories.inactive',
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
                      actions: (
                          params: ICellRendererParams<ExpenseCategory>,
                      ) => {
                          const category = params.data;

                          if (!category) {
                              return [];
                          }

                          return [
                              {
                                  label: t('common.edit'),
                                  onClick: () => openEdit(category),
                              },
                              {
                                  label: t('common.delete'),
                                  danger: true,
                                  disabled: !isDeletable(category),
                                  title: deleteHint(category),
                                  onClick: () => confirmRemove(category),
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
const editing = ref<ExpenseCategory | null>(null);
const removing = ref<ExpenseCategory | null>(null);

const blankForm = {
    code: '',
    name_lo: '',
    name_en: '',
    parent_id: '',
    color: '#6366f1',
    sort_order: '0',
    is_active: true,
};

const form = reactive({ ...blankForm });

/** ຊ່ອງສີເປັນ input ດິບ ບໍ່ໄດ້ຜ່ານ FormField ຈຶ່ງດຶງ error ເອງ */
const colorError = computed(() =>
    error.value ? firstError(error.value.errors, 'color') : null,
);

/**
 * ຮອງຮັບແມ່-ລູກຊັ້ນດຽວ: ເລືອກເປັນແມ່ໄດ້ສະເພາະໝວດຊັ້ນເທິງ ແລະ ບໍ່ແມ່ນຕົນເອງ.
 * ຖ້າປ່ອຍໃຫ້ເລືອກໝວດລູກເປັນແມ່ຈະໄດ້ຕົ້ນໄມ້ເລິກກວ່າທີ່ຕາຕະລາງສະແດງໄດ້.
 */
const parentOptions = computed(() =>
    items.value
        .filter(
            (category) =>
                category.parent_id === null &&
                category.id !== editing.value?.id,
        )
        .map((category) => ({
            value: String(category.id),
            label: `${category.name_lo} — ${category.code}`,
            translate: false,
        })),
);

function openCreate(): void {
    clearError();
    editing.value = null;
    Object.assign(form, blankForm);
    modalOpen.value = true;
}

function openEdit(category: ExpenseCategory): void {
    clearError();
    editing.value = category;
    Object.assign(form, {
        ...blankForm,
        ...category,
        name_en: category.name_en ?? '',
        parent_id:
            category.parent_id === null ? '' : String(category.parent_id),
        sort_order: String(category.sort_order),
    });
    modalOpen.value = true;
}

function closeModal(): void {
    modalOpen.value = false;
    clearError();
}

async function submit(): Promise<void> {
    const payload = {
        code: form.code,
        name_lo: form.name_lo,
        name_en: form.name_en || null,
        // "" = ບໍ່ມີແມ່ → null ບໍ່ດັ່ງນັ້ນຈະຕົກ validation `integer` ຢູ່ server
        parent_id: form.parent_id === '' ? null : Number(form.parent_id),
        color: form.color,
        sort_order: Number(form.sort_order) || 0,
        is_active: form.is_active,
    };

    if (await save(payload, editing.value)) {
        closeModal();
    }
}

function confirmRemove(category: ExpenseCategory): void {
    clearError();
    removing.value = category;
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
