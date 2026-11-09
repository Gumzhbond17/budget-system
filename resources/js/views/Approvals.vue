<template>
    <AdminLayout>
        <PageBreadcrumb :page-title="t('approvals.title')" :icon="PageIcon" />

        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 lg:p-6 dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3
                        class="text-lg font-semibold text-gray-800 dark:text-white/90"
                    >
                        {{ t('approvals.heading') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ t('approvals.description') }}
                    </p>
                </div>

                <span
                    v-if="approvals.hasPending"
                    class="rounded-full bg-error-50 px-3 py-1 text-theme-sm font-medium text-error-600 dark:bg-error-500/15 dark:text-error-500"
                >
                    {{
                        t('approvals.waitingCount', { count: approvals.total })
                    }}
                </span>
            </div>

            <p
                v-if="approvals.loading && !approvals.loaded"
                class="py-16 text-center text-sm text-gray-500 dark:text-gray-400"
            >
                {{ t('common.loading') }}
            </p>

            <!--
                ຄິວຫວ່າງບໍ່ແມ່ນຄວາມຜິດພາດ — ມັນຄືສະພາບປົກຕິທີ່ດີ.
                ຜູ້ທີ່ບໍ່ມີສິດອະນຸມັດກໍມາຮອດບ່ອນນີ້ ແລະ ເຫັນອັນດຽວກັນ
                (server ຕອບຄິວຫວ່າງ ບໍ່ແມ່ນ 403 — ເບິ່ງ ApprovalController)
            -->
            <div
                v-else-if="approvals.items.length === 0"
                class="py-16 text-center"
            >
                <CheckIcon
                    class="mx-auto h-10 w-10 text-success-500"
                    aria-hidden="true"
                />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('approvals.empty') }}
                </p>
            </div>

            <ul v-else class="flex flex-col gap-3">
                <li
                    v-for="entry in approvals.items"
                    :key="entry.uuid"
                    :ref="(el) => registerRow(entry.uuid, el)"
                    class="flex flex-wrap items-center gap-4 rounded-xl border p-4 transition"
                    :class="
                        entry.uuid === focusUuid
                            ? 'border-brand-300 bg-brand-50/50 dark:border-brand-500/50 dark:bg-brand-500/10'
                            : 'border-gray-200 dark:border-gray-800'
                    "
                >
                    <span
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                        :class="
                            entry.type === 'revenue'
                                ? 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500'
                                : 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500'
                        "
                    >
                        <component
                            :is="
                                entry.type === 'revenue'
                                    ? RevenueIcon
                                    : ExpenseIcon
                            "
                            class="h-5 w-5"
                        />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p
                            class="truncate text-sm font-medium text-gray-800 dark:text-white/90"
                        >
                            <span
                                v-if="entry.doc_no"
                                class="font-mono text-theme-xs text-gray-500 dark:text-gray-400"
                                >{{ entry.doc_no }} · </span
                            >{{ entry.title }}
                        </p>
                        <p
                            class="mt-1 flex flex-wrap items-center gap-2 text-theme-xs text-gray-500 dark:text-gray-400"
                        >
                            <span>{{ entry.project?.name ?? '—' }}</span>
                            <span class="h-1 w-1 rounded-full bg-gray-400" />
                            <span>{{ formatDate(entry.date) }}</span>
                            <span class="h-1 w-1 rounded-full bg-gray-400" />
                            <span>
                                {{
                                    t('approvals.submittedByAt', {
                                        name:
                                            entry.submitted_by ??
                                            t('approvals.someone'),
                                        when: fromNow(entry.submitted_at),
                                    })
                                }}
                            </span>
                            <!-- ບໍ່ມີຫຼັກຖານແນບ = ເຫດຜົນທີ່ພົບຫຼາຍທີ່ສຸດໃນການປະຕິເສດ -->
                            <span
                                v-if="entry.attachments_count === 0"
                                class="rounded-full bg-warning-50 px-2 py-0.5 text-warning-700 dark:bg-warning-500/10 dark:text-orange-400"
                            >
                                {{ t('approvals.noEvidence') }}
                            </span>
                        </p>
                    </div>

                    <p
                        class="shrink-0 text-right text-sm font-semibold"
                        :class="
                            entry.type === 'revenue'
                                ? 'text-success-600 dark:text-success-500'
                                : 'text-error-600 dark:text-error-500'
                        "
                    >
                        {{ formatMoneyWithCurrency(entry.amount) }}
                        <span
                            class="block text-theme-xs font-normal text-gray-500 dark:text-gray-400"
                        >
                            {{ formatMoneyWithCurrency(entry.amount_usd) }}
                        </span>
                    </p>

                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            type="button"
                            :disabled="pendingUuid === entry.uuid"
                            class="rounded-lg border border-gray-300 px-3 py-2 text-theme-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-white/5"
                            @click="flowFor(entry).askReject(entry)"
                        >
                            {{ t('tx.workflow.reject') }}
                        </button>

                        <button
                            type="button"
                            :disabled="pendingUuid === entry.uuid"
                            class="rounded-lg bg-success-500 px-3 py-2 text-theme-sm font-medium text-white transition hover:bg-success-600 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="flowFor(entry).askApprove(entry)"
                        >
                            {{ t('tx.workflow.approve') }}
                        </button>
                    </div>
                </li>
            </ul>
        </div>

        <WorkflowDialog
            :entry="dialogEntry"
            :action="dialogAction"
            :saving="dialogSaving"
            :error="dialogError"
            @cancel="closeDialog"
            @confirm="onConfirm"
        />
    </AdminLayout>
