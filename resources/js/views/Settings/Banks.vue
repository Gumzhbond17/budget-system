<template>
    <AdminLayout>
        <SettingsPage
            v-model:search="search"
            title="settings.banks.title"
            :icon="FormIcon"
            description="settings.banks.description"
            :error="listError"
            :can-create="canWrite"
            create-label="settings.banks.create"
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
            :title="editing ? 'settings.banks.edit' : 'settings.banks.create'"
            subtitle="settings.banks.formHint"
            :saving="saving || uploadingLogo"
            :error="error"
            @close="closeModal"
            @submit="submit"
        >
            <div class="grid gap-5 sm:grid-cols-2">
                <TextField
                    v-model="form.code"
                    label="settings.banks.code"
                    name="code"
                    :error="error"
                    hint="settings.banks.codeHint"
                    required
                />

                <TextField
                    v-model="form.short_name"
                    label="settings.banks.shortName"
                    name="short_name"
                    :error="error"
                    hint="settings.banks.shortNameHint"
                />

                <div class="sm:col-span-2">
                    <TextField
                        v-model="form.name_lo"
                        label="settings.banks.nameLo"
                        name="name_lo"
                        :error="error"
                        required
                    />
                </div>

                <div class="sm:col-span-2">
                    <TextField
                        v-model="form.name_en"
                        label="settings.banks.nameEn"
                        name="name_en"
                        :error="error"
                    />
                </div>

                <TextField
                    v-model="form.swift_code"
                    label="settings.banks.swift"
                    name="swift_code"
                    :error="error"
                    hint="settings.banks.swiftHint"
                />

                <TextField
                    v-model="form.sort_order"
                    label="settings.banks.sortOrder"
                    name="sort_order"
                    type="number"
                    :error="error"
                    hint="settings.banks.sortOrderHint"
                />

                <!-- ---------- ໂລໂກ້ ---------- -->
                <!--
                    ໄຟລ໌ຖືກຖືໄວ້ໃນ browser ກ່ອນ ແລ້ວສົ່ງຫຼັງແຖວຖືກບັນທຶກ —
                    endpoint ໂລໂກ້ເປັນ multipart ແລະ ຕ້ອງການ id ຂອງທະນາຄານ
                    ເຊິ່ງແຖວໃໝ່ຍັງບໍ່ມີຈົນກວ່າຈະ POST ສຳເລັດ.
                -->
                <div class="sm:col-span-2">
                    <span
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        {{ t('settings.banks.logo') }}
                    </span>

                    <div class="flex items-center gap-4">
                        <!--
                            ຕົວຢ່າງກ່ອນບັນທຶກ — ໃຫຍ່ກວ່າໃນຕາຕະລາງ (64 px)
                            ເພື່ອໃຫ້ກວດໄດ້ວ່າເລືອກຮູບຖືກອັນບໍ. ບໍ່ໄດ້ຜູກກັບ
                            lightbox ໂດຍເຈດຕະນາ: ຢູ່ນີ້ຮູບອາດເປັນ object URL
                            ຂອງໄຟລ໌ທີ່ຍັງບໍ່ໄດ້ອັບ ແລະ ການເປີດ lightbox ຊ້ອນ
                            ເທິງ modal ຈະເປັນບັນຫາ z-index ໂດຍບໍ່ໄດ້ຫຍັງເພີ່ມ.
                        -->
                        <!--
                            ບໍ່ໄດ້ໃຊ້ `BankLogo` ຢູ່ນີ້: ຢູ່ຕາຕະລາງຮູບມາຈາກ
                            `bank.logo_url` ແຕ່ຢູ່ນີ້ມັນອາດເປັນ object URL ຂອງ
                            ໄຟລ໌ທີ່ຫາກໍ່ເລືອກ ແລະ ຍັງບໍ່ໄດ້ອັບຂຶ້ນ server ເລີຍ —
                            ຍັງບໍ່ມີ `Bank` ໃຫ້ສົ່ງເຂົ້າ. ຂອບ/ພື້ນຄືກັນທຸກຢ່າງ
                            ເພື່ອໃຫ້ຕົວຢ່າງກົງກັບຜົນຈິງ.
                        -->
                        <span
                            class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full"
                            :class="
                                logoDisplayUrl
                                    ? ''
                                    : 'border border-dashed border-gray-300 dark:border-gray-700'
                            "
                        >
                            <img
                                v-if="logoDisplayUrl"
                                :src="logoDisplayUrl"
                                alt=""
                                class="h-full w-full object-contain"
                            />
                            <!-- ລະຫັດທີ່ພິມຢູ່ຕອນນີ້ — ປ່ຽນຕາມທີ່ພິມ ຈຶ່ງເຫັນ
                                 ໄດ້ວ່າຄ່າສຳຮອງໃນຕາຕະລາງຈະອອກມາໜ້າຕາແນວໃດ -->
                            <span
                                v-else
                                class="truncate px-1 text-sm font-semibold text-gray-400 dark:text-gray-500"
                            >
                                {{
                                    form.code
                                        ? form.code.slice(0, 4)
                                        : t('settings.banks.noLogo')
                                }}
                            </span>
                        </span>

                        <div class="flex flex-wrap items-center gap-2">
                            <!--
                                input ຢູ່ນອກ label ແລະ ໃຊ້ `sr-only` ບໍ່ແມ່ນ
                                `hidden` — ເຫດຜົນດຽວກັບ EvidenceUploader:
                                display:none ເຮັດໃຫ້ໂຟກັດດ້ວຍແປ້ນພິມບໍ່ໄດ້
                            -->
                            <input
                                :id="logoInputId"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                @change="onPickLogo"
                            />

                            <label
                                :for="logoInputId"
                                class="cursor-pointer rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-white/5"
                            >
                                {{
                                    logoDisplayUrl
                                        ? t('settings.banks.logoReplace')
                                        : t('settings.banks.logoChoose')
                                }}
                            </label>

                            <button
                                v-if="logoDisplayUrl"
                                type="button"
                                class="rounded-lg px-3 py-2.5 text-sm font-medium text-error-500 transition hover:bg-error-50 dark:hover:bg-error-500/10"
                                @click="clearLogo"
                            >
                                {{ t('settings.banks.logoRemove') }}
                            </button>
                        </div>
                    </div>

                    <p
                        class="mt-2 text-theme-xs text-gray-500 dark:text-gray-400"
                    >
                        {{ t('settings.banks.logoHint') }}
                    </p>

                    <p
                        v-if="logoError"
                        class="mt-1 text-theme-xs text-error-500"
                    >
                        {{ logoError }}
                    </p>
                </div>

                <div class="sm:col-span-2">
                    <TextField
                        v-model="form.note"
                        label="settings.banks.note"
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
                        t('settings.banks.isActiveHint')
                    }}</span>
                </label>
            </div>
        </FormModal>

        <ConfirmDialog
            :open="removing !== null"
            title="settings.banks.deleteTitle"
            :message="
                t('settings.banks.deleteMessage', {
                    name: removing?.name_lo ?? '',
                })
            "
            :saving="saving"
            :error="removeError"
            @cancel="cancelRemove"
            @confirm="doRemove"
        />

        <!--
            ຕົວດຽວກັບທີ່ໜ້າຫຼັກຖານໃຊ້. `imgs` ຄືໂລໂກ້ **ທຸກອັນ** ໃນຕາຕະລາງ
            ບໍ່ແມ່ນອັນທີ່ກົດອັນດຽວ — ຈຶ່ງກົດລູກສອນເລື່ອນເບິ່ງທີ່ລະອັນໄດ້.
        -->
        <VueEasyLightbox
            :visible="lightboxVisible"
            :imgs="lightboxSources"
            :index="lightboxIndex"
            teleport="body"
            move-disabled
            @hide="lightboxVisible = false"
        />
    </AdminLayout>
