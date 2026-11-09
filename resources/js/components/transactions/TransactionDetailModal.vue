<template>
    <Modal v-if="open" full-screen-backdrop @close="$emit('close')">
        <template #body>
            <!-- flex-col + max-h: ກ້ອງຢູ່ນິ້ງ ບໍ່ສູງເກີນຈໍ ແລະ ມີແຕ່ສ່ວນ
                 ເນື້ອໃນທີ່ເລື່ອນ (ເບິ່ງ FormModal ສຳລັບເຫດຜົນເຕັມ) -->
            <div
                class="relative z-10 m-4 flex max-h-[calc(100dvh-2rem)] w-full max-w-3xl flex-col rounded-3xl bg-white p-6 lg:p-8 dark:bg-gray-900"
            >
                <button
                    type="button"
                    class="absolute top-5 right-5 flex h-9 w-9 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/5 dark:hover:text-white/80"
                    :aria-label="t('common.close')"
                    @click="$emit('close')"
                >
                    &times;
                </button>

                <!-- ---------- ຫົວ ---------- -->
                <div class="mb-5 shrink-0 pr-10">
                    <div class="mb-1 flex flex-wrap items-center gap-2">
                        <span
                            class="font-mono text-theme-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ docNo ?? '—' }}
                        </span>
                        <Badge size="sm" :color="statusColor">
                            {{ t(`ledger.statusLabel.${entry.status}`) }}
                        </Badge>
                        <Badge v-if="entry.is_locked" size="sm" color="light">
                            {{ t('tx.locked') }}
                        </Badge>
                    </div>

                    <h4
                        class="text-xl font-semibold text-gray-800 dark:text-white/90"
                    >
                        {{ entry.title }}
                    </h4>

                    <!-- ລາຍຮັບຂຽວ ລາຍຈ່າຍແດງ — ຄືກັນທົ່ວທັງລະບົບ -->
                    <p
                        class="mt-1 text-2xl font-semibold tabular-nums"
                        :class="
                            kind === 'expense'
                                ? 'text-error-600 dark:text-error-500'
                                : 'text-success-600 dark:text-success-500'
                        "
                    >
                        {{ kind === 'expense' ? '−' : '+'
                        }}{{ formatMoneyWithCurrency(entry.amount) }}
                    </p>
                </div>

                <!-- `min-h-0` ຈຳເປັນສຳລັບ overflow ໃນ flex column —
                     ບໍ່ດັ່ງນັ້ນກ້ອງຈະລົ້ນແທນທີ່ຈະເລື່ອນ -->
                <div class="min-h-0 flex-1 overflow-y-auto pr-1">
                    <!-- ---------- ລາຍລະອຽດ ---------- -->
                    <dl
                        class="grid grid-cols-1 gap-x-6 gap-y-4 border-t border-gray-200 pt-5 sm:grid-cols-2 dark:border-gray-800"
                    >
                        <DetailRow
                            :label="t('tx.date')"
                            :value="formatDate(entryDate)"
                        />
                        <DetailRow
                            :label="t('tx.usdPreview')"
                            :value="formatMoneyWithCurrency(entry.amount_usd)"
                        />
                        <DetailRow
                            :label="t('tx.fxRate')"
                            :value="displayRate"
                        />
                        <DetailRow
                            :label="t('tx.amountLak')"
                            :value="formatMoneyWithCurrency(entry.amount_lak)"
                        />

                        <slot name="details" />

                        <DetailRow
                            v-if="entry.note"
                            class="sm:col-span-2"
                            :label="t('tx.note')"
                            :value="entry.note"
                        />
                        <DetailRow
                            v-if="entry.rejection_reason"
                            class="sm:col-span-2"
                            :label="t('tx.rejectionReason')"
                            :value="entry.rejection_reason"
                        />
                    </dl>

                    <!-- ---------- ຫຼັກຖານ ---------- -->
                    <div
                        class="mt-6 border-t border-gray-200 pt-5 dark:border-gray-800"
                    >
                        <h5
                            class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            {{ t('tx.evidence') }}
                            <span class="text-gray-400"
                                >({{ attachments.length }})</span
                            >
                        </h5>

                        <!--
                            ຢູ່ນີ້ໃຊ້ Spinner ດ່ຽວໆ ບໍ່ແມ່ນ LoadingState:
                            ນີ້ຄືແຖວນ້ອຍໆໃຕ້ຫົວຂໍ້ "ຫຼັກຖານ" ບໍ່ແມ່ນພື້ນທີ່
                            ຕາຕະລາງ — ກ້ອນກາງຈໍຈະດັນ layout ຂອງໜ້າຕ່າງ
                        -->
                        <p
                            v-if="loading"
                            role="status"
                            aria-live="polite"
                            class="flex items-center gap-2 text-theme-xs text-gray-500 dark:text-gray-400"
                        >
                            <Spinner size="sm" class="text-brand-500" />
                            {{ t('common.loading') }}
                        </p>

                        <p
                            v-else-if="loadError"
                            class="text-theme-xs text-error-500"
                        >
                            {{ loadError }}
                        </p>

                        <p
                            v-else-if="attachments.length === 0"
                            class="text-theme-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ t('tx.noEvidence') }}
                        </p>

                        <ul
                            v-else
                            class="grid grid-cols-2 gap-3 sm:grid-cols-4"
                        >
                            <li v-for="file in attachments" :key="file.uuid">
                                <!-- ຮູບ → lightbox; PDF → ແທັບໃໝ່ (lightbox ສະແດງ PDF ບໍ່ໄດ້) -->
                                <button
                                    v-if="file.is_image && file.thumb_url"
                                    type="button"
                                    class="group block w-full overflow-hidden rounded-lg border border-gray-200 transition hover:border-brand-400 dark:border-gray-800"
                                    :aria-label="
                                        t('tx.openImage', {
                                            name: file.original_name,
                                        })
                                    "
                                    @click="lightbox.open(file)"
                                >
                                    <span class="relative block">
                                        <img
                                            :src="file.thumb_url"
                                            :alt="file.original_name"
                                            loading="lazy"
                                            class="aspect-square w-full object-cover"
                                        />
                                        <span
                                            class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition group-hover:opacity-100"
                                        >
                                            <ZoomIcon
                                                class="h-7 w-7 text-white"
                                            />
                                        </span>
                                    </span>
                                </button>

                                <a
                                    v-else
                                    :href="file.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="block overflow-hidden rounded-lg border border-gray-200 transition hover:border-brand-400 dark:border-gray-800"
                                >
                                    <span
                                        class="flex aspect-square w-full items-center justify-center bg-gray-50 dark:bg-white/[0.03]"
                                    >
                                        <FileIcon
                                            class="h-8 w-8 text-gray-400"
                                        />
                                    </span>
                                </a>

                                <p
                                    class="mt-1 truncate text-theme-xs text-gray-600 dark:text-gray-400"
                                    :title="file.original_name"
                                >
                                    {{ file.original_name }}
                                </p>
                                <p class="truncate text-theme-xs text-gray-400">
                                    {{ t(`tx.evidenceKinds.${file.kind}`) }}
                                </p>
                            </li>
                        </ul>
                    </div>
                </div>

                <!--
                    ປຸ່ມຕັດສິນຢູ່ **ນອກ** ກອບທີ່ເລື່ອນ — ຜູ້ອະນຸມັດເລື່ອນເບິ່ງ
                    ໃບໂອນຢູ່ເທິງ ແລ້ວປຸ່ມຍັງຢູ່ບ່ອນເກົ່າ ບໍ່ຕ້ອງເລື່ອນກັບຄືນ.

                    `showActions` ມາຈາກຜູ້ເອີ້ນ ບໍ່ແມ່ນ `$slots.actions`:
                    slot ຖືກສົ່ງມາສະເໝີ ແລະ ຂ້າງໃນມັນ v-if ເອງ — ກວດແຕ່ວ່າ
                    "ມີ slot ບໍ" ຈະໄດ້ແຖບເສັ້ນຂີດຫວ່າງໆເມື່ອຜູ້ໃຊ້ບໍ່ມີສິດ
                -->
                <div
                    v-if="showActions"
                    class="mt-5 flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-gray-200 pt-5 dark:border-gray-800"
                >
                    <slot name="actions" />
                </div>
            </div>
        </template>
    </Modal>

    <!--
        ນອກ <Modal> ໂດຍເຈດຕະນາ: lightbox ຕ້ອງຢູ່ເທິງໜ້າຕ່າງລາຍລະອຽດ
        ບໍ່ແມ່ນຢູ່ໃນກອບ scroll ຂອງມັນ
    -->
    <VueEasyLightbox
        :visible="lightbox.visible.value"
        :imgs="lightbox.sources.value"
        :index="lightbox.index.value"
        teleport="body"
        move-disabled
        @hide="lightbox.close"
    />
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import VueEasyLightbox from 'vue-easy-lightbox';
import { useI18n } from 'vue-i18n';
import { api, toApiError } from '@/api/client';
import Badge from '@/components/ui/Badge.vue';
import Modal from '@/components/ui/Modal.vue';
import Spinner from '@/components/ui/Spinner.vue';
import { useEvidenceLightbox } from '@/composables/useEvidenceLightbox';
import type { Attachment, Expense, Revenue } from '@/types/transactions';
import { formatDate } from '@/utils/date';
import {
    formatMoneyWithCurrency,
    groupNumeric,
    trimTrailingZeros,
} from '@/utils/money';
import DetailRow from './DetailRow.vue';
import FileIcon from '~icons/tabler/file-text';
import ZoomIcon from '~icons/tabler/zoom-in';

