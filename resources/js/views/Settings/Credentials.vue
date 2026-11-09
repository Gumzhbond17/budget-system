<template>
    <AdminLayout>
        <SettingsPage
            v-model:search="search"
            title="settings.credentials.title"
            :icon="FormIcon"
            description="settings.credentials.description"
            :error="listError"
            :can-create="true"
            create-label="settings.credentials.create"
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
                    ? 'settings.credentials.edit'
                    : 'settings.credentials.create'
            "
            subtitle="settings.credentials.formHint"
            :saving="saving"
            :error="error"
            @close="closeModal"
            @submit="submit"
        >
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <TextField
                        v-model="form.title"
                        label="settings.credentials.titleField"
                        name="title"
                        :error="error"
                        hint="settings.credentials.titleHint"
                        required
                    />
                </div>

                <div class="sm:col-span-2">
                    <TextField
                        v-model="form.url_link"
                        label="settings.credentials.urlLink"
                        name="url_link"
                        :error="error"
                        hint="settings.credentials.urlLinkHint"
                    />
                </div>

                <TextField
                    v-model="form.username"
                    label="settings.credentials.username"
                    name="username"
                    :error="error"
                />

                <!--
                    ຊ່ອງລະຫັດ + ປຸ່ມສະຫຼັບການເບິ່ງ.
                    ປຸ່ມຢູ່ລຸ່ມຊ່ອງ ບໍ່ແມ່ນລອຍທັບຢູ່ໃນຊ່ອງ — ບໍ່ດັ່ງນັ້ນມັນຈະ
                    ບັງຂໍ້ຄວາມທ້າຍລະຫັດທີ່ຍາວ ເຊິ່ງແມ່ນຕອນທີ່ຕ້ອງເບິ່ງທີ່ສຸດ.
                -->
                <div>
                    <TextField
                        v-model="form.password"
                        label="settings.credentials.password"
                        name="password"
                        :type="showFormPassword ? 'text' : 'password'"
                        :error="error"
                        :hint="
                            editing
                                ? 'settings.credentials.passwordEditHint'
                                : undefined
                        "
                        :required="!editing"
                    />

                    <button
                        type="button"
                        class="mt-1.5 text-theme-xs font-medium text-brand-500 hover:underline"
                        @click="showFormPassword = !showFormPassword"
                    >
                        {{
                            showFormPassword
                                ? t('auth.hidePassword')
                                : t('auth.showPassword')
                        }}
                    </button>
                </div>

                <TextField
                    v-model="form.sort_order"
                    label="settings.credentials.sortOrder"
                    name="sort_order"
                    type="number"
                    :error="error"
                    hint="settings.credentials.sortOrderHint"
                />

                <div class="sm:col-span-2">
                    <TextField
                        v-model="form.note"
                        label="settings.credentials.note"
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
                        t('settings.credentials.isActiveHint')
                    }}</span>
                </label>
            </div>
        </FormModal>

        <RevealPasswordDialog
            :open="revealing !== null"
            :title="revealing?.title ?? ''"
            :saving="revealSaving"
            :error="revealError"
            @cancel="cancelReveal"
            @confirm="doReveal"
        />

        <ConfirmDialog
            :open="removing !== null"
            title="settings.credentials.deleteTitle"
            :message="
                t('settings.credentials.deleteMessage', {
                    name: removing?.title ?? '',
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
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { api, toApiError } from '@/api/client';
import DataGrid from '@/components/common/DataGrid.vue';
import ActionsCell from '@/components/grid/ActionsCell.vue';
import BadgeCell from '@/components/grid/BadgeCell.vue';
import SecretCell from '@/components/grid/SecretCell.vue';
import TwoLineCell from '@/components/grid/TwoLineCell.vue';
import AdminLayout from '@/components/layout/AdminLayout.vue';
import ConfirmDialog from '@/components/settings/ConfirmDialog.vue';
import FormModal from '@/components/settings/FormModal.vue';
import RevealPasswordDialog from '@/components/settings/RevealPasswordDialog.vue';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import TextField from '@/components/settings/TextField.vue';
import { useAutoRefresh } from '@/composables/useAutoRefresh';
import { useCrud } from '@/composables/useCrud';
import { useDebouncedSearch } from '@/composables/useDebouncedSearch';
import { actionColDef } from '@/lib/ag-grid';
import type { Credential } from '@/types/settings';
import { formatDateTime } from '@/utils/date';
import FormIcon from '~icons/tabler/key';

type Row = Credential & Record<string, unknown>;

const { t } = useI18n();

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
} = useCrud<Row>('/credentials', 'uuid', {
    describe: (credential) => credential.title,
});

// ບໍ່ມີ `canWrite`: ໜ້ານີ້ເປີດໄດ້ສະເພາະຜູ້ດູແລລະບົບ (router ເຕະຄົນອື່ນອອກ
// ແລະ CredentialPolicy ຕອບ 403) ຈຶ່ງບໍ່ມີສະຖານະ "ເຫັນແຕ່ແກ້ບໍ່ໄດ້" ຄື
// ໜ້າຂໍ້ມູນຫຼັກອື່ນ — ໃຜເຫັນຕາຕະລາງນີ້ໄດ້ ແປວ່າແກ້ໄດ້ໝົດ.

const search = ref('');

const listError = computed(() =>
    loading.value || modalOpen.value || removing.value !== null
        ? null
        : (error.value?.message ?? null),
);

const removeError = computed(() =>
    removing.value === null ? null : (error.value?.message ?? null),
);

// ---------- ລະຫັດທີ່ເປີດເຜີຍຢູ່ ----------

/**
 * ເວລາທີ່ລະຫັດຢູ່ໜ້າຈໍກ່ອນຈະກັບເປັນຈຸດເອງ.
 *
 * ມີເພື່ອກັນກໍລະນີທີ່ພົບຈິງທີ່ສຸດ: ເປີດເບິ່ງ ແລ້ວລຸກໜີໄປໂດຍລືມປິດ. 1 ນາທີ
 * ພໍໃຫ້ອ່ານ/ພິມຕໍ່/ສຳເນົາ ແຕ່ບໍ່ພໍໃຫ້ຄ້າງຢູ່ໜ້າຈໍຈົນຄົນຍ່າງຜ່ານມາເຫັນ.
 */
const REVEAL_TTL_MS = 60_000;

/**
 * uuid → ລະຫັດຈິງ. ເກັບຢູ່ໜ້ານີ້ບ່ອນດຽວ **ບໍ່ແມ່ນໃນແຖວຂອງຕາຕະລາງ** —
 * ຖ້າຂຽນໃສ່ `items` ລະຫັດຈະຖືກລ້າງຫາຍທຸກເທື່ອທີ່ໂຫຼດຄືນອັດຕະໂນມັດ ແລະ
 * ຈະໂຜ່ຢູ່ໃນ Vue devtools ພ້ອມກັບຂໍ້ມູນແຖວທັງໝົດ.
 */
const revealed = reactive<Record<string, string>>({});

/** ຕົວຈັບເວລາປິດອັດຕະໂນມັດ ຕໍ່ 1 ແຖວ */
const hideTimers = new Map<string, number>();

/** ແຖວທີ່ກຳລັງຖາມລະຫັດຜ່ານຢູ່ — null = ກ່ອງປິດ */
const revealing = ref<Credential | null>(null);
const revealSaving = ref(false);
const revealError = ref<string | null>(null);

function secretOf(credential: Credential): string | null {
    return revealed[credential.uuid] ?? null;
}

function hide(credential: Credential): void {
    delete revealed[credential.uuid];

    const timer = hideTimers.get(credential.uuid);

    if (timer !== undefined) {
        window.clearTimeout(timer);
        hideTimers.delete(credential.uuid);
    }
}

function askForPassword(credential: Credential): void {
    revealError.value = null;
    revealing.value = credential;
}

function cancelReveal(): void {
    revealing.value = null;
    revealError.value = null;
}

/**
 * ຂໍລະຫັດຈິງຈາກ server ພ້ອມລະຫັດຜ່ານຂອງຜູ້ໃຊ້ເອງ.
 *
 * server ຢືນຢັນລະຫັດຜ່ານທຸກເທື່ອ (ບໍ່ມີ "ປົດລັອກ 5 ນາທີ") ແລະ ບັນທຶກ
 * ທຸກຄັ້ງລົງ activity_logs — ໜ້ານີ້ຈຶ່ງບໍ່ຄວນ cache ຄຳຕອບໄວ້ຂ້າມແຖວ.
 */
async function doReveal(password: string): Promise<void> {
    const target = revealing.value;

    if (!target) {
        return;
    }

    revealSaving.value = true;
    revealError.value = null;

    try {
        const { data } = await api.post<{ data: { password: string } }>(
            `/credentials/${target.uuid}/reveal`,
            { password },
        );

        revealed[target.uuid] = data.data.password;

        const timer = hideTimers.get(target.uuid);

        if (timer !== undefined) {
            window.clearTimeout(timer);
        }

        hideTimers.set(
            target.uuid,
            window.setTimeout(() => hide(target), REVEAL_TTL_MS),
        );

        revealing.value = null;
    } catch (e) {
        const apiError = toApiError(e);

        revealError.value = apiError.errors.password?.[0] ?? apiError.message;
    } finally {
        revealSaving.value = false;
    }
}

/** ອອກຈາກໜ້າ = ລະຫັດຫາຍໝົດ ແລະ ບໍ່ມີຕົວຈັບເວລາຄ້າງຢູ່ */
onBeforeUnmount(() => {
    hideTimers.forEach((timer) => window.clearTimeout(timer));
    hideTimers.clear();
});

// ---------- ຕາຕະລາງ ----------

const getRowId = (params: { data: Credential }) => params.data.uuid;

const columnDefs = computed<ColDef[]>(() => [
    {
        colId: 'index',
        headerName: t('settings.credentials.index'),
        width: 80,
        sortable: false,
        filter: false,
        floatingFilter: false,
        cellClass: 'ag-cell-numeric text-gray-400',
        headerClass: 'ag-right-aligned-header',
        // ຕາມລຳດັບທີ່ **ເຫັນຢູ່ໜ້າຈໍ** ບໍ່ແມ່ນ id ໃນຖານຂໍ້ມູນ — ຈຶ່ງຍັງນັບ
        // 1, 2, 3 ຢູ່ ຫຼັງຈາກຄົ້ນຫາ ຫຼື ຮຽງຄືນໃໝ່
        valueGetter: (params) => (params.node?.rowIndex ?? 0) + 1,
    },
    {
        field: 'title',
        headerName: t('settings.credentials.titleField'),
        flex: 2,
        minWidth: 180,
        cellRenderer: TwoLineCell,
        cellRendererParams: {
            secondary: (params: ICellRendererParams<Credential>) =>
                params.data?.note ?? null,
        },
    },
    {
        field: 'url_link',
        headerName: t('settings.credentials.urlLink'),
        flex: 2,
        minWidth: 180,
        cellClass: 'text-gray-500 dark:text-gray-400',
        valueFormatter: (params) => params.value ?? '—',
    },
    {
        field: 'username',
        headerName: t('settings.credentials.username'),
        flex: 1,
        minWidth: 150,
        cellClass: 'font-mono',
        valueFormatter: (params) => params.value ?? '—',
    },
    {
        colId: 'password',
        headerName: t('settings.credentials.password'),
        flex: 1,
        minWidth: 190,
        // ຮຽງ/ກັ່ນຕອງບໍ່ໄດ້: ຄ່າທີ່ຢູ່ໃນແຖວຄືຈຸດ ບໍ່ແມ່ນລະຫັດ — ການປ່ອຍໃຫ້
        // ກັ່ນຕອງໄດ້ຈະສ້າງຄວາມເຂົ້າໃຈຜິດວ່າພິມລະຫັດຄົ້ນຫາໄດ້
        sortable: false,
        filter: false,
        floatingFilter: false,
        cellRenderer: SecretCell,
        cellRendererParams: {
            secretOf,
            onReveal: askForPassword,
            onHide: hide,
        },
    },
    {
        colId: 'lastRevealed',
        headerName: t('settings.credentials.lastRevealed'),
        width: 150,
        filter: false,
        cellClass: 'text-gray-500 dark:text-gray-400',
        valueGetter: (params) =>
            params.data?.last_revealed_at
                ? formatDateTime(params.data.last_revealed_at)
                : t('settings.credentials.neverRevealed'),
    },
    {
        field: 'is_active',
        headerName: t('settings.credentials.isActive'),
        width: 130,
        cellRenderer: BadgeCell,
        cellRendererParams: {
            color: (params: ICellRendererParams<Credential>) =>
                params.data?.is_active ? 'success' : 'light',
        },
        valueGetter: (params) =>
            t(
                params.data?.is_active
                    ? 'settings.partyStatus.active'
                    : 'settings.partyStatus.inactive',
            ),
    },
    {
        ...actionColDef,
        colId: 'actions',
        headerName: t('common.actions'),
        width: 140,
        cellRenderer: ActionsCell,
        cellRendererParams: {
            actions: (params: ICellRendererParams<Credential>) => {
                const credential = params.data;

                if (!credential) {
                    return [];
                }

                return [
                    {
                        label: t('common.edit'),
                        onClick: () => openEdit(credential),
                    },
                    {
                        label: t('common.delete'),
                        danger: true,
                        onClick: () => confirmRemove(credential),
                    },
                ];
            },
        },
    },
]);

// include_inactive: ໜ້ານີ້ຄືບ່ອນດຽວທີ່ເປີດແຖວທີ່ຢຸດໃຊ້ແລ້ວກັບມາໄດ້
useDebouncedSearch(search, (term) =>
    fetchAll(
        term
            ? { search: term, include_inactive: true }
            : { include_inactive: true },
    ),
);

// ຢຸດຕອນກ່ອງໃດໜຶ່ງເປີດ — ດຶງທັບຕອນນັ້ນຈະເຮັດໃຫ້ແຖວທີ່ກຳລັງແກ້ຫາຍໄປ
useAutoRefresh(refresh, {
    paused: () =>
        modalOpen.value || removing.value !== null || revealing.value !== null,
});

// ---------- ຟອມ ----------
const modalOpen = ref(false);
const editing = ref<Credential | null>(null);
const removing = ref<Credential | null>(null);
const showFormPassword = ref(false);

const blankForm = {
    title: '',
    url_link: '',
    username: '',
    password: '',
    note: '',
    sort_order: '0',
    is_active: true,
};

const form = reactive({ ...blankForm });

function openCreate(): void {
    clearError();
    editing.value = null;
    showFormPassword.value = false;
    Object.assign(form, blankForm);
    modalOpen.value = true;
}

/**
 * ຊ່ອງລະຫັດເປີດມາຫວ່າງສະເໝີຕອນແກ້ໄຂ — ເຕີມຄືນບໍ່ໄດ້ເພາະໜ້ານີ້ບໍ່ເຄີຍໄດ້
 * ຮັບຄ່າເກົ່າມາ (ຫຼັກການດຽວກັບ Payees.vue ກັບ bank_account_no). ປະຫວ່າງ
 * ໄວ້ = ບໍ່ປ່ຽນ, ພິມໃສ່ = ປ່ຽນ.
 */
function openEdit(credential: Credential): void {
    clearError();
    editing.value = credential;
    showFormPassword.value = false;
    Object.assign(form, {
        ...blankForm,
        title: credential.title,
        url_link: credential.url_link ?? '',
        username: credential.username ?? '',
        note: credential.note ?? '',
        sort_order: String(credential.sort_order),
        is_active: credential.is_active,
    });
    modalOpen.value = true;
}

function closeModal(): void {
    modalOpen.value = false;
    // ລະຫັດທີ່ພິມຄ້າງໄວ້ຫ້າມຢູ່ຕໍ່ຫຼັງປິດຟອມ
    form.password = '';
    showFormPassword.value = false;
    clearError();
}

async function submit(): Promise<void> {
    const payload: Record<string, unknown> = {
        title: form.title,
        // "" → null ບໍ່ດັ່ງນັ້ນຈະເກັບ string ຫວ່າງແທນທີ່ຈະເປັນ "ບໍ່ໄດ້ລະບຸ"
        url_link: form.url_link || null,
        username: form.username || null,
        note: form.note || null,
        sort_order: Number(form.sort_order) || 0,
        is_active: form.is_active,
    };

    // ຕອນແກ້ໄຂ: ສົ່ງຊ່ອງລະຫັດໄປສະເພາະເມື່ອພິມໃສ່ຈິງ (server ກໍຖອດຄ່າຫວ່າງ
    // ອອກໃຫ້ອີກຊັ້ນ). ຕອນສ້າງ: ສົ່ງສະເໝີ ເພື່ອໃຫ້ 422 ຂຶ້ນຢູ່ຊ່ອງທີ່ຖືກຕ້ອງ
    if (!editing.value || form.password !== '') {
        payload.password = form.password;
    }

    const saved = await save(payload, editing.value as Row | null);

    if (!saved) {
        return;
    }

    // ລະຫັດຖືກປ່ຽນ → ຄ່າທີ່ເປີດເຜີຍຢູ່ໜ້າຈໍເປັນຂອງເກົ່າແລ້ວ ຕ້ອງເຊື່ອງຖິ້ມ
    // ບໍ່ດັ່ງນັ້ນຜູ້ໃຊ້ຈະສຳເນົາລະຫັດເກົ່າໄປໃຊ້ໂດຍບໍ່ຮູ້ຕົວ
    if (form.password !== '') {
        hide(saved);
    }

    closeModal();
}

function confirmRemove(credential: Credential): void {
    clearError();
    removing.value = credential;
}

function cancelRemove(): void {
    removing.value = null;
    clearError();
}

async function doRemove(): Promise<void> {
    const target = removing.value;

    if (target && (await remove(target as Row))) {
        hide(target);
        removing.value = null;
    }
}
</script>
