<template>
    <div ref="dropdownRef" class="relative">
        <button
            type="button"
            :aria-label="t('header.notifications')"
            class="relative flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
            @click="toggleDropdown"
        >
            <!--
                ປ້າຍເລກ ບໍ່ແມ່ນຈຸດແດງລ້າໆ: ຈຳນວນທີ່ຄ້າງອະນຸມັດຄືສິ່ງທີ່ຜູ້ອະນຸມັດ
                ຢາກຮູ້ກ່ອນກົດເຂົ້າໄປ. ຫຼາຍກວ່າ 9 ສະແດງ "9+" ເພື່ອບໍ່ໃຫ້ລົ້ນວົງກົມ.
            -->
            <span
                v-if="approvals.hasPending"
                class="absolute -top-1 -right-1 z-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-error-500 px-1 text-[10px] font-semibold text-white"
            >
                {{ approvals.total > 9 ? '9+' : approvals.total }}
            </span>

            <BellIcon class="h-5 w-5" />
        </button>

        <div
            v-if="dropdownOpen"
            class="absolute -right-[240px] mt-[17px] flex max-h-[480px] w-[350px] flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg sm:w-[361px] lg:right-0 dark:border-gray-800 dark:bg-gray-dark"
        >
            <div
                class="mb-3 flex shrink-0 items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800"
            >
                <h5
                    class="text-lg font-semibold text-gray-800 dark:text-white/90"
                >
                    {{ t('approvals.title') }}
                </h5>

                <button
                    type="button"
                    :aria-label="t('common.close')"
                    class="text-gray-500 dark:text-gray-400"
                    @click="closeDropdown"
                >
                    <XIcon class="h-5 w-5" />
                </button>
            </div>

            <p
                v-if="previewItems.length === 0"
                class="px-2 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
            >
                {{ t('approvals.empty') }}
            </p>

            <ul
                v-else
                class="flex custom-scrollbar min-h-0 flex-1 flex-col overflow-y-auto"
            >
                <li v-for="entry in previewItems" :key="entry.uuid">
                    <button
                        type="button"
                        class="flex w-full gap-3 border-b border-gray-100 px-4.5 py-3 text-left hover:bg-gray-100 dark:border-gray-800 dark:hover:bg-white/5"
                        @click="openEntry(entry)"
                    >
                        <!-- ຂຽວ = ເງິນເຂົ້າ, ແດງ = ເງິນອອກ — ສີດຽວກັນກັບໜ້າບັນຊີແຍກປະເພດ -->
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

                        <span class="block min-w-0 flex-1">
                            <span
                                class="mb-1.5 block text-theme-sm text-gray-500 dark:text-gray-400"
                            >
                                <span
                                    class="font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{
                                        entry.submitted_by ??
                                        t('approvals.someone')
                                    }}
                                </span>
                                {{ t('approvals.submittedFor') }}
                                <span
                                    class="font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{ formatMoneyWithCurrency(entry.amount) }}
                                </span>
                            </span>

                            <span
                                class="flex items-center gap-2 text-theme-xs text-gray-500 dark:text-gray-400"
                            >
                                <span class="truncate">{{
                                    entry.project?.name ?? entry.title
                                }}</span>
                                <span
                                    class="h-1 w-1 shrink-0 rounded-full bg-gray-400"
                                ></span>
                                <span class="shrink-0">{{
                                    fromNow(entry.submitted_at)
                                }}</span>
                            </span>
                        </span>
                    </button>
                </li>
            </ul>

            <!--
                ບອກຈຳນວນທີ່ຍັງບໍ່ໄດ້ສະແດງ — ກະດິ່ງເອົາມາແຄ່ 5 ແຖວ ຖ້າບໍ່ບອກ
                ຜູ້ໃຊ້ຈະຄິດວ່າມີແຕ່ເທົ່ານີ້ແລ້ວບໍ່ກົດເຂົ້າໄປເບິ່ງທັງໝົດ
            -->
            <RouterLink
                :to="{ name: 'approvals' }"
                class="mt-3 flex shrink-0 justify-center rounded-lg border border-gray-300 bg-white p-3 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200"
                @click="closeDropdown"
            >
                {{
                    hiddenCount > 0
                        ? t('approvals.viewAllCount', { count: hiddenCount })
                        : t('approvals.viewAll')
                }}
            </RouterLink>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { RouterLink, useRouter } from 'vue-router';
