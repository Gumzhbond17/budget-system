<template>
    <AdminLayout>
        <PageBreadcrumb page-title="tx.revenues.title" :icon="FormIcon" />

        <ProjectPicker v-model="projectUuid" :projects="projects" />

        <div v-if="!projectUuid" class="empty-state">
            {{ t('tx.selectProjectFirst') }}
        </div>

        <!-- ບໍ່ແມ່ນສະມາຊິກ = API ຈະຕອບ 403 ໂດຍບໍ່ບອກເຫດຜົນ ແລະ ປຸ່ມ
             "ເພີ່ມລາຍການ" ບໍ່ຂຶ້ນ. ບອກໃຫ້ຊັດ ພ້ອມທາງແກ້ໄຂ -->
        <Alert
            v-else-if="!isMember"
            variant="info"
            :title="t('tx.notAMember')"
            :message="t('tx.notAMemberHint')"
        />

        <div
            v-else
            class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800"
            >
                <div>
                    <h3
                        class="text-base font-medium text-gray-800 dark:text-white/90"
                    >
                        {{ t('tx.revenues.title') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('tx.revenues.description') }}
                    </p>
                </div>

                <Button
                    v-if="canCreate"
                    size="sm"
                    :start-icon="PlusIcon"
                    :on-click="openCreate"
                >
                    {{ t('tx.revenues.create') }}
                </Button>
            </div>

            <DataGrid
                :column-defs="columnDefs"
                :row-data="items"
                :loading="loading"
                :error="listError"
                :get-row-id="getRowId"
                :row-height="60"
                pagination
                :page-size="20"
                :empty-label="t('tx.revenues.empty')"
            />
        </div>

        <!-- ຫຼັກຖານແບບຮູບລ້ວນໆ — ເປີດຈາກປ້າຍຈຳນວນຢູ່ຕາຕະລາງ -->
        <VueEasyLightbox
            :visible="evidence.visible.value"
            :imgs="evidence.sources.value"
            :index="evidence.index.value"
            teleport="body"
            move-disabled
            @hide="evidence.close"
        />

        <!--
            ຢືນຢັນການອະນຸມັດ / ປະຕິເສດ. ຢູ່ນອກ TransactionDetailModal ໂດຍ
            ເຈດຕະນາ — ຕັດສິນຈາກຕາຕະລາງກໍໄດ້ ຈາກໜ້າລາຍລະອຽດກໍໄດ້
        -->
        <WorkflowDialog
            :entry="workflow.dialog.value?.entry ?? null"
            :action="workflow.dialog.value?.action ?? 'approve'"
            :saving="workflow.saving.value"
            :error="workflow.error.value"
            @cancel="workflow.closeDialog"
            @confirm="onConfirmWorkflow"
        />

        <!-- soft delete ຢູ່ server (RevenueController::destroy) ແຕ່ຜູ້ໃຊ້ກູ້ຄືນເອງບໍ່ໄດ້ -->
        <ConfirmDialog
            :open="removing !== null"
            title="tx.deleteTitle"
            :message="
                t('tx.deleteMessage', {
                    entry: removing ? summarise(removing) : '',
                })
            "
            :saving="deleting"
            :error="removeError"
            @cancel="cancelRemove"
            @confirm="doRemove"
        />

        <TransactionDetailModal
            v-if="detail"
            :open="detail !== null"
            kind="revenue"
            :entry="detail"
            :show-actions="hasWorkflowActions(detail)"
            @close="detail = null"
        >
            <!-- ຜູ້ອະນຸມັດເບິ່ງໃບໂອນຢູ່ນີ້ແລ້ວຕັດສິນໄດ້ເລີຍ ບໍ່ຕ້ອງປິດອອກ
                 ໄປຫາແຖວເດີມໃນຕາຕະລາງ -->
            <template #actions>
                <WorkflowActions
                    :entry="detail"
                    :busy="workflow.pending.value === detail.uuid"
                    @submit="onSubmit"
                    @approve="workflow.askApprove"
                    @reject="workflow.askReject"
                    @reopen="workflow.askReopen"
                />
            </template>

            <template #details>
                <DetailRow
                    :label="t('tx.source')"
                    :value="detail.payer?.name ?? null"
                />
                <!-- ຄູ່ກັບໃບໂອນທີ່ແນບ — ຜູ້ກວດສອບອ່ານສອງແຖວນີ້ຄູ່ກັນ -->
                <DetailRow
                    v-if="detail.bank"
                    :label="t('tx.bankReceived')"
                    :value="bankLabel(detail.bank)"
                />
                <DetailRow
                    :label="t('tx.reference')"
                    :value="detail.reference"
                />
            </template>
        </TransactionDetailModal>

        <FormModal
            :open="modalOpen"
            :icon="FormIcon"
            :title="editing ? 'tx.revenues.edit' : 'tx.revenues.create'"
            subtitle="tx.revenues.formHint"
            :saving="saving"
            :error="error"
            wide
            @close="closeModal"
            @submit="submit"
        >
            <div class="space-y-3">
                <!--
                    4 ຖັນຢູ່ຈໍໃຫຍ່ / 2 ຖັນຢູ່ແທັບເລັດ / ຊ້ອນກັນຢູ່ມືຖື.
                    `sm:col-span-*` ບໍ່ແມ່ນ `col-span-*` ໂດຍເຈດຕະນາ — ຢູ່ຂະໜາດ
                    ພື້ນຖານ grid ມີຖັນດຽວ ການສັ່ງ span 2 ຈະສ້າງຖັນແຝງຂຶ້ນມາ
                    ແລ້ວຊ່ອງລົ້ນອອກນອກຟອມ
                -->
                <div class="grid gap-x-5 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                    <DatePicker
                        v-model="form.received_date"
                        label="tx.date"
                        name="received_date"
                        :error="error"
                        required
                    />
                    <SelectField
                        v-model="form.payer_id"
                        label="tx.source"
                        name="payer_id"
                        :error="error"
                        :options="payerOptions"
                        nullable
                        null-label="tx.noSource"
                    />
                    <TextField
                        v-model="form.title"
                        class="sm:col-span-2"
                        label="tx.title"
                        name="title"
                        :error="error"
                        required
                    />
                    <!--
                        ບໍ່ມີ `payment_method` ຢູ່ລາຍຮັບ ຈຶ່ງສະແດງສະເໝີ —
                        "ເງິນເຂົ້າບັນຊີໃດ" ເປັນຄຳຖາມທີ່ຖືກສະເໝີ (ຕ່າງຈາກ
                        ຝັ່ງລາຍຈ່າຍ ທີ່ຊ່ອງນີ້ຂຶ້ນກັບວິທີຈ່າຍ)
                    -->
                    <BankSelect
                        v-model="form.bank_id"
                        label="tx.bankReceived"
                        name="bank_id"
                        :error="error"
                        :banks="banks"
                        hint="tx.bankReceivedHint"
                    />
                    <TextField
                        v-model="form.reference"
                        label="tx.reference"
                        name="reference"
                        :error="error"
                        hint="tx.referenceHint"
                    />

                    <!--
                        ໝາຍເຫດຢູ່ແຖວດຽວກັບທະນາຄານ/ເລກອ້າງອີງ ບໍ່ແມ່ນແຖວຂອງ
                        ຕົນເອງ — ສອງຊ່ອງນັ້ນປະຖັນຫວ່າງໄວ້ 2 ຖັນຢູ່ຈໍໃຫຍ່
                        ແລະ ໝາຍເຫດພໍດີກັບຊ່ອງນັ້ນ ຟອມຈຶ່ງເຕ້ຍລົງໜຶ່ງແຖວ
                    -->
                    <TextField
                        v-model="form.note"
                        class="sm:col-span-2"
                        label="tx.note"
                        name="note"
                        :error="error"
                    />
                </div>

                <MoneyInput
                    v-model:amount="form.amount"
                    v-model:currency="form.currency_code"
                    v-model:rate="form.fx_rate_lak"
                    :date="form.received_date"
                    :currencies="currencies"
                    :error="error"
                    dense
                />

                <div class="border-t border-gray-200 pt-5 dark:border-gray-800">
                    <!--
                        uuid = null ຕອນສ້າງໃໝ່ — ຕົວອັບໂຫຼດຈະຖືໄຟລ໌ໄວ້ໃນ browser
                        ແລ້ວສົ່ງໃຫ້ເອງທັນທີທີ່ບັນທຶກແລ້ວໄດ້ uuid ມາ
                    -->
                    <EvidenceUploader
                        resource="revenues"
                        :uuid="editing?.uuid ?? null"
                        :disabled="editing?.is_locked ?? false"
                        @changed="onEvidenceChanged"
                    />
                </div>
            </div>
        </FormModal>
    </AdminLayout>