</template>

<script setup lang="ts">
import type { ColDef, ICellRendererParams } from 'ag-grid-community';
import { computed, onBeforeUnmount, reactive, ref, useId } from 'vue';
import VueEasyLightbox from 'vue-easy-lightbox';
import { useI18n } from 'vue-i18n';
import { api, toApiError } from '@/api/client';
import DataGrid from '@/components/common/DataGrid.vue';
import ActionsCell from '@/components/grid/ActionsCell.vue';
import BadgeCell from '@/components/grid/BadgeCell.vue';
import BankLogoCell from '@/components/grid/BankLogoCell.vue';
import TwoLineCell from '@/components/grid/TwoLineCell.vue';
import AdminLayout from '@/components/layout/AdminLayout.vue';
import ConfirmDialog from '@/components/settings/ConfirmDialog.vue';
import FormModal from '@/components/settings/FormModal.vue';
import SettingsPage from '@/components/settings/SettingsPage.vue';
import TextField from '@/components/settings/TextField.vue';
import { useAutoRefresh } from '@/composables/useAutoRefresh';
import { useCrud } from '@/composables/useCrud';
import { useDebouncedSearch } from '@/composables/useDebouncedSearch';
import { actionColDef } from '@/lib/ag-grid';
import { useAuthStore } from '@/stores/auth';
import type { Bank } from '@/types/settings';
import FormIcon from '~icons/tabler/building-bank';

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
    replace,
    remove,
    clearError,
} = useCrud<Bank & Record<string, unknown>>('/banks', 'id', {
    describe: (bank) => bank.name_lo,
});