</template>

<script setup lang="ts">
import type { ComponentPublicInstance } from 'vue';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import AdminLayout from '@/components/layout/AdminLayout.vue';
import WorkflowDialog from '@/components/transactions/WorkflowDialog.vue';
import { useAutoRefresh } from '@/composables/useAutoRefresh';
import { useTransactionWorkflow } from '@/composables/useTransactionWorkflow';
import { useApprovalsStore } from '@/stores/approvals';
import type { PendingApproval } from '@/types/transactions';
import { formatDate, fromNow } from '@/utils/date';
import { formatMoneyWithCurrency } from '@/utils/money';
import { toastSuccess } from '@/utils/toast';
// ຂໍ້ຕົກລົງທົ່ວແອັບ: ລູກສອນ **ລົງ** = ເງິນເຂົ້າ, **ຂຶ້ນ** = ເງິນອອກ
// (ຄືກັນກັບບັດສະຫຼຸບຢູ່ໜ້າຫຼັກ, ບັນຊີແຍກປະເພດ ແລະ ຟອມທຸລະກຳ)
import RevenueIcon from '~icons/tabler/arrow-down-left';
import ExpenseIcon from '~icons/tabler/arrow-up-right';
import PageIcon from '~icons/tabler/checklist';
import CheckIcon from '~icons/tabler/checks';

/**
 * ຄິວລໍຖ້າອະນຸມັດ — ປາຍທາງຂອງກະດິ່ງແຈ້ງເຕືອນ.
 *
 * ຕ່າງຈາກໜ້າທຸລະກຳ: ບໍ່ຕ້ອງເລືອກໂຄງການກ່ອນ. ຜູ້ອະນຸມັດທີ່ຮັບຜິດຊອບຫຼາຍ
 * ໂຄງການເຫັນທຸກອັນລວມກັນ ຮຽງຕາມເວລາທີ່ສົ່ງມາ ຈຶ່ງເຄລຍໄດ້ໃນບ່ອນດຽວ.
 */
const { t } = useI18n();
const route = useRoute();
const approvals = useApprovalsStore();

// ຂໍຄິວເຕັມຕາບໃດທີ່ໜ້ານີ້ເປີດຢູ່ — ບໍ່ດັ່ງນັ້ນຮອບ 30 ວິນາທີຂອງກະດິ່ງ
// (fetch 5 ແຖວ) ຈະທັບລາຍການຢູ່ນີ້ໃຫ້ເຫຼືອ 5 ແຖວ
onUnmounted(approvals.holdFull());

/** uuid ຈາກ `?focus=` — ແຖວທີ່ຜູ້ໃຊ້ກົດມາຈາກກະດິ່ງ */
const focusUuid = ref<string | null>(null);

const rows = new Map<string, HTMLElement>();

function registerRow(
    uuid: string,
    el: Element | ComponentPublicInstance | null,
): void {
    if (el instanceof HTMLElement) {
        rows.set(uuid, el);
    } else {
        rows.delete(uuid);
    }
}