</template>

<script setup lang="ts">
import type { ColDef, ICellRendererParams } from 'ag-grid-community';
import { computed, reactive, ref, watch } from 'vue';
import VueEasyLightbox from 'vue-easy-lightbox';
import { useI18n } from 'vue-i18n';
import { api, toApiError } from '@/api/client';
import type { ApiError } from '@/api/client';
import DataGrid from '@/components/common/DataGrid.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import DatePicker from '@/components/forms/DatePicker.vue';
import BadgeCell from '@/components/grid/BadgeCell.vue';
import CodeCell from '@/components/grid/CodeCell.vue';
import EvidenceCell from '@/components/grid/EvidenceCell.vue';
import MoneyCell from '@/components/grid/MoneyCell.vue';
import TransactionActionsCell from '@/components/grid/TransactionActionsCell.vue';
import TwoLineCell from '@/components/grid/TwoLineCell.vue';
import AdminLayout from '@/components/layout/AdminLayout.vue';
import ConfirmDialog from '@/components/settings/ConfirmDialog.vue';
import FormModal from '@/components/settings/FormModal.vue';
import SelectField from '@/components/settings/SelectField.vue';
import TextField from '@/components/settings/TextField.vue';
import BankSelect from '@/components/transactions/BankSelect.vue';
import DetailRow from '@/components/transactions/DetailRow.vue';
import EvidenceUploader from '@/components/transactions/EvidenceUploader.vue';
import MoneyInput from '@/components/transactions/MoneyInput.vue';
import ProjectPicker from '@/components/transactions/ProjectPicker.vue';
import TransactionDetailModal from '@/components/transactions/TransactionDetailModal.vue';
import WorkflowActions from '@/components/transactions/WorkflowActions.vue';
import WorkflowDialog from '@/components/transactions/WorkflowDialog.vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import { useAutoRefresh } from '@/composables/useAutoRefresh';
import { useEvidenceViewer } from '@/composables/useEvidenceLightbox';
import {
    hasWorkflowActions,
    useTransactionWorkflow,
} from '@/composables/useTransactionWorkflow';
import { PlusIcon } from '@/icons';
import { actionColDef } from '@/lib/ag-grid';
import type { EntryStatus } from '@/types/ledger';
import type { Paginated, Project } from '@/types/settings';
import type { Bank } from '@/types/settings';
import type { BankRef, CurrencyOption, Revenue } from '@/types/transactions';
import { formatDate } from '@/utils/date';
import {
    formatMoney,
    formatMoneyWithCurrency,
    rateToSend,
} from '@/utils/money';
import { toastSuccess } from '@/utils/toast';
import FormIcon from '~icons/tabler/arrow-down-left';