/** server ຄິດໃຫ້ຈາກ MasterDataPolicy — ຜູ້ດູແລລະບົບບໍ່ໄດ້ຖືກຍົກເວັ້ນ */
const canWrite = computed(() => auth.user?.can_manage_master_data === true);

const search = ref('');
const listError = computed(() =>
    loading.value || modalOpen.value || removing.value !== null
        ? null
        : (error.value?.message ?? null),
);

const removeError = computed(() =>
    removing.value === null ? null : (error.value?.message ?? null),
);

/** ລາຍຈ່າຍ + ລາຍຮັບ — ທັງສອງຊີ້ມາທີ່ bank_id ຈຶ່ງກັນການລຶບໄດ້ທັງຄູ່ */
function usageCount(bank: Bank): number {
    return (bank.expenses_count ?? 0) + (bank.revenues_count ?? 0);
}

// ---------- ຕາຕະລາງ ----------

const getRowId = (params: { data: Bank }) => String(params.data.id);

const columnDefs = computed<ColDef[]>(() => [
    {
        colId: 'logo',
        headerName: t('settings.banks.logo'),
        width: 90,
        sortable: false,
        filter: false,
        floatingFilter: false,
        cellRenderer: BankLogoCell,
        cellRendererParams: { onView: openLightbox },
    },
    {
        field: 'code',
        headerName: t('settings.banks.code'),
        width: 120,
        cellClass: 'font-mono',
    },
    {
        field: 'name_lo',
        headerName: t('settings.banks.name'),
        flex: 2,
        minWidth: 200,
        cellRenderer: TwoLineCell,
        cellRendererParams: {
            secondary: (params: ICellRendererParams<Bank>) =>
                params.data?.name_en ?? null,
        },
    },
    {
        field: 'swift_code',
        headerName: t('settings.banks.swift'),
        width: 140,
        cellClass: 'font-mono text-gray-500 dark:text-gray-400',
        valueFormatter: (params) => params.value ?? '—',
    },
    {
        colId: 'usage',
        headerName: t('settings.banks.usedBy'),
        width: 110,
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
        valueGetter: (params) => (params.data ? usageCount(params.data) : 0),
    },
    {
        field: 'is_active',
        headerName: t('settings.banks.isActive'),
        width: 130,
        cellRenderer: BadgeCell,
        cellRendererParams: {
            color: (params: ICellRendererParams<Bank>) =>
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
                      actions: (params: ICellRendererParams<Bank>) => {
                          const bank = params.data;

                          if (!bank) {
                              return [];
                          }

                          const used = usageCount(bank);

                          return [
                              {
                                  label: t('common.edit'),
                                  onClick: () => openEdit(bank),
                              },
                              {
                                  label: t('common.delete'),
                                  danger: true,
                                  disabled: used > 0,
                                  title:
                                      used > 0
                                          ? t('settings.banks.inUseHint', {
                                                count: used,
                                            })
                                          : t('common.delete'),
                                  onClick: () => confirmRemove(bank),
                              },
                          ];
                      },
                  },
              },
          ]
        : []),
]);

