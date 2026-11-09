<template>
    <AccessBadge
        v-if="company"
        :plan="company.plan"
        :has-access="company.has_active_access"
        :expires-in-days="company.expires_in_days"
    />
</template>

<script setup lang="ts">
import type { ICellRendererParams } from 'ag-grid-community';
import { computed } from 'vue';
import AccessBadge from '@/components/settings/AccessBadge.vue';
import type { Company } from '@/types/settings';

/**
 * ຊ່ອງສະຖານະການເຂົ້າເຖິງຂອງອົງກອນ.
 *
 * ຫຸ້ມ `AccessBadge` ໄວ້ເພາະມັນຕັດສິນສີຈາກສາມ field ພ້ອມກັນ (plan,
 * has_active_access, expires_in_days) ຈຶ່ງໃສ່ຜ່ານ `BadgeCell` ທົ່ວໄປບໍ່ໄດ້.
 */
const props = defineProps<{ params: ICellRendererParams<Company> }>();

const company = computed(() => props.params.data ?? null);
</script>