const { t } = useI18n();

const projects = ref<Project[]>([]);
const projectUuid = ref('');
const currencies = ref<CurrencyOption[]>([]);
const payers = ref<{ id: number; name: string }[]>([]);
const banks = ref<Bank[]>([]);

const items = ref<Revenue[]>([]);

const loading = ref(false);
const saving = ref(false);
const error = ref<ApiError | null>(null);
const listError = ref<string | null>(null);

const modalOpen = ref(false);
const editing = ref<Revenue | null>(null);

/** ລາຍການທີ່ກຳລັງເບິ່ງລາຍລະອຽດ (ອ່ານຢ່າງດຽວ) — null = ປິດ */
const detail = ref<Revenue | null>(null);

/** ເປີດຮູບຫຼັກຖານໂດຍກົງຈາກຕາຕະລາງ (ດຶງໄຟລ໌ໃຫ້ເອງຕອນກົດ) */
const evidence = useEvidenceViewer('revenues');

const currentProject = computed(() =>
    projects.value.find((p) => p.uuid === projectUuid.value),
);

/** ສິດຄິດຢູ່ຝັ່ງນີ້ເພື່ອເຊື່ອງປຸ່ມເທົ່ານັ້ນ — server ບັງຄັບຈິງຜ່ານ Policy */
// ທຸກ Policy ຂອງລາຍຮັບເລີ່ມຈາກ "ເປັນສະມາຊິກໂຄງການບໍ" — ຜູ້ດູແລລະບົບ
// ກໍບໍ່ຍົກເວັ້ນ. ບໍ່ມີ role = ບໍ່ຕ້ອງຍິງ request ໄປໃຫ້ 403 ຕອບກັບມາ
const isMember = computed(() => currentProject.value?.my_role != null);

