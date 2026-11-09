<template>
    <AdminLayout>
        <PageBreadcrumb page-title="tx.expenses.title" :icon="FormIcon" />

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
                        {{ t('tx.expenses.title') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('tx.expenses.description') }}
                    </p>
                </div>

                <Button
                    v-if="canCreate"
                    size="sm"
                    :start-icon="PlusIcon"
                    :on-click="openCreate"
                >
                    {{ t('tx.expenses.create') }}
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
                :empty-label="t('tx.expenses.empty')"
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

        <!-- soft delete ຢູ່ server (ExpenseController::destroy) ແຕ່ຜູ້ໃຊ້ກູ້ຄືນເອງບໍ່ໄດ້ -->
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
            kind="expense"
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
                    :label="t('tx.category')"
                    :value="detail.category?.name_lo ?? null"
                />
                <DetailRow
                    :label="t('tx.payee')"
                    :value="detail.payee?.name ?? null"
                />
                <DetailRow
                    :label="t('tx.payer')"
                    :value="detail.payer?.name ?? null"
                />
                <DetailRow
                    :label="t('tx.paymentMethod')"
                    :value="t(`tx.paymentMethods.${detail.payment_method}`)"
                />
                <!-- ຄູ່ກັບໃບໂອນທີ່ແນບ — ຜູ້ກວດສອບອ່ານສອງແຖວນີ້ຄູ່ກັນ -->
                <DetailRow
                    v-if="detail.bank"
                    :label="t('tx.bank')"
                    :value="bankLabel(detail.bank)"
                />
                <DetailRow
                    :label="t('tx.reference')"
                    :value="detail.payment_reference"
                />
            </template>
        </TransactionDetailModal>

        <FormModal
            :open="modalOpen"
            :icon="FormIcon"
            :title="editing ? 'tx.expenses.edit' : 'tx.expenses.create'"
            subtitle="tx.expenses.formHint"
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
                    <!-- server ບັງຄັບ before_or_equal:today ຢູ່ແລ້ວ —
                         ປິດວັນອະນາຄົດຢູ່ປະຕິທິນນຳ ຈະບໍ່ຕ້ອງເສຍເວລາຮອດ 422 -->
                    <DatePicker
                        v-model="form.expense_date"
                        label="tx.date"
                        name="expense_date"
                        :error="error"
                        max-today
                        required
                    />
                    <SelectField
                        v-model="form.category_id"
                        label="tx.category"
                        name="category_id"
                        :error="error"
                        :options="categoryOptions"
                        nullable
                        null-label="tx.noCategory"
                    />
                    <SelectField
                        v-model="form.payer_id"
                        label="tx.payer"
                        name="payer_id"
                        :error="error"
                        :options="payerOptions"
                        nullable
                        null-label="tx.noPayer"
                    />
                    <SelectField
                        v-model="form.payee_id"
                        label="tx.payee"
                        name="payee_id"
                        :error="error"
                        :options="payeeOptions"
                        nullable
                        null-label="tx.noPayee"
                    />

                    <TextField
                        v-model="form.title"
                        class="sm:col-span-2"
                        label="tx.title"
                        name="title"
                        :error="error"
                        required
                    />
                    <SelectField
                        v-model="form.payment_method"
                        label="tx.paymentMethod"
                        name="payment_method"
                        :error="error"
                        :options="paymentMethodOptions"
                    />

                    <!--
                        ສະແດງສະເພາະການຈ່າຍທີ່ຜ່ານທະນາຄານ — ຖາມວ່າ "ທະນາຄານໃດ"
                        ສຳລັບການຈ່າຍເປັນເງິນສົດແມ່ນຄຳຖາມທີ່ບໍ່ມີຄຳຕອບ.
                        server ກໍລ້າງຄ່ານີ້ຖິ້ມເອງເມື່ອວິທີຈ່າຍບໍ່ຜ່ານທະນາຄານ
                        (ExpenseService::bankIdFor) — ຢູ່ນີ້ພຽງແຕ່ບໍ່ໃຫ້ຖາມ
                    -->
                    <BankSelect
                        v-if="usesBank"
                        v-model="form.bank_id"
                        label="tx.bank"
                        name="bank_id"
                        :error="error"
                        :banks="banks"
                    />

                    <TextField
                        v-model="form.payment_reference"
                        label="tx.reference"
                        name="payment_reference"
                        :error="error"
                        hint="tx.referenceHint"
                    />

                    <!--
                        ໝາຍເຫດກິນຖັນທີ່ເຫຼືອທັງໝົດ ບໍ່ປະຮູໄວ້ທ້າຍແຖວ.

                        ຈຳນວນຖັນປ່ຽນຕາມວ່າມີຊ່ອງທະນາຄານບໍ ເພາະມັນຍ້າຍທຸກ
                        ຢ່າງທີ່ຢູ່ຖັດໄປ (ຢູ່ຈໍໃຫຍ່ 4 ຖັນ):
                          ມີທະນາຄານ  → ແຖວນີ້ = ເລກອ້າງອີງ(1) + ໝາຍເຫດ(3)
                          ບໍ່ມີ       → ເລກອ້າງອີງຂຶ້ນໄປແຖວເທິງ ໝາຍເຫດຢູ່ຄົນດຽວ(4)
                    -->
                    <TextField
                        v-model="form.note"
                        class="sm:col-span-2"
                        :class="usesBank ? 'lg:col-span-3' : 'lg:col-span-4'"
                        label="tx.note"
                        name="note"
                        :error="error"
                    />
                </div>

                <MoneyInput
                    v-model:amount="form.amount"
                    v-model:currency="form.currency_code"
                    v-model:rate="form.fx_rate_lak"
                    v-model:confirm-rate="form.confirm_unusual_rate"
                    :date="form.expense_date"
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
                        resource="expenses"
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
import type { BankRef, CurrencyOption, Expense } from '@/types/transactions';
import { formatDate } from '@/utils/date';
import {
    formatMoney,
    formatMoneyWithCurrency,
    rateToSend,
} from '@/utils/money';
import { toastSuccess } from '@/utils/toast';
import FormIcon from '~icons/tabler/arrow-up-right';

