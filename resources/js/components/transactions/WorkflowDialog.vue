<template>
    <!-- `stacked`: ເປີດຈາກໜ້າຕ່າງລາຍລະອຽດໄດ້ ຈຶ່ງຕ້ອງຢູ່ເທິງມັນ -->
    <Modal v-if="entry" full-screen-backdrop stacked @close="$emit('cancel')">
        <template #body>
            <div
                class="relative z-10 m-4 w-full max-w-md rounded-3xl bg-white p-6 lg:p-8 dark:bg-gray-900"
            >
                <div
                    class="mb-5 flex h-12 w-12 items-center justify-center rounded-full"
                    :class="tone.iconWrap"
                >
                    <CheckIcon
                        v-if="action === 'approve'"
                        class="h-6 w-6 text-success-600 dark:text-success-500"
                    />
                    <ReopenIcon
                        v-else-if="action === 'reopen'"
                        class="h-6 w-6 text-warning-600 dark:text-orange-400"
                    />
                    <XIcon v-else class="h-6 w-6 text-error-500" />
                </div>

                <h4
                    class="mb-2 text-lg font-semibold text-gray-800 dark:text-white/90"
                >
                    {{ t(`tx.workflow.${action}Title`) }}
                </h4>

                <!-- ເລກທີ່ໃບສຳຄັນ + ຍອດ: ຢືນຢັນວ່າກຳລັງຕັດສິນລາຍການໃດ.
                     ຜູ້ອະນຸມັດເຫັນຫຼາຍແຖວຄ້າຍກັນ ຊື່ລາຍການຢ່າງດຽວບໍ່ພໍ -->
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ t('tx.workflow.confirmSubject') }}
                </p>
                <p
                    class="mt-1 text-sm font-medium text-gray-800 dark:text-white/90"
                >
                    <span
                        v-if="entry.doc_no"
                        class="font-mono text-theme-xs text-gray-500 dark:text-gray-400"
                        >{{ entry.doc_no }} · </span
                    >{{ entry.title }} ·
                    {{ formatMoneyWithCurrency(entry.amount) }}
                </p>

                <!--
                    ຄຳເຕືອນສະເພາະການອະນຸມັດ: ຫຼັງອະນຸມັດແລ້ວ `isLocked()` ເປັນ
                    true ຕະຫຼອດໄປ — ບໍ່ມີປຸ່ມຍ້ອນຄືນຢູ່ບ່ອນໃດໃນລະບົບ ຕ້ອງ
                    ສ້າງລາຍການປັບປຸງໃໝ່ແທນ. ຕ້ອງບອກ **ກ່ອນ** ກົດ.
                -->
                <p
                    v-if="action !== 'reject'"
                    class="mt-4 rounded-lg bg-warning-50 p-3 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-orange-400"
                >
                    {{ t(`tx.workflow.${action}Warning`) }}
                </p>

                <!-- ເຫດຜົນການປະຕິເສດ / ສົ່ງກັບ — server ບັງຄັບ 3–2000 ຕົວອັກສອນ ແລະ
                     ຜູ້ບັນທຶກຈະເຫັນຂໍ້ຄວາມນີ້ຢູ່ໜ້າລາຍລະອຽດເພື່ອເອົາໄປແກ້ -->
                <div v-if="needsReason" class="mt-4">
                    <label
                        for="workflow-reason"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        {{ t('tx.rejectionReason') }}
                        <span class="text-error-500">*</span>
                    </label>
                    <textarea
                        id="workflow-reason"
                        ref="reasonInput"
                        v-model="reason"
                        rows="3"
                        maxlength="2000"
                        :placeholder="t('tx.workflow.reasonPlaceholder')"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                    ></textarea>
                    <p
                        class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400"
                    >
                        {{ t('tx.workflow.reasonHint') }}
                    </p>
                </div>

                <p
                    v-if="errorMessage"
                    role="alert"
                    class="mt-4 rounded-lg bg-error-50 p-3 text-sm text-error-700 dark:bg-error-500/10 dark:text-error-400"
                >
                    {{ errorMessage }}
                </p>

                <div class="mt-7 flex items-center justify-end gap-3">
                    <Button
                        variant="outline"
                        size="sm"
                        :on-click="() => $emit('cancel')"
                    >
                        {{ t('common.cancel') }}
                    </Button>

                    <button
                        type="button"
                        :disabled="saving || (needsReason && !reasonIsValid)"
                        class="inline-flex items-center justify-center rounded-lg px-4 py-3 text-sm font-medium text-white shadow-theme-xs transition disabled:cursor-not-allowed disabled:opacity-50"
                        :class="tone.button"
                        @click="$emit('confirm', reason.trim())"
                    >
                        {{
                            saving
                                ? t('common.saving')
                                : t(`tx.workflow.${action}`)
                        }}
                    </button>
                </div>
            </div>
        </template>
    </Modal>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ApiError } from '@/api/client';