const canCreate = computed(() =>
    ['full_access', 'modify'].includes(currentProject.value?.my_role ?? ''),
);

const payerOptions = computed(() =>
    payers.value.map((p) => ({
        value: String(p.id),
        label: p.name,
        translate: false,
    })),
);

/** ຊື່ຫຍໍ້ກ່ອນ (BCEL ອ່ານໄວກວ່າຊື່ເຕັມ) ແລ້ວຄ່ອຍຊື່ເຕັມ */
function bankLabel(bank: BankRef): string {
    return bank.short_name
        ? `${bank.short_name} — ${bank.name_lo}`
        : bank.name_lo;
}

const blankForm = {
    received_date: new Date().toISOString().slice(0, 10),
    title: '',
    currency_code: 'LAK',
    amount: '',
    fx_rate_lak: '',
    payer_id: '',
    bank_id: '',
    reference: '',
    note: '',
};

const form = reactive({ ...blankForm });

function statusColor(status: EntryStatus) {
    return (
        {
            draft: 'light',
            submitted: 'info',
            approved: 'success',
            rejected: 'error',
        } as const
    )[status];
}

async function loadReference(): Promise<void> {
    const [projectRes, currencyRes, payerRes, bankRes] = await Promise.all([
        api.get<{ data: Project[] }>('/projects'),
        api.get<{ data: CurrencyOption[] }>('/currencies'),
        api.get<{ data: { id: number; name: string }[] }>('/payers'),
        // ບໍ່ຂໍ `with_usage` — ຟອມນີ້ໃຊ້ແຕ່ຊື່ ບໍ່ຕ້ອງຈ່າຍ subquery ນັບ
        api.get<{ data: Bank[] }>('/banks'),
    ]);

    projects.value = projectRes.data.data;
    currencies.value = currencyRes.data.data;
    payers.value = payerRes.data.data;
    banks.value = bankRes.data.data;

    if (projects.value.length > 0 && !projectUuid.value) {
        projectUuid.value = projects.value[0].uuid;
    }
}