/**
 * ໜ້າຕ່າງ "ເບິ່ງລາຍລະອຽດ" — ອ່ານຢ່າງດຽວ ບໍ່ມີການແກ້ໄຂ.
 *
 * ຫຼັກຖານຖືກດຶງມາຕອນເປີດເທົ່ານັ້ນ ບໍ່ແມ່ນຕອນໂຫຼດຕາຕະລາງ: signed URL
 * ໝົດອາຍຸໃນ 5 ນາທີ ດັ່ງນັ້ນການດຶງລ່ວງໜ້າໃຫ້ທຸກແຖວຈະໄດ້ URL ທີ່ຕາຍແລ້ວ
 * ພ້ອມກັບຍິງ request ໂດຍບໍ່ຈຳເປັນ.
 */
interface Props {
    open: boolean;
    kind: 'expense' | 'revenue';
    entry: Expense | Revenue;
    /** ສະແດງແຖບປຸ່ມທ້າຍໜ້າຕ່າງ (slot `actions`) — ເບິ່ງເຫດຜົນທີ່ template */
    showActions?: boolean;
}

const props = withDefaults(defineProps<Props>(), { showActions: false });

defineEmits<{ close: [] }>();

const { t } = useI18n();

const attachments = ref<Attachment[]>([]);
const loading = ref(false);
const loadError = ref<string | null>(null);

