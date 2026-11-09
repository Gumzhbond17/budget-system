<template>
    <FormField
        v-slot="{ id, invalid }"
        :label="label"
        :name="name"
        :error="error"
        :hint="hint"
        :required="required"
    >
        <DateInput
            :id="id"
            :model-value="modelValue"
            :disabled="disabled"
            :max-today="maxToday"
            :class="[
                'h-11 w-full rounded-lg border bg-transparent py-2.5 pr-4 pl-11 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:ring-3 focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-50 dark:text-white/90 dark:placeholder:text-white/30 dark:disabled:bg-white/5',
                invalid
                    ? 'border-error-500 focus:border-error-300 focus:ring-error-500/10 dark:border-error-500'
                    : 'border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800',
            ]"
            @update:model-value="$emit('update:modelValue', $event)"
        />
    </FormField>
</template>

<script setup lang="ts">
import type { ApiError } from '@/api/client';
import DateInput from '@/components/forms/DateInput.vue';
import FormField from '@/components/settings/FormField.vue';

/**
 * ຊ່ອງວັນທີສຳລັບຟອມ — ປ້າຍຊື່, ດາວແດງ ແລະ ຂໍ້ຄວາມ 422 ຄືກັບ TextField.
 * ຮູບແບບວັນທີ ແລະ ພາສາຂອງປະຕິທິນຢູ່ໃນ DateInput.
 */
interface Props {
    label: string;
    /** ຊື່ field ຢູ່ຝັ່ງ server — ໃຊ້ຫາຂໍ້ຄວາມ 422 */
    name: string;
    /** ຄ່າໃນຮູບແບບ ISO 'YYYY-MM-DD' */
    modelValue: string | null;
    hint?: string;
    error?: ApiError | null;
    required?: boolean;
    disabled?: boolean;
    maxToday?: boolean;
}

withDefaults(defineProps<Props>(), {
    error: null,
    required: false,
    disabled: false,
    maxToday: false,
});

defineEmits<{ 'update:modelValue': [value: string] }>();
</script>