/**
 * ໂຫຼດ **ທັງໂຄງການ** ມາເທື່ອດຽວ ແລ້ວປະໃຫ້ຕາຕະລາງກັ່ນຕອງ, ຮຽງ ແລະ ແບ່ງໜ້າ
 * ເອງຢູ່ browser (client-side row model).
 *
 * ເປັນຫຍັງບໍ່ໃຫ້ server ກັ່ນເທື່ອລະໜ້າຄືເກົ່າ: ຖ້າຕາຕະລາງເຫັນສະເພາະ 20 ແຖວ
 * ຂອງໜ້າປັດຈຸບັນ ການກັ່ນຕອງ ແລະ ການຮຽງຂອງມັນຈະຄິດຈາກ 20 ແຖວນັ້ນເທົ່ານັ້ນ —
 * "ຍອດສູງສຸດ" ຈະຜິດທັນທີທີ່ມີໜ້າທີສອງ. server ຍັງມີເພດານ (ALL_ROWS_CAP)
 * ຢູ່ ຈຶ່ງບໍ່ແມ່ນການດຶງແບບບໍ່ຈຳກັດ.
 *
 * @param silent ຮອບໂຫຼດຄືນອັດຕະໂນມັດ — ບໍ່ຕ້ອງສະແດງ "ກຳລັງໂຫຼດ..." ແລະ
 *               ຖ້າລົ້ມໃຫ້ຮັກສາແຖວເກົ່າໄວ້ ແທນທີ່ຈະລ້າງຕາຕະລາງຖິ້ມ
 */
/**
 * ລຳດັບຂອງການໂຫຼດ — ຄຳຕອບທີ່ມາຊ້າກວ່າການໂຫຼດອັນໃໝ່ຖືກຖິ້ມ.
 * ບໍ່ດັ່ງນັ້ນຮອບ 30 ວິນາທີທີ່ຍິງກ່ອນບັນທຶກ ອາດກັບມາທີຫຼັງ ແລ້ວທັບແຖວທີ່ຫາກໍແກ້.
 */
let loadSeq = 0;

async function load(silent = false): Promise<void> {
    const seq = ++loadSeq;

    if (!projectUuid.value || !isMember.value) {
        items.value = [];

        return;
    }

    if (!silent) {
        loading.value = true;
        listError.value = null;
    }

    try {
        const { data } = await api.get<Paginated<Revenue>>(
            `/projects/${projectUuid.value}/revenues`,
            { params: { all: 1 } },
        );

        if (seq !== loadSeq) {
            return;
        }

        items.value = data.data;
    } catch (e) {
        if (silent || seq !== loadSeq) {
            return;
        }

        listError.value = toApiError(e).message;
        items.value = [];
    } finally {
        if (!silent) {
            loading.value = false;
        }
    }
}

function openCreate(): void {
    error.value = null;
    editing.value = null;
    Object.assign(form, blankForm);
    modalOpen.value = true;
}

function openDetail(row: Revenue): void {
    detail.value = row;
}

function openEdit(row: Revenue): void {
    error.value = null;
    editing.value = row;
    Object.assign(form, {
        received_date: row.received_date,
        title: row.title,
        currency_code: row.amount.currency,
        amount: row.amount.decimal,
        fx_rate_lak: row.fx_rate_lak,
        payer_id: row.payer ? String(row.payer.id) : '',
        bank_id: row.bank ? String(row.bank.id) : '',
        reference: row.reference ?? '',
        note: row.note ?? '',
    });
    modalOpen.value = true;
}

function closeModal(): void {
    modalOpen.value = false;
    error.value = null;
}

/** ເນື້ອໃນ toast: "REV-2026-0001 · 200,000 LAK" — ພໍໃຫ້ຢັ້ງຢືນວ່າບັນທຶກຖືກລາຍການ */
function summarise(row: Revenue): string {
    return [row.doc_no, formatMoneyWithCurrency(row.amount)]
        .filter(Boolean)
        .join(' · ');
}

/**
 * ເອົາແຖວທີ່ server ຕອບກັບມາໄປທັບຂອງເກົ່າ.
 *
 * ທັບໃສ່ `detail` ນຳ ຖ້າກຳລັງເປີດແຖວດຽວກັນຢູ່: ຫຼັງອະນຸມັດ ປຸ່ມໃນໜ້າ
 * ລາຍລະອຽດຕ້ອງຫາຍໄປພ້ອມກັນ ບໍ່ດັ່ງນັ້ນຈະກົດອະນຸມັດຊ້ຳໄດ້ອີກ (ແລ້ວໄດ້ 403)
 */