// ດຶງ URL ໃໝ່ໃຫ້ເອງເມື່ອ signed URL ໃກ້ໝົດອາຍຸ (ເບິ່ງ composable)
const lightbox = useEvidenceLightbox(attachments, () => loadAttachments());

const docNo = computed(() => props.entry.doc_no);

const entryDate = computed(() =>
    'expense_date' in props.entry
        ? props.entry.expense_date
        : props.entry.received_date,
);

/**
 * ອັດຕາເປັນກີບແບບປ້າຍ BCEL: "1 USD = 22,239 LAK", "1 THB = 675.48 LAK".
 * ລາຍການ USD ສະແດງອັດຕາກີບທີ່ໃຊ້ຄິດຍອດກີບ.
 */
const displayRate = computed(() => {
    const rate = trimTrailingZeros(props.entry.fx_rate_lak);
    const unit =
        props.entry.amount.currency === 'LAK'
            ? 'USD'
            : props.entry.amount.currency;

    return `1 ${unit} = ${groupNumeric(rate, 6)} LAK`;
});

const statusColor = computed(
    () =>
        (
            ({
                draft: 'light',
                submitted: 'info',
                approved: 'success',
                rejected: 'error',
            }) as const
        )[props.entry.status],
);

async function loadAttachments(): Promise<void> {
    if (props.entry.attachments_count === 0) {
        attachments.value = [];

        return;
    }

    loading.value = true;
    loadError.value = null;

    try {
        const { data } = await api.get<{ data: Attachment[] }>(
            `/${props.kind}s/${props.entry.uuid}/attachments`,
        );
        attachments.value = data.data;
        lightbox.markLoaded();
    } catch (e) {
        loadError.value = toApiError(e).message;
        attachments.value = [];
    } finally {
        loading.value = false;
    }
}

watch(
    () => [props.open, props.entry.uuid],
    () => {
        if (props.open) {
            void loadAttachments();
        }
    },
    { immediate: true },
);
</script>
