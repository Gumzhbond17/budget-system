<template>
    <Badge :color="color" size="sm">{{ label }}</Badge>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Badge from '@/components/ui/Badge.vue';
import type { CompanyPlan } from '@/types/settings';

interface Props {
    plan: CompanyPlan | null;
    /** ຄິດຢູ່ຝັ່ງ server — ຫ້າມປຽບທຽບວັນທີຢູ່ browser (timezone ຕ່າງກັນ) */
    hasAccess: boolean;
    expiresInDays: number | null;
}

const props = defineProps<Props>();

const { t } = useI18n();

/** ພາຍໃນກີ່ວັນຈຶ່ງເລີ່ມເຕືອນ — ກົງກັບ config('company.expiry_warning_days') */
const WARNING_DAYS = 7;

const state = computed(() => {
    if (props.plan === 'suspended') {
        return 'suspended';
    }

    if (!props.hasAccess) {
        return 'expired';
    }

    if (props.expiresInDays !== null && props.expiresInDays <= WARNING_DAYS) {
        return 'expiring';
    }

    return props.plan === 'trial' ? 'trial' : 'active';
});

const color = computed(
    () =>
        ({
            suspended: 'dark',
            expired: 'error',
            expiring: 'warning',
            trial: 'info',
            active: 'success',
        })[state.value] as 'dark' | 'error' | 'warning' | 'info' | 'success',
);

const label = computed(() => {
    if (state.value === 'expiring' || state.value === 'trial') {
        // "ທົດລອງ · ເຫຼືອ 12 ວັນ" — ຈຳນວນວັນຄືສິ່ງທີ່ຜູ້ດູແລຢາກຮູ້ທີ່ສຸດ
        return props.expiresInDays !== null
            ? `${t(`settings.plan.${state.value}`)} · ${t('settings.daysLeft', { days: props.expiresInDays })}`
            : t(`settings.plan.${state.value}`);
    }

    return t(`settings.plan.${state.value}`);
});
</script>