const { t } = useI18n();

const projects = ref<Project[]>([]);
const projectUuid = ref('');
const currencies = ref<CurrencyOption[]>([]);
const payers = ref<{ id: number; name: string }[]>([]);
const payees = ref<{ id: number; name: string }[]>([]);
const categories = ref<{ id: number; name_lo: string }[]>([]);
const banks = ref<Bank[]>([]);

const items = ref<Expense[]>([]);

const loading = ref(false);
const saving = ref(false);
const error = ref<ApiError | null>(null);
const listError = ref<string | null>(null);

const modalOpen = ref(false);
const editing = ref<Expense | null>(null);

/** ລາຍການທີ່ກຳລັງເບິ່ງລາຍລະອຽດ (ອ່ານຢ່າງດຽວ) — null = ປິດ */
const detail = ref<Expense | null>(null);

/** ເປີດຮູບຫຼັກຖານໂດຍກົງຈາກຕາຕະລາງ (ດຶງໄຟລ໌ໃຫ້ເອງຕອນກົດ) */
const evidence = useEvidenceViewer('expenses');

const currentProject = computed(() =>
    projects.value.find((p) => p.uuid === projectUuid.value),
);

// ທຸກ Policy ຂອງລາຍຈ່າຍເລີ່ມຈາກ "ເປັນສະມາຊິກໂຄງການບໍ" — ຜູ້ດູແລລະບົບ
// ກໍບໍ່ຍົກເວັ້ນ. ບໍ່ມີ role = ບໍ່ຕ້ອງຍິງ request ໄປໃຫ້ 403 ຕອບກັບມາ
const isMember = computed(() => currentProject.value?.my_role != null);

const canCreate = computed(() =>
    ['full_access', 'modify'].includes(currentProject.value?.my_role ?? ''),
);

const toOptions = (rows: { id: number; name?: string; name_lo?: string }[]) =>
    rows.map((r) => ({
        value: String(r.id),
        label: r.name ?? r.name_lo ?? '',
        translate: false,
    }));

const payerOptions = computed(() => toOptions(payers.value));
const payeeOptions = computed(() => toOptions(payees.value));
const categoryOptions = computed(() => toOptions(categories.value));

/** ຊື່ຫຍໍ້ກ່ອນ (BCEL ອ່ານໄວກວ່າຊື່ເຕັມ) ແລ້ວຄ່ອຍຊື່ເຕັມ */
function bankLabel(bank: BankRef): string {
    return bank.short_name
        ? `${bank.short_name} — ${bank.name_lo}`
        : bank.name_lo;
}