function replaceRow(row: Revenue): void {
    const index = items.value.findIndex((i) => i.uuid === row.uuid);

    if (index !== -1) {
        items.value[index] = row;
    }

    if (detail.value?.uuid === row.uuid) {
        detail.value = row;
    }

    if (editing.value?.uuid === row.uuid) {
        editing.value = row;
    }

    // ສົ່ງ/ອະນຸມັດ/ປະຕິເສດ/ສົ່ງກັບ ປ່ຽນສິດ (`can`) ຂອງແຖວ ແລະ ອາດປ່ຽນແຖວອື່ນ
    // ທີ່ຄົນອື່ນແກ້ພ້ອມກັນ — ດຶງທັງລາຍການຄືນແບບງຽບ
    void load(true);
}

const workflow = useTransactionWorkflow<Revenue>('revenues', {
    onUpdated: replaceRow,
});

async function onSubmit(row: Revenue): Promise<void> {
    if (await workflow.submit(row)) {
        toastSuccess(t('tx.workflow.submitted'), summarise(row));
    }
}

/**
 * ໜ້າຕ່າງຢືນຢັນປິດເອງເມື່ອສຳເລັດ (ເບິ່ງ composable) — ຢູ່ນີ້ພຽງແຈ້ງຜົນ.
 * ລົ້ມເຫຼວແມ່ນຄ້າງໄວ້ພ້ອມຂໍ້ຄວາມ ຈຶ່ງບໍ່ມີ toast ໃນເສັ້ນທາງນັ້ນ.
 */
async function onConfirmWorkflow(reason: string): Promise<void> {
    const action = workflow.dialog.value?.action;
    const done = await workflow.confirmDialog(reason);

    if (!done) {
        return;
    }

    toastSuccess(
        t(
            {
                approve: 'tx.workflow.approved',
                reject: 'tx.workflow.rejected',
                reopen: 'tx.workflow.reopened',
            }[action ?? 'approve'],
        ),
        summarise(done),
    );
}

async function submit(): Promise<void> {
    // ອະນຸມັດແລ້ວ = ອ່ານຢ່າງດຽວ; ປິດໄປເລີຍແທນທີ່ຈະຍິງ request ທີ່ຈະຖືກປະຕິເສດ
    if (editing.value?.is_locked) {
        closeModal();

        return;
    }

    saving.value = true;
    error.value = null;

    const payload = {
        received_date: form.received_date,
        title: form.title,
        currency_code: form.currency_code,
        amount: form.amount,
        fx_rate_lak: rateToSend(
            form.fx_rate_lak,
            editing.value
                ? {
                      rate: editing.value.fx_rate_lak,
                      currency: editing.value.amount.currency,
                      newCurrency: form.currency_code,
                  }
                : undefined,
        ),
        payer_id: form.payer_id ? Number(form.payer_id) : null,
        bank_id: form.bank_id ? Number(form.bank_id) : null,
        reference: form.reference || null,
        note: form.note || null,
    };

    try {
        if (editing.value) {
            const { data } = await api.patch<{ data: Revenue }>(
                `/revenues/${editing.value.uuid}`,
                payload,
            );
            const index = items.value.findIndex(
                (i) => i.uuid === editing.value?.uuid,
            );

            if (index !== -1) {
                items.value[index] = data.data;
            }

            closeModal();
            toastSuccess(t('tx.revenues.updated'), summarise(data.data));
            void load(true);
        } else {
            const { data } = await api.post<{ data: Revenue }>(
                `/projects/${projectUuid.value}/revenues`,
                payload,
            );
            // ບໍ່ໄດ້ຍັດໃສ່ຫົວຕາຕະລາງເອງ ແຕ່ໂຫຼດຄືນແບບງຽບ — ຟອມຍັງບັງຢູ່
            // ຜູ້ໃຊ້ບໍ່ເຫັນການກະພິບ ແລະ ຕາຕະລາງໄດ້ຮັບແຖວໃໝ່ພ້ອມລຳດັບທີ່
            // server ຕັດສິນ ບໍ່ແມ່ນລຳດັບທີ່ຄິດເອົາເອງຢູ່ນີ້
            void load(true);

            // ບໍ່ປິດ modal — ສະຫຼັບໄປໂໝດແກ້ໄຂເພື່ອໃຫ້ແນບຫຼັກຖານໄດ້ທັນທີ
            // ບໍ່ດັ່ງນັ້ນຜູ້ໃຊ້ຕ້ອງຫາລາຍການທີ່ຫາກໍສ້າງແລ້ວເປີດຄືນ
            editing.value = data.data;

            // ຟອມຄ້າງເປີດຢູ່ — ຖ້າບໍ່ແຈ້ງຫຍັງເລີຍ ຜູ້ໃຊ້ຈະບໍ່ຮູ້ວ່າບັນທຶກຜ່ານແລ້ວ
            // ແລ້ວກົດ "ບັນທຶກ" ຊ້ຳຈົນເກີດລາຍການຊ້ຳ
            toastSuccess(t('tx.revenues.created'), summarise(data.data));
        }
    } catch (e) {
        error.value = toApiError(e);
    } finally {
        saving.value = false;
    }
}

