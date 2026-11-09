<template>
    <!-- ອາຍຸມາຈາກອົງກອນ ບໍ່ແມ່ນຈາກບັນຊີ — ຜູ້ດູແລລະບົບຈຶ່ງບໍ່ໝົດອາຍຸ -->
    <span
        v-if="user?.is_platform_admin"
        class="text-sm text-gray-500 dark:text-gray-400"
    >
        {{ t('settings.users.noExpiry') }}
    </span>

    <AccessBadge
        v-else-if="user"
        :plan="user.access.plan"
        :has-access="user.access.can_access"
        :expires-in-days="user.access.expires_in_days"
    />
</template>

<script setup lang="ts">
import type { ICellRendererParams } from 'ag-grid-community';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AccessBadge from '@/components/settings/AccessBadge.vue';
import type { AppUser } from '@/types/settings';

/**
 * ຊ່ອງສະຖານະການເຂົ້າເຖິງຂອງຜູ້ໃຊ້.
 *
 * ຕ່າງຈາກ `AccessBadgeCell` ຂອງອົງກອນ: ຜູ້ດູແລລະບົບບໍ່ໄດ້ຜູກກັບອົງກອນ
 * ຈຶ່ງບໍ່ມີວັນໝົດອາຍຸໃຫ້ສະແດງເລີຍ.
 */
const props = defineProps<{ params: ICellRendererParams<AppUser> }>();

const { t } = useI18n();

const user = computed(() => props.params.data ?? null);
</script>
