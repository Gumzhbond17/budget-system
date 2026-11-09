<template>
    <div
        class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
    >
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ t(label) }}
            </p>
            <span
                class="flex h-9 w-9 items-center justify-center rounded-lg"
                :class="iconClass"
            >
                <component :is="icon" class="h-5 w-5" />
            </span>
        </div>

        <p class="mt-3 text-2xl font-semibold tabular-nums" :class="valueClass">
            {{ prefix }}{{ formatMoney(value) }}
        </p>

        <p
            v-if="caption"
            class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400"
        >
            {{ caption }}
        </p>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Money } from '@/types/settings';
import { formatMoney } from '@/utils/money';

interface Props {
    label: string;
    value: Money | null;
    /** revenue = ຂຽວ, expense = ແດງ, balance = ຂຽວ/ແດງ ຕາມເຄື່ອງໝາຍ, neutral = ເທົາ */
    tone: 'revenue' | 'expense' | 'balance' | 'neutral';
    icon: object;
    caption?: string;
}

const props = defineProps<Props>();

const { t } = useI18n();

/**
 * ຍອດຄົງເຫຼືອປ່ຽນສີຕາມເຄື່ອງໝາຍ — ຕິດລົບ (ຈ່າຍເກີນເງິນທີ່ຮັບ) ຕ້ອງແດງ
 * ບໍ່ດັ່ງນັ້ນຄົນຈະເລື່ອນຜ່ານໂດຍບໍ່ສັງເກດເຫັນເຄື່ອງໝາຍລົບຕົວນ້ອຍໆ.
 */
const effectiveTone = computed(() => {
    if (props.tone !== 'balance') {
        return props.tone;
    }

    return (props.value?.minor ?? 0) < 0 ? 'expense' : 'revenue';
});

const valueClass = computed(
    () =>
        ({
            revenue: 'text-success-600 dark:text-success-500',
            expense: 'text-error-600 dark:text-error-500',
            neutral: 'text-gray-800 dark:text-white/90',
        })[effectiveTone.value as 'revenue' | 'expense' | 'neutral'],
);

const iconClass = computed(
    () =>
        ({
            revenue:
                'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500',
            expense:
                'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500',
            neutral:
                'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300',
        })[effectiveTone.value as 'revenue' | 'expense' | 'neutral'],
);

/** ບວກເຄື່ອງໝາຍ + ໃຫ້ລາຍຮັບ ເພື່ອບອກທິດທາງ ບໍ່ຕ້ອງອາໄສສີຢ່າງດຽວ (a11y) */
const prefix = computed(() => {
    if (effectiveTone.value === 'neutral' || props.value === null) {
        return '';
    }

    if (props.tone === 'expense') {
        return '−';
    }

    return (props.value.minor ?? 0) < 0 ? '' : '+';
});
</script>