import Button from '@/components/ui/Button.vue';
import Modal from '@/components/ui/Modal.vue';
import type { DialogAction } from '@/composables/useTransactionWorkflow';
import type { Expense, Revenue } from '@/types/transactions';
import { formatMoneyWithCurrency } from '@/utils/money';
import ReopenIcon from '~icons/tabler/arrow-back-up';
import CheckIcon from '~icons/tabler/check';
import XIcon from '~icons/tabler/x';

/**
 * ໜ້າຕ່າງຢືນຢັນ "ອະນຸມັດ", "ປະຕິເສດ" ແລະ "ສົ່ງກັບແກ້ໄຂ" (ຍົກເລີກການອະນຸມັດ).
 *
 * ບໍ່ໃຊ້ ConfirmDialog ຂອງໜ້າຕັ້ງຄ່າ ເພາະອັນນັ້ນຕາຍຕົວເປັນການລຶບ (ໄອຄອນ
 * ເຕືອນສີແດງ + ປຸ່ມ "ລຶບ") ແລະ ຮັບແຕ່ຂໍ້ຄວາມ ບໍ່ມີບ່ອນປ້ອນເຫດຜົນ.
 */
/**
 * ສິ່ງທີ່ໜ້າຕ່າງນີ້ຕ້ອງການຈິງ — ພຽງພໍໃຫ້ຜູ້ອະນຸມັດຢືນຢັນວ່າກຳລັງຕັດສິນ
 * ລາຍການໃດ. ປະກາດເປັນໂຄງສ້າງເພື່ອໃຫ້ຄິວລໍຖ້າອະນຸມັດ (`PendingApproval`)
 * ໃຊ້ໜ້າຕ່າງດຽວກັນນີ້ໄດ້ ໂດຍບໍ່ຕ້ອງມີ field ຄົບຄືທຸລະກຳເຕັມ.
 */
type ConfirmableEntry = Pick<
    Expense | Revenue,
    'uuid' | 'doc_no' | 'title' | 'amount'
>;

interface Props {
    /** null = ປິດ. ບໍ່ໃຊ້ `open` ແຍກ — ຄ່າດຽວແລ້ວບໍ່ມີທາງເປີດໂດຍບໍ່ມີລາຍການ */
    entry: ConfirmableEntry | null;
    action: DialogAction;
    saving?: boolean;
    error?: ApiError | null;
}

const props = withDefaults(defineProps<Props>(), {
    saving: false,
    error: null,
});

defineEmits<{ cancel: []; confirm: [reason: string] }>();

const { t } = useI18n();

const reason = ref('');
const reasonInput = useTemplateRef<HTMLTextAreaElement>('reasonInput');

/** ປະຕິເສດ ແລະ ສົ່ງກັບ ຕ້ອງບອກຜູ້ບັນທຶກວ່າໃຫ້ແກ້ຫຍັງ */
const needsReason = computed(() => props.action !== 'approve');

const tone = computed(
    () =>
        ({
            approve: {
                iconWrap: 'bg-success-50 dark:bg-success-500/15',
                button: 'bg-success-500 hover:bg-success-600',
            },
            reject: {
                iconWrap: 'bg-error-50 dark:bg-error-500/15',
                button: 'bg-error-500 hover:bg-error-600',
            },
            reopen: {
                iconWrap: 'bg-warning-50 dark:bg-warning-500/15',
                button: 'bg-warning-500 hover:bg-warning-600',
            },
        })[props.action],
);

/** ຄືກັບກົດຂອງ server: `required|min:3` ຫຼັງຕັດຊ່ອງຫວ່າງ */
const reasonIsValid = computed(() => reason.value.trim().length >= 3);

/**
 * ຂໍ້ຜິດພາດຂອງຊ່ອງ `reason` ຈາກ 422 ມາຢູ່ `errors` ບໍ່ແມ່ນ `message`
 * (`message` ຕອນນັ້ນເປັນຂໍ້ຄວາມລວມແບບ "The given data was invalid").
 */
const errorMessage = computed(() => {
    if (!props.error) {
        return null;
    }

    return props.error.errors.reason?.[0] ?? props.error.message;
});

// ລ້າງເຫດຜົນເກົ່າຖິ້ມທຸກຄັ້ງທີ່ເປີດໃສ່ລາຍການໃໝ່ — ບໍ່ດັ່ງນັ້ນເຫດຜົນຂອງ
// ແຖວກ່ອນຈະຄ້າງມາ ແລ້ວກົດຢືນຢັນຜິດລາຍການໄດ້ງ່າຍໆ
watch(
    () => props.entry?.uuid,
    (uuid) => {
        reason.value = '';

        if (uuid && needsReason.value) {
            void nextTick(() => reasonInput.value?.focus());
        }
    },
    { immediate: true },
);
</script>