// `with_usage` ຂໍໃຫ້ server ນັບການອ້າງອີງມານຳ — ໜ້ານີ້ຕ້ອງຮູ້ກ່ອນຈະ
// ສະແດງປຸ່ມລຶບໃຫ້ກົດໄດ້ (ຟອມທຸລະກຳບໍ່ຂໍ ເພາະໃຊ້ແຕ່ຊື່)
useDebouncedSearch(search, (term) =>
    fetchAll(
        term
            ? { search: term, include_inactive: true, with_usage: true }
            : { include_inactive: true, with_usage: true },
    ),
);

// ຢຸດຕອນຟອມ/ກ່ອງຢືນຢັນເປີດ — ດຶງທັບຕອນນັ້ນຈະເຮັດໃຫ້ແຖວທີ່ກຳລັງແກ້ຫາຍໄປ
useAutoRefresh(refresh, {
    paused: () => modalOpen.value || removing.value !== null,
});

// ---------- ເບິ່ງໂລໂກ້ເຕັມຈໍ ----------
const lightboxVisible = ref(false);
const lightboxIndex = ref(0);

/** ສະເພາະແຖວທີ່ມີຮູບ — ຕຳແໜ່ງໃນ array ນີ້ຄື index ຂອງ lightbox */
const banksWithLogo = computed(() => items.value.filter((b) => b.logo_url));

const lightboxSources = computed(() =>
    banksWithLogo.value.map((b) => ({
        src: b.logo_url as string,
        title: `${b.code} — ${b.name_lo}`,
    })),
);

/**
 * ບໍ່ຕ້ອງດຶງ URL ໃໝ່ກ່ອນເປີດ ຄືທີ່ຫຼັກຖານເຮັດ (useEvidenceLightbox):
 * signed URL ຂອງໂລໂກ້ມີອາຍຸ 24 ຊົ່ວໂມງ ບໍ່ແມ່ນ 5 ນາທີ ຈຶ່ງບໍ່ມີທາງໝົດອາຍຸ
 * ລະຫວ່າງທີ່ໜ້າຍັງເປີດຢູ່.
 */
function openLightbox(bank: Bank): void {
    const position = banksWithLogo.value.findIndex((b) => b.id === bank.id);

    if (position === -1) {
        return;
    }

    lightboxIndex.value = position;
    lightboxVisible.value = true;
}

// ---------- ຟອມ ----------
const modalOpen = ref(false);
const editing = ref<Bank | null>(null);
const removing = ref<Bank | null>(null);

const logoInputId = useId();
/** ໄຟລ໌ທີ່ເລືອກໄວ້ ແຕ່ຍັງບໍ່ໄດ້ສົ່ງ — ສົ່ງຫຼັງແຖວມີ id ແລ້ວເທົ່ານັ້ນ */
const logoFile = ref<File | null>(null);
/** object URL ຂອງໄຟລ໌ທີ່ເລືອກ — ຕ້ອງ revoke ເອງ ບໍ່ດັ່ງນັ້ນຮົ່ວໜ່ວຍຄວາມຈຳ */
const logoPreview = ref<string | null>(null);
/** ຕິ໊ກລຶບໂລໂກ້ເກົ່າ (ມີຄວາມໝາຍສະເພາະຕອນແກ້ໄຂ) */
const logoCleared = ref(false);
const uploadingLogo = ref(false);
const logoError = ref<string | null>(null);

const blankForm = {
    code: '',
    name_lo: '',
    name_en: '',
    short_name: '',
    swift_code: '',
    note: '',
    sort_order: '0',
    is_active: true,
};

const form = reactive({ ...blankForm });

/** ຮູບໃໝ່ທີ່ເລືອກມາກ່ອນ, ຖ້າບໍ່ມີຈຶ່ງເປັນຮູບເກົ່າ (ເວັ້ນແຕ່ຖືກສັ່ງລຶບແລ້ວ) */
const logoDisplayUrl = computed(() => {
    if (logoPreview.value) {
        return logoPreview.value;
    }

    return logoCleared.value ? null : (editing.value?.logo_url ?? null);
});

function releasePreview(): void {
    if (logoPreview.value) {
        URL.revokeObjectURL(logoPreview.value);
        logoPreview.value = null;
    }
}

function resetLogoState(): void {
    releasePreview();
    logoFile.value = null;
    logoCleared.value = false;
    logoError.value = null;
}