/** counter ຢູ່ຕາຕະລາງຕ້ອງກົງກັບຈຳນວນຈິງທັນທີ ບໍ່ຕ້ອງໂຫຼດໜ້າຄືນ */
// ---------- ລຶບ ----------

const removing = ref<Revenue | null>(null);
const deleting = ref(false);
const removeError = ref<string | null>(null);

function confirmRemove(row: Revenue): void {
    removeError.value = null;
    removing.value = row;
}

function cancelRemove(): void {
    removing.value = null;
    removeError.value = null;
}

async function doRemove(): Promise<void> {
    const target = removing.value;

    if (!target) {
        return;
    }

    deleting.value = true;
    removeError.value = null;

    try {
        await api.delete(`/revenues/${target.uuid}`);

        items.value = items.value.filter((i) => i.uuid !== target.uuid);

        if (detail.value?.uuid === target.uuid) {
            detail.value = null;
        }

        removing.value = null;
        toastSuccess(t('tx.deleted'), summarise(target));
        void load(true);
    } catch (e) {
        // 403 (ເຊັ່ນ ມີຄົນອະນຸມັດໄປພໍດີ) — ປະກ່ອງເປີດໄວ້ພ້ອມເຫດຜົນ
        removeError.value = toApiError(e).message;
    } finally {
        deleting.value = false;
    }
}

function onEvidenceChanged(count: number): void {
    if (!editing.value) {
        return;
    }

    editing.value.attachments_count = count;

    const index = items.value.findIndex((i) => i.uuid === editing.value?.uuid);

    if (index !== -1) {
        items.value[index] = {
            ...items.value[index],
            attachments_count: count,
        };
    }
}

// ---------- ຕາຕະລາງ ----------

const getRowId = (params: { data: Revenue }) => params.data.uuid;