const paymentMethodOptions = [
    { value: 'bank_transfer', label: 'tx.paymentMethods.bank_transfer' },
    { value: 'cash', label: 'tx.paymentMethods.cash' },
    { value: 'mobile_banking', label: 'tx.paymentMethods.mobile_banking' },
    { value: 'cheque', label: 'tx.paymentMethods.cheque' },
    { value: 'credit', label: 'tx.paymentMethods.credit' },
    { value: 'other', label: 'tx.paymentMethods.other' },
];

/**
 * ວິທີຈ່າຍທີ່ຜ່ານທະນາຄານ — ຕ້ອງກົງກັບ `ExpenseService::BANK_METHODS`.
 * ຖ້າສອງບ່ອນນີ້ບໍ່ກົງກັນ ຜູ້ໃຊ້ຈະເລືອກທະນາຄານໄດ້ແລ້ວ server ລ້າງຖິ້ມງຽບໆ.
 */
const BANK_METHODS = ['bank_transfer', 'mobile_banking', 'cheque'];

const usesBank = computed(() => BANK_METHODS.includes(form.payment_method));

const blankForm = {
    expense_date: new Date().toISOString().slice(0, 10),
    title: '',
    currency_code: 'LAK',
    amount: '',
    fx_rate_lak: '',
    confirm_unusual_rate: false,
    category_id: '',
    payer_id: '',
    payee_id: '',
    bank_id: '',
    payment_method: 'bank_transfer',
    payment_reference: '',
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
    const [projectRes, currencyRes, payerRes, payeeRes, categoryRes, bankRes] =
        await Promise.all([
            api.get<{ data: Project[] }>('/projects'),
            api.get<{ data: CurrencyOption[] }>('/currencies'),
            api.get<{ data: { id: number; name: string }[] }>('/payers'),
            api.get<{ data: { id: number; name: string }[] }>('/payees'),
            api.get<{ data: { id: number; name_lo: string }[] }>('/categories'),
            // ບໍ່ຂໍ `with_usage` — ຟອມນີ້ໃຊ້ແຕ່ຊື່ ບໍ່ຕ້ອງຈ່າຍ subquery ນັບ
            api.get<{ data: Bank[] }>('/banks'),
        ]);

    projects.value = projectRes.data.data;
    currencies.value = currencyRes.data.data;
    payers.value = payerRes.data.data;
    payees.value = payeeRes.data.data;
    categories.value = categoryRes.data.data;
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
        const { data } = await api.get<Paginated<Expense>>(
            `/projects/${projectUuid.value}/expenses`,
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

function openDetail(row: Expense): void {
    detail.value = row;
}

function openEdit(row: Expense): void {
    error.value = null;
    editing.value = row;
    Object.assign(form, {
        expense_date: row.expense_date,
        title: row.title,
        currency_code: row.amount.currency,
        amount: row.amount.decimal,
        fx_rate_lak: row.fx_rate_lak,
        confirm_unusual_rate: false,
        category_id: row.category ? String(row.category.id) : '',
        payer_id: row.payer ? String(row.payer.id) : '',
        payee_id: row.payee ? String(row.payee.id) : '',
        bank_id: row.bank ? String(row.bank.id) : '',
        payment_method: row.payment_method,
        payment_reference: row.payment_reference ?? '',
        note: row.note ?? '',
    });
    modalOpen.value = true;
}

function closeModal(): void {
    modalOpen.value = false;
    error.value = null;
}

/** ເນື້ອໃນ toast: "EXP-2026-0001 · 12,500,000 LAK" — ພໍໃຫ້ຢັ້ງຢືນວ່າບັນທຶກຖືກລາຍການ */
function summarise(row: Expense): string {
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
function replaceRow(row: Expense): void {
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

const workflow = useTransactionWorkflow<Expense>('expenses', {
    onUpdated: replaceRow,
});

async function onSubmit(row: Expense): Promise<void> {
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
    if (editing.value?.is_locked) {
        closeModal();

        return;
    }

    saving.value = true;
    error.value = null;

    const payload = {
        expense_date: form.expense_date,
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
        confirm_unusual_rate: form.confirm_unusual_rate,
        category_id: form.category_id ? Number(form.category_id) : null,
        payer_id: form.payer_id ? Number(form.payer_id) : null,
        payee_id: form.payee_id ? Number(form.payee_id) : null,
        // ສົ່ງ null ເມື່ອວິທີຈ່າຍບໍ່ຜ່ານທະນາຄານ — ຊ່ອງນີ້ຖືກເຊື່ອງໄວ້ ແຕ່ຄ່າ
        // ເກົ່າຍັງຄ້າງຢູ່ໃນຟອມ ຖ້າຜູ້ໃຊ້ເລືອກທະນາຄານແລ້ວຄ່ອຍປ່ຽນເປັນເງິນສົດ
        bank_id: usesBank.value && form.bank_id ? Number(form.bank_id) : null,
        payment_method: form.payment_method,
        payment_reference: form.payment_reference || null,
        note: form.note || null,
    };

    try {
        if (editing.value) {
            const { data } = await api.patch<{ data: Expense }>(
                `/expenses/${editing.value.uuid}`,
                payload,
            );
            const index = items.value.findIndex(
                (i) => i.uuid === editing.value?.uuid,
            );

            if (index !== -1) {
                items.value[index] = data.data;
            }

            closeModal();
            toastSuccess(t('tx.expenses.updated'), summarise(data.data));
            void load(true);
        } else {
            const { data } = await api.post<{ data: Expense }>(
                `/projects/${projectUuid.value}/expenses`,
                payload,
            );
            // ບໍ່ໄດ້ຍັດໃສ່ຫົວຕາຕະລາງເອງ ແຕ່ໂຫຼດຄືນແບບງຽບ — ຟອມຍັງບັງຢູ່
            // ຜູ້ໃຊ້ບໍ່ເຫັນການກະພິບ
            void load(true);

            // ຄ້າງ modal ໄວ້ໃນໂໝດແກ້ໄຂ ເພື່ອໃຫ້ແນບຫຼັກຖານຕໍ່ໄດ້ທັນທີ
            editing.value = data.data;

            // ຟອມຄ້າງເປີດຢູ່ — ຖ້າບໍ່ແຈ້ງຫຍັງເລີຍ ຜູ້ໃຊ້ຈະບໍ່ຮູ້ວ່າບັນທຶກຜ່ານແລ້ວ
            // ແລ້ວກົດ "ບັນທຶກ" ຊ້ຳຈົນເກີດລາຍການຊ້ຳ
            toastSuccess(t('tx.expenses.created'), summarise(data.data));
        }
    } catch (e) {
        error.value = toApiError(e);
    } finally {
        saving.value = false;
    }
}

// ---------- ລຶບ ----------

const removing = ref<Expense | null>(null);
const deleting = ref(false);
const removeError = ref<string | null>(null);

function confirmRemove(row: Expense): void {
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
        await api.delete(`/expenses/${target.uuid}`);

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

const getRowId = (params: { data: Expense }) => params.data.uuid;

const columnDefs = computed<ColDef[]>(() => [
    {
        field: 'expense_date',
        headerName: t('ledger.date'),
        width: 140,
        filter: 'agDateColumnFilter',
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
            secondary: (params: ICellRendererParams<Expense>) => {
                const row = params.data;

                if (!row) {
                    return null;
                }

                // ໝວດ · ຜູ້ຮັບເງິນ · ຍອດ USD — ສາມຢ່າງທີ່ຄົນຫຼຽວຫາກ່ອນເປີດ
                // ລາຍລະອຽດ, ວາງລຽນກັນຄືເກົ່າ ແລະ ຂ້າມອັນທີ່ບໍ່ໄດ້ລະບຸ
                return [
                    row.category?.name_lo,
                    row.payee?.name,
                    `${formatMoney(row.amount_usd)} USD`,
                ]
                    .filter(Boolean)
                    .join(' · ');
            },
            dotColor: (params: ICellRendererParams<Expense>) =>
                params.data?.category?.color ?? null,
        },
    },
    {
        colId: 'amount',
        headerName: t('tx.amount'),
        width: 170,
        // ຮຽງ ແລະ ກັ່ນຕອງດ້ວຍຍອດ USD — ມິຕິດຽວທີ່ແຖວຄົນລະສະກຸນທຽບກັນໄດ້
        filter: 'agNumberColumnFilter',
        cellClass: 'ag-cell-numeric',
        headerClass: 'ag-right-aligned-header',
        valueGetter: (params) => (params.data?.amount_usd?.minor ?? 0) / 100,
        cellRenderer: MoneyCell,
        cellRendererParams: {
            tone: 'expense',
            money: (params: ICellRendererParams<Expense>) =>
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
            uuid: (params: ICellRendererParams<Expense>) => params.data?.uuid,
        },
    },
    {
        field: 'status',
        headerName: t('tx.status'),
        width: 140,
        cellRenderer: BadgeCell,
        cellRendererParams: {
            color: (params: ICellRendererParams<Expense>) =>
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
            busy: (row: Expense) => workflow.pending.value === row.uuid,
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