function onPickLogo(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    // ລ້າງຄ່າ — ບໍ່ດັ່ງນັ້ນເລືອກໄຟລ໌ດຽວກັນຊ້ຳຈະບໍ່ຍິງ change event ອີກ
    input.value = '';

    if (!file) {
        return;
    }

    releasePreview();
    logoError.value = null;
    logoFile.value = file;
    logoPreview.value = URL.createObjectURL(file);
    // ເລືອກໄຟລ໌ໃໝ່ = ທັບຂອງເກົ່າ ບໍ່ແມ່ນລຶບ
    logoCleared.value = false;
}

function clearLogo(): void {
    releasePreview();
    logoFile.value = null;
    logoError.value = null;
    logoCleared.value = true;
}

function openCreate(): void {
    clearError();
    resetLogoState();
    editing.value = null;
    Object.assign(form, blankForm);
    modalOpen.value = true;
}

function openEdit(bank: Bank): void {
    clearError();
    resetLogoState();
    editing.value = bank;
    Object.assign(form, {
        ...blankForm,
        code: bank.code,
        name_lo: bank.name_lo,
        name_en: bank.name_en ?? '',
        short_name: bank.short_name ?? '',
        swift_code: bank.swift_code ?? '',
        note: bank.note ?? '',
        sort_order: String(bank.sort_order),
        is_active: bank.is_active,
    });
    modalOpen.value = true;
}

function closeModal(): void {
    modalOpen.value = false;
    resetLogoState();
    clearError();
}

async function submit(): Promise<void> {
    const payload: Record<string, unknown> = {
        code: form.code,
        name_lo: form.name_lo,
        name_en: form.name_en || null,
        short_name: form.short_name || null,
        swift_code: form.swift_code || null,
        note: form.note || null,
        sort_order: Number(form.sort_order) || 0,
        is_active: form.is_active,
    };

    const saved = await save(payload, editing.value);

    if (!saved) {
        return;
    }

    // ຂໍ້ມູນບັນທຶກແລ້ວ — ໂລໂກ້ລົ້ມກໍບໍ່ໄດ້ລົບລ້າງສ່ວນນັ້ນ ຈຶ່ງປະຟອມເປີດໄວ້
    // ພ້ອມຂໍ້ຄວາມ ໃຫ້ຜູ້ໃຊ້ລອງໃໝ່ໄດ້ໂດຍບໍ່ຕ້ອງພິມຄືນ
    if (!(await syncLogo(saved))) {
        return;
    }

    closeModal();
}

/** ຄືນ false ເມື່ອສົ່ງໂລໂກ້ລົ້ມ — ຂໍ້ມູນສ່ວນອື່ນຖືກບັນທຶກໄປແລ້ວ */
async function syncLogo(bank: Bank): Promise<boolean> {
    const file = logoFile.value;
    const shouldDelete = logoCleared.value && editing.value?.has_logo === true;

    if (!file && !shouldDelete) {
        return true;
    }

    uploadingLogo.value = true;
    logoError.value = null;

    try {
        if (file) {
            const body = new FormData();
            body.append('logo', file);

            const { data } = await api.post<{ data: Bank }>(
                `/banks/${bank.id}/logo`,
                body,
            );
            replace(data.data as Bank & Record<string, unknown>);
        } else {
            const { data } = await api.delete<{ data: Bank }>(
                `/banks/${bank.id}/logo`,
            );
            replace(data.data as Bank & Record<string, unknown>);
        }

        return true;
    } catch (e) {
        const apiError = toApiError(e);
        logoError.value = apiError.errors.logo?.[0] ?? apiError.message;

        // ແຖວຖືກສ້າງ/ແກ້ໄປແລ້ວ — ຄັ້ງຕໍ່ໄປຕ້ອງເປັນການ "ແກ້ໄຂ" ບໍ່ແມ່ນສ້າງຊ້ຳ
        editing.value = bank;

        return false;
    } finally {
        uploadingLogo.value = false;
    }
}

function confirmRemove(bank: Bank): void {
    clearError();
    removing.value = bank;
}

function cancelRemove(): void {
    removing.value = null;
    clearError();
}

async function doRemove(): Promise<void> {
    const target = removing.value;

    if (target && (await remove(target as Bank & Record<string, unknown>))) {
        removing.value = null;
    }
}

onBeforeUnmount(releasePreview);
</script>