const columnDefs = computed<ColDef[]>(() => [
    {
        field: 'received_date',
        headerName: t('ledger.date'),
        width: 140,
        filter: 'agDateColumnFilter',
        // ຮຽງດ້ວຍຄ່າດິບ ປ-ດ-ວ (ຮຽງເປັນຂໍ້ຄວາມກໍຖືກຕາມລຳດັບເວລາ) ແຕ່ສະແດງ
        // ເປັນ ວ/ດ/ປ ທີ່ຄົນລາວອ່ານ
        valueFormatter: (params) =>
            params.value ? formatDate(params.value) : '',
    },
    {
        field: 'doc_no',
        headerName: t('ledger.docNo'),
        width: 160,
        cellRenderer: CodeCell,
    },
    {
        field: 'title',
        headerName: t('ledger.description'),
        flex: 2,
        minWidth: 220,
        cellRenderer: TwoLineCell,
        cellRendererParams: {
            secondary: (params: ICellRendererParams<Revenue>) => {
                const row = params.data;

                if (!row) {
                    return null;
                }

                const usd = `${formatMoney(row.amount_usd)} USD`;

                return row.payer ? `${row.payer.name} · ${usd}` : usd;
            },
        },
    },
    {
        colId: 'amount',
        headerName: t('tx.amount'),
        width: 170,
        // ຮຽງ ແລະ ກັ່ນຕອງດ້ວຍຍອດ USD — ມິຕິດຽວທີ່ແຖວຄົນລະສະກຸນທຽບກັນໄດ້
        // (200,000 ກີບ ບໍ່ໄດ້ໃຫຍ່ກວ່າ USD 1,000 ເຖິງວ່າຕົວເລກຈະໃຫຍ່ກວ່າ)
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
        valueGetter: (params) => (params.data?.amount_usd?.minor ?? 0) / 100,
        cellRenderer: MoneyCell,
        cellRendererParams: {
            tone: 'revenue',
            money: (params: ICellRendererParams<Revenue>) =>
                params.data?.amount,
        },
    },
    {
        field: 'attachments_count',
        headerName: t('tx.evidence'),
        width: 110,
        filter: 'agNumberColumnFilter',
        cellClass: 'justify-center',
        headerClass: 'ag-center-aligned-header',
        cellRenderer: EvidenceCell,
        cellRendererParams: {
            onView: (uuid: string) => evidence.openFor(uuid),
            uuid: (params: ICellRendererParams<Revenue>) => params.data?.uuid,
        },
    },
    {
        field: 'status',
        headerName: t('tx.status'),
        width: 140,
        cellRenderer: BadgeCell,
        cellRendererParams: {
            color: (params: ICellRendererParams<Revenue>) =>
                params.data ? statusColor(params.data.status) : 'light',
        },
        valueGetter: (params) =>
            params.data ? t(`ledger.statusLabel.${params.data.status}`) : '',
    },
    {
        ...actionColDef,
        colId: 'actions',
        headerName: t('common.actions'),
        // ອະນຸມັດ + ປະຕິເສດ + ເສັ້ນແບ່ງ + 3 ໄອຄອນ ≈ 310px — ແຄບກວ່ານີ້ປຸ່ມທຳອິດຈະຖືກຕັດ
        width: 340,
        cellRenderer: TransactionActionsCell,
        cellRendererParams: {
            busy: (row: Revenue) => workflow.pending.value === row.uuid,
            onSubmit: onSubmit,
            onApprove: workflow.askApprove,
            onReject: workflow.askReject,
            onReopen: workflow.askReopen,
            onDetail: openDetail,
            onEdit: openEdit,
            onDelete: confirmRemove,
        },
    },
]);

// ຫຸ້ມດ້ວຍ arrow: watcher ສົ່ງ (ຄ່າໃໝ່, ຄ່າເກົ່າ) ເປັນ argument —
// ສົ່ງ `load` ກົງໆຈະກາຍເປັນ load(uuid) ເຊິ່ງ uuid ຈະຖືກອ່ານເປັນ `silent`
watch(projectUuid, () => void load());

// ຢຸດຕອນຟອມ, ໜ້າຕ່າງລາຍລະອຽດ ຫຼື ໜ້າຕ່າງຢືນຢັນເປີດຢູ່ — ໂຫຼດຄືນຂະນະ
// ກຳລັງພິມເຫດຜົນການປະຕິເສດຈະປ່ຽນແຖວໃຕ້ຕີນຜູ້ໃຊ້
useAutoRefresh(() => load(true), {
    paused: () =>
        modalOpen.value ||
        detail.value !== null ||
        removing.value !== null ||
        workflow.dialog.value !== null,
});

void loadReference();
</script>