import { useAutoRefresh } from '@/composables/useAutoRefresh';
import { useApprovalsStore } from '@/stores/approvals';
import type { PendingApproval } from '@/types/transactions';
import { fromNow } from '@/utils/date';
import { formatMoneyWithCurrency } from '@/utils/money';
// ຂໍ້ຕົກລົງທົ່ວແອັບ: ລູກສອນ **ລົງ** = ເງິນເຂົ້າ, **ຂຶ້ນ** = ເງິນອອກ
import RevenueIcon from '~icons/tabler/arrow-down-left';
import ExpenseIcon from '~icons/tabler/arrow-up-right';
import BellIcon from '~icons/tabler/bell';
import XIcon from '~icons/tabler/x';

/**
 * ກະດິ່ງແຈ້ງເຕືອນ = ຄິວລໍຖ້າອະນຸມັດ.
 *
 * ບໍ່ມີການແຈ້ງເຕືອນປະເພດອື່ນໂດຍເຈດຕະນາ — ສິ່ງດຽວທີ່ຮຽກຮ້ອງໃຫ້ຜູ້ໃຊ້
 * **ລົງມືເຮັດ** ຄືການອະນຸມັດ/ປະຕິເສດ. ຂ່າວສານທົ່ວໄປທີ່ກົດແລ້ວບໍ່ມີຫຍັງໃຫ້
 * ເຮັດຈະເຮັດໃຫ້ຄົນເຊົາເບິ່ງກະດິ່ງ ແລ້ວພາດອັນທີ່ສຳຄັນໄປນຳ.
 */
const { t } = useI18n();
const router = useRouter();
const approvals = useApprovalsStore();

/** ກະດິ່ງເອົາແຕ່ 5 ແຖວເທິງສຸດ — ທີ່ເຫຼືອຢູ່ໜ້າ /approvals */
const PREVIEW_LIMIT = 5;

const dropdownOpen = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);

/**
 * 5 ແຖວທຳອິດ — ຕັດເອງຢູ່ນີ້ ເພາະຕອນໜ້າ /approvals ເປີດຢູ່ store ຖືຄິວເຕັມ
 * (ເບິ່ງ `holdFull`) ແລ້ວກະດິ່ງຈະຍາວເປັນຫາງໄປ
 */
const previewItems = computed(() => approvals.items.slice(0, PREVIEW_LIMIT));

const hiddenCount = computed(() =>
    Math.max(0, approvals.total - previewItems.value.length),
);

void approvals.fetch(PREVIEW_LIMIT);

// ດຶງຄືນເປັນໄລຍະ — ນີ້ຄືສິ່ງທີ່ເຮັດໃຫ້ "ທຸລະກຳໃໝ່" ໂຜ່ຂຶ້ນມາເອງ
// ໂດຍບໍ່ຕ້ອງໂຫຼດໜ້າຄືນ. ຢຸດຕອນເປີດລາຍການຢູ່ — ດຶງທັບຕອນນັ້ນຈະເຮັດໃຫ້
// ແຖວທີ່ກຳລັງຈະກົດຍ້າຍບ່ອນໜີ.
useAutoRefresh(() => approvals.fetch(PREVIEW_LIMIT), {
    paused: () => dropdownOpen.value,
});

function toggleDropdown(): void {
    dropdownOpen.value = !dropdownOpen.value;

    // ເປີດເທື່ອໃດດຶງໃໝ່ເທື່ອນັ້ນ — ຢ່າໃຫ້ເຫັນຄິວທີ່ເກົ່າເຖິງ 30 ວິນາທີ
    if (dropdownOpen.value) {
        void approvals.fetch(PREVIEW_LIMIT);
    }
}

function closeDropdown(): void {
    dropdownOpen.value = false;
}

/** ໄປໜ້າຄິວເຕັມ ພ້ອມເລື່ອນຫາແຖວທີ່ກົດ */
function openEntry(entry: PendingApproval): void {
    closeDropdown();
    void router.push({ name: 'approvals', query: { focus: entry.uuid } });
}

function handleClickOutside(event: MouseEvent): void {
    if (
        dropdownRef.value &&
        !dropdownRef.value.contains(event.target as Node)
    ) {
        closeDropdown();
    }
}

onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));
</script>