/**
 * ອະນຸມັດ/ປະຕິເສດ ໃຊ້ composable ອັນດຽວກັນກັບໜ້າທຸລະກຳ. ມັນຜູກ endpoint
 * ຕອນສ້າງ (`/expenses/…` ຫຼື `/revenues/…`) ແຕ່ຄິວນີ້ປົນສອງຊະນິດ ຈຶ່ງສ້າງ
 * ສອງອັນໄວ້ ແລ້ວເລືອກເອົາອັນທີ່ກົງກັບແຖວທີ່ກົດ.
 */
/**
 * ເອົາແຖວອອກທັນທີ (ບໍ່ຕ້ອງລໍເນັດ) ແລ້ວດຶງຄິວຄືນ — ລາຍການທີ່ຄົນອື່ນຫາກໍສົ່ງ
 * ຫຼື ອະນຸມັດໄປແລ້ວປາກົດ/ຫາຍໄປພ້ອມກັນ ບໍ່ຕ້ອງລໍຮອບ 30 ວິນາທີ
 */
function onDecided(entry: PendingApproval): void {
    approvals.remove(entry.uuid);
    void approvals.fetch();
}

const expenseFlow = useTransactionWorkflow<PendingApproval>('expenses', {
    onUpdated: onDecided,
});

const revenueFlow = useTransactionWorkflow<PendingApproval>('revenues', {
    onUpdated: onDecided,
});

function flowFor(entry: PendingApproval) {
    return entry.type === 'expense' ? expenseFlow : revenueFlow;
}

/**
 * ມີໜ້າຕ່າງຢືນຢັນອັນດຽວແຕ່ມີສອງ flow — ອັນທີ່ເປີດຢູ່ຄືອັນທີ່ຄຸມມັນ.
 * ເປີດພ້ອມກັນສອງອັນບໍ່ໄດ້ ເພາະການກົດແຕ່ລະຄັ້ງເປີດອັນດຽວ.
 */
const openFlow = computed(() =>
    expenseFlow.dialog.value !== null ? expenseFlow : revenueFlow,
);

const dialogEntry = computed(() => openFlow.value.dialog.value?.entry ?? null);
const dialogAction = computed(
    () => openFlow.value.dialog.value?.action ?? 'approve',
);
const dialogSaving = computed(() => openFlow.value.saving.value);
const dialogError = computed(() => openFlow.value.error.value);

/** uuid ຂອງແຖວທີ່ກຳລັງສົ່ງ request — ປິດປຸ່ມສະເພາະແຖວນັ້ນ */
const pendingUuid = computed(
    () => expenseFlow.pending.value ?? revenueFlow.pending.value,
);

function closeDialog(): void {
    expenseFlow.closeDialog();
    revenueFlow.closeDialog();
}

async function onConfirm(reason: string): Promise<void> {
    const flow = openFlow.value;
    const action = flow.dialog.value?.action;

    const done = await flow.confirmDialog(reason);

    if (done === null) {
        // ລົ້ມ — ໜ້າຕ່າງຄ້າງໄວ້ພ້ອມຂໍ້ຄວາມ (ເບິ່ງ useTransactionWorkflow).
        // ເຫດຜົນທີ່ພົບຫຼາຍສຸດ: ຄົນອື່ນອະນຸມັດຕັດໜ້າໄປແລ້ວ
        return;
    }

    toastSuccess(
        t(action === 'approve' ? 'approvals.approved' : 'approvals.rejected', {
            doc: done.doc_no ?? done.title,
        }),
    );

    // ດຶງຄືນເພື່ອເອົາແຖວທີ່ຍັງບໍ່ທັນສະແດງຂຶ້ນມາແທນອັນທີ່ຫາກໍເຄລຍໄປ
    void approvals.fetch();
}

void approvals.fetch();

useAutoRefresh(() => approvals.fetch(), {
    paused: () => dialogEntry.value !== null,
});

// ເລື່ອນຫາແຖວທີ່ກົດມາຈາກກະດິ່ງ — ຄິວຍາວແລ້ວຫາເອງຍາກ
watch(
    () => [route.query.focus, approvals.items.length] as const,
    async ([focus]) => {
        focusUuid.value = typeof focus === 'string' ? focus : null;

        if (focusUuid.value === null) {
            return;
        }

        await nextTick();

        rows.get(focusUuid.value)?.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
        });
    },
    { immediate: true },
);
</script>
