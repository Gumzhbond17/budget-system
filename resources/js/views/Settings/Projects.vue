<template>
    <AdminLayout>
        <SettingsPage
            v-model:search="search"
            title="settings.projects.title"
            :icon="FormIcon"
            description="settings.projects.description"
            :error="listError"
            :can-create="auth.isPlatformAdmin"
            create-label="settings.projects.create"
            @create="openCreate"
        >
            <!--
        ຂັ້ນທີ 1 ຂອງຂັ້ນຕອນ: ເລືອກອົງກອນກ່ອນ ແລ້ວລາຍການລຸ່ມນີ້ຈຶ່ງແຄບລົງ
        ເຫຼືອສະເພາະໂຄງການຂອງອົງກອນນັ້ນ.
      -->
            <template #filters>
                <SearchableSelect
                    v-model="companyFilter"
                    variant="filter"
                    class="sm:w-64"
                    :aria-label="t('settings.projects.filterByCompany')"
                    :options="companyOptions"
                    nullable
                    null-label="settings.projects.allCompanies"
                />
            </template>

            <DataGrid
                :column-defs="columnDefs"
                :row-data="items"
                :loading="loading"
                :get-row-id="getRowId"
                :row-height="60"
                :empty-label="
                    companyFilter
                        ? t('settings.projects.emptyInCompany')
                        : t('settings.empty')
                "
                :suppress-cell-focus="false"
                @cell-editing-started="inlineEditing = true"
                @cell-editing-stopped="inlineEditing = false"
            />
        </SettingsPage>

        <FormModal
            :open="modalOpen"
            :icon="FormIcon"
            :title="
                editing ? 'settings.projects.edit' : 'settings.projects.create'
            "
            subtitle="settings.projects.formHint"
            :saving="saving"
            :error="error"
            @close="closeModal"
            @submit="submit"
        >
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <SelectField
                        v-model="form.company_uuid"
                        label="settings.projects.company"
                        name="company_uuid"
                        :error="error"
                        :options="companyOptions"
                        placeholder="settings.projects.selectCompany"
                        :disabled="editing !== null"
                        :hint="
                            editing
                                ? 'settings.projects.companyLocked'
                                : undefined
                        "
                        required
                    />
                </div>

                <TextField
                    v-model="form.code"
                    label="settings.projects.code"
                    name="code"
                    :error="error"
                    required
                    hint="settings.projects.codeHint"
                />
                <TextField
                    v-model="form.name"
                    label="settings.projects.name"
                    name="name"
                    :error="error"
                    required
                />

                <TextField
                    v-model="form.location"
                    label="settings.projects.location"
                    name="location"
                    :error="error"
                />
                <TextField
                    v-model="form.capacity_kw"
                    type="number"
                    label="settings.projects.capacity"
                    name="capacity_kw"
                    :error="error"
                />

                <DatePicker
                    v-model="form.start_date"
                    label="settings.projects.startDate"
                    name="start_date"
                    :error="error"
                />
                <DatePicker
                    v-model="form.end_date"
                    label="settings.projects.endDate"
                    name="end_date"
                    :error="error"
                />

                <SelectField
                    v-model="form.budget_currency_code"
                    label="settings.projects.budgetCurrency"
                    name="budget_currency_code"
                    :error="error"
                    :options="currencyOptions"
                />

                <NumericField
                    v-model="form.budget_amount"
                    label="settings.projects.budgetAmount"
                    name="budget_amount"
                    :decimals="budgetDecimals"
                    :error="error"
                    hint="settings.projects.budgetHint"
                />

                <SelectField
                    v-model="form.status"
                    label="settings.projects.status"
                    name="status"
                    :error="error"
                    :options="statusOptions"
                />

                <div class="sm:col-span-2">
                    <TextField
                        v-model="form.description"
                        label="settings.projects.description"
                        name="description"
                        :error="error"
                    />
                </div>
            </div>
        </FormModal>

        <ProjectMembersModal
            v-if="managing"
            :project="managing"
            @close="onMembersClosed"
        />

        <ConfirmDialog
            :open="removing !== null"
            title="settings.projects.deleteTitle"
            :message="
                t('settings.projects.deleteMessage', {
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
import type {
    ColDef,
    ICellRendererParams,
    ValueSetterParams,
} from 'ag-grid-community';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { api } from '@/api/client';
import DataGrid from '@/components/common/DataGrid.vue';
import SearchableSelect from '@/components/common/SearchableSelect.vue';
import DatePicker from '@/components/forms/DatePicker.vue';
import NumericField from '@/components/forms/NumericField.vue';
import ActionsCell from '@/components/grid/ActionsCell.vue';
import BadgeCell from '@/components/grid/BadgeCell.vue';
import MoneyCellEditor from '@/components/grid/MoneyCellEditor.vue';
import TwoLineCell from '@/components/grid/TwoLineCell.vue';
import AdminLayout from '@/components/layout/AdminLayout.vue';
import ConfirmDialog from '@/components/settings/ConfirmDialog.vue';
import FormModal from '@/components/settings/FormModal.vue';
import ProjectMembersModal from '@/components/settings/ProjectMembersModal.vue';
import SelectField from '@/components/settings/SelectField.vue';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import TextField from '@/components/settings/TextField.vue';
import { useAutoRefresh } from '@/composables/useAutoRefresh';
import { useCrud } from '@/composables/useCrud';
import { useDebouncedSearch } from '@/composables/useDebouncedSearch';
import { actionColDef } from '@/lib/ag-grid';
import { useAuthStore } from '@/stores/auth';
import type { Company, Project } from '@/types/settings';
import { formatMoney } from '@/utils/money';
import FormIcon from '~icons/tabler/solar-panel-2';
import MembersIcon from '~icons/tabler/users';

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
} = useCrud<Project & Record<string, unknown>>('/projects', 'uuid', {
    describe: (project) => project.name,
});

const search = ref('');
const companyFilter = ref('');
const companies = ref<Company[]>([]);
const currencies = ref<
    { code: string; name_lo: string; decimal_places: number }[]
>([]);

const modalOpen = ref(false);
const editing = ref<Project | null>(null);
const removing = ref<Project | null>(null);
const managing = ref<Project | null>(null);

const listError = computed(() =>
    loading.value || modalOpen.value ? null : (error.value?.message ?? null),
);

function load(): void {
    const params: Record<string, string> = {};

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

/** ກຳລັງແກ້ຊ່ອງໃດໜຶ່ງໃນຕາຕະລາງຢູ່ */
const inlineEditing = ref(false);

// ຢຸດຕອນຟອມ/ກ່ອງຢືນຢັນ/ໜ້າຕ່າງສະມາຊິກເປີດ ຫຼື ກຳລັງແກ້ຊ່ອງໃນຕາຕະລາງ —
// ດຶງທັບຕອນນັ້ນຈະເຮັດໃຫ້ແຖວທີ່ກຳລັງແກ້ຫາຍໄປ
useAutoRefresh(refresh, {
    paused: () =>
        modalOpen.value ||
        inlineEditing.value ||
        removing.value !== null ||
        managing.value !== null,
});

// ຕົວເລືອກອົງກອນ ແລະ ສະກຸນເງິນ — ໂຫຼດເທື່ອດຽວຕອນເປີດໜ້າ
void (async () => {
    const [companyRes, currencyRes] = await Promise.all([
        api.get<{ data: Company[] }>('/companies'),
        api.get<{
            data: { code: string; name_lo: string; decimal_places: number }[];
        }>('/currencies'),
    ]);

    companies.value = companyRes.data.data;
    currencies.value = currencyRes.data.data;
})();

const companyOptions = computed(() =>
    companies.value.map((company) => ({
        value: company.uuid,
        label: company.name,
        translate: false,
    })),
);

const currencyOptions = computed(() =>
    currencies.value.map((currency) => ({
        value: currency.code,
        label: `${currency.code} — ${currency.name_lo}`,
        translate: false,
    })),
);

/** ຫຼັກທົດສະນິຍົມຂອງສະກຸນງົບປະມານ — LAK = 0, USD/THB = 2 */
const budgetDecimals = computed(
    () =>
        currencies.value.find((c) => c.code === form.budget_currency_code)
            ?.decimal_places ?? 2,
);

const statusOptions = [
    { value: 'planning', label: 'settings.projectStatus.planning' },
    { value: 'active', label: 'settings.projectStatus.active' },
    { value: 'on_hold', label: 'settings.projectStatus.on_hold' },
    { value: 'completed', label: 'settings.projectStatus.completed' },
    { value: 'cancelled', label: 'settings.projectStatus.cancelled' },
];

function statusColor(status: Project['status']) {
    return (
        {
            planning: 'light',
            active: 'success',
            on_hold: 'warning',
            completed: 'info',
            cancelled: 'error',
        } as const
    )[status];
}

/** ຜູ້ດູແລລະບົບ ຫຼື ຄົນທີ່ມີສິດ full_access ໃນໂຄງການນັ້ນ (server ບັງຄັບຊ້ຳ) */
function canEdit(project: Project): boolean {
    return auth.isPlatformAdmin || project.my_role === 'full_access';
}

// ---------- ຕາຕະລາງ ----------

const getRowId = (params: { data: Project }) => params.data.uuid;

/** ຊ່ອງທີ່ແກ້ໄດ້ໃນຕາຕະລາງ → ຊື່ field ທີ່ API ຮັບ */
type InlineField = 'name' | 'status' | 'budget_amount';

/** ຄ່າປັດຈຸບັນຂອງແຖວ ໃນຮູບແບບດຽວກັບທີ່ຈະສົ່ງ — ໃຊ້ຂ້າມການບັນທຶກທີ່ບໍ່ໄດ້ປ່ຽນຫຍັງ */
function currentValue(project: Project, field: InlineField): string {
    if (field === 'budget_amount') {
        return project.budget.minor === 0 ? '' : project.budget.decimal;
    }

    return project[field];
}

/**
 * ບັນທຶກຊ່ອງດຽວທີ່ຫາກໍ່ແກ້ໃນຕາຕະລາງ — PATCH ສະເພາະ field ນັ້ນ.
 *
 * ບໍ່ແກ້ຂໍ້ມູນແຖວໃນ grid ເອງ (`valueSetter` ຄືນ false): ແຖວປ່ຽນກໍຕໍ່ເມື່ອ
 * server ຕອບກັບມາແລ້ວ ຜ່ານ `save()` ທີ່ແທນແຖວດ້ວຍສະບັບຈາກ server. ຖ້າລົ້ມ
 * (422, 403) ຊ່ອງກໍກັບເປັນຄ່າເດີມເອງ ແລະ ຂໍ້ຄວາມຂຶ້ນຢູ່ແຖບເທິງຕາຕະລາງ —
 * ບໍ່ມີຊ່ວງທີ່ໜ້າຈໍສະແດງຄ່າທີ່ບໍ່ໄດ້ຖືກບັນທຶກຈິງ.
 */
async function saveInline(
    project: Project,
    field: InlineField,
    value: unknown,
): Promise<void> {
    const next = String(value ?? '').trim();
    const comparable =
        field === 'budget_amount' ? next.replace(/,/g, '') : next;

    if (comparable === currentValue(project, field)) {
        return;
    }

    await save(
        { [field]: next === '' ? null : next },
        project as Project & Record<string, unknown>,
    );
}

/** ສິດແກ້ໄຂແຖວ — ກົດເກນດຽວກັບປຸ່ມ "ແກ້ໄຂ" (server ບັງຄັບຊ້ຳຜ່ານ Policy) */
const editableRow = (params: { data?: Project }) =>
    params.data !== undefined && canEdit(params.data);

function inlineEdit(field: InlineField): ColDef<Project> {
    return {
        editable: editableRow,
        cellClassRules: { 'ag-cell-editable': editableRow },
        valueSetter: (params: ValueSetterParams<Project>) => {
            void saveInline(params.data, field, params.newValue);

            return false;
        },
    };
}

const columnDefs = computed<ColDef[]>(() => [
    {
        field: 'name',
        headerName: t('settings.projects.name'),
        flex: 2,
        minWidth: 200,
        ...inlineEdit('name'),
        cellEditor: 'agTextCellEditor',
        cellEditorParams: { maxLength: 255 },
        cellRenderer: TwoLineCell,
        cellRendererParams: {
            secondary: (params: ICellRendererParams<Project>) => {
                const project = params.data;

                if (!project) {
                    return null;
                }

                return project.location
                    ? `${project.code} · ${project.location}`
                    : project.code;
            },
        },
    },
    {
        colId: 'company',
        headerName: t('settings.projects.company'),
        flex: 1,
        minWidth: 160,
        valueGetter: (params) => params.data?.company?.name ?? '—',
    },
    {
        field: 'status',
        headerName: t('settings.projects.status'),
        width: 140,
        ...inlineEdit('status'),
        // ຄ່າດິບ (planning, active…) ເປັນ value ເພື່ອໃຫ້ຕົວເລືອກຂອງ editor
        // ສົ່ງ enum ທີ່ API ຮັບ — ຄຳແປມາຈາກ valueFormatter ທັງໃນປ້າຍ ແລະ
        // ໃນລາຍການຕົວເລືອກ ສ່ວນຕົວກັ່ນຕອງຄົ້ນດ້ວຍຄຳແປ
        cellEditor: 'agSelectCellEditor',
        cellEditorParams: { values: statusOptions.map((o) => o.value) },
        cellRenderer: BadgeCell,
        cellRendererParams: {
            color: (params: ICellRendererParams<Project>) =>
                params.data ? statusColor(params.data.status) : 'light',
        },
        valueFormatter: (params) =>
            params.value ? t(`settings.projectStatus.${params.value}`) : '',
        filterValueGetter: (params) =>
            params.data
                ? t(`settings.projectStatus.${params.data.status}`)
                : '',
    },
    {
        colId: 'budget',
        headerName: t('settings.projects.budget'),
        width: 150,
        // ຮຽງ/ກັ່ນຕອງດ້ວຍ minor unit ບໍ່ແມ່ນຂໍ້ຄວາມທີ່ຈັດຮູບແບບແລ້ວ —
        // "1,000,000" ຮຽງແບບຂໍ້ຄວາມຈະມາກ່ອນ "9,000" ຢູ່ສະເໝີ
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
        ...inlineEdit('budget_amount'),
        cellEditor: MoneyCellEditor,
        cellEditorParams: { money: (data: Project) => data.budget },
        valueGetter: (params) => params.data?.budget?.minor ?? 0,
        valueFormatter: (params) =>
            formatMoney((params.data as Project | undefined)?.budget ?? null),
    },
    {
        field: 'members_count',
        headerName: t('settings.projects.members'),
        width: 120,
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
        valueFormatter: (params) => params.value ?? '—',
    },
    {
        ...actionColDef,
        colId: 'actions',
        headerName: t('common.actions'),
        // 3 ປຸ່ມໄອຄອນ (ສະມາຊິກ / ແກ້ / ລຶບ)
        width: 150,
        cellRenderer: ActionsCell,
        cellRendererParams: {
            actions: (params: ICellRendererParams<Project>) => {
                const project = params.data;

                if (!project) {
                    return [];
                }

                return [
                    {
                        label: t('settings.projects.manageMembers'),
                        icon: MembersIcon,
                        onClick: () => {
                            managing.value = project;
                        },
                    },
                    ...(canEdit(project)
                        ? [
                              {
                                  label: t('common.edit'),
                                  onClick: () => openEdit(project),
                              },
                          ]
                        : []),
                    ...(auth.isPlatformAdmin
                        ? [
                              {
                                  label: t('common.delete'),
                                  danger: true,
                                  onClick: () => confirmRemove(project),
                              },
                          ]
                        : []),
                ];
            },
        },
    },
]);

const blankForm = {
    company_uuid: '',
    code: '',
    name: '',
    description: '',
    location: '',
    capacity_kw: '',
    start_date: '',
    end_date: '',
    budget_currency_code: 'USD',
    budget_amount: '',
    status: 'planning',
};

const form = reactive({ ...blankForm });

function openCreate(): void {
    clearError();
    editing.value = null;
    Object.assign(form, blankForm, { company_uuid: companyFilter.value });
    modalOpen.value = true;
}

function openEdit(project: Project): void {
    clearError();
    editing.value = project;
    Object.assign(form, {
        ...blankForm,
        ...project,
        company_uuid: project.company?.uuid ?? '',
        description: project.description ?? '',
        location: project.location ?? '',
        capacity_kw: project.capacity_kw ?? '',
        start_date: project.start_date ?? '',
        end_date: project.end_date ?? '',
        budget_currency_code: project.budget.currency,
        // ສົ່ງກັບເປັນຂໍ້ຄວາມທົດສະນິຍົມ ບໍ່ແມ່ນ float — ຫ້າມໃຫ້ JS ແຕະຕົວເລກເງິນ
        budget_amount: project.budget.minor === 0 ? '' : project.budget.decimal,
    });
    modalOpen.value = true;
}

function closeModal(): void {
    modalOpen.value = false;
    clearError();
}

async function submit(): Promise<void> {
    const payload: Record<string, unknown> = Object.fromEntries(
        Object.entries({ ...form }).map(([key, value]) => [
            key,
            value === '' ? null : value,
        ]),
    );

    // ອົງກອນຂອງໂຄງການທີ່ມີຢູ່ແລ້ວປ່ຽນບໍ່ໄດ້ — ຍ້າຍໂຄງການຂ້າມອົງກອນຈະພາ
    // ລາຍຈ່າຍ ແລະ ສະມາຊິກເກົ່າຕິດໄປນຳ ເຊິ່ງເປັນການຮົ່ວຂໍ້ມູນຂ້າມລູກຄ້າ
    if (editing.value) {
        delete payload.company_uuid;
    }

    if (await save(payload, editing.value)) {
        closeModal();
    }
}

function confirmRemove(project: Project): void {
    clearError();
    removing.value = project;
}

async function doRemove(): Promise<void> {
    const target = removing.value;

    if (target && (await remove(target))) {
        removing.value = null;
    }
}

/** ຈຳນວນສະມາຊິກອາດປ່ຽນ — ໂຫຼດຄືນເພື່ອໃຫ້ຄໍລຳນັບຖືກຕ້ອງ */
function onMembersClosed(): void {
    managing.value = null;
    load();
}
</script>
