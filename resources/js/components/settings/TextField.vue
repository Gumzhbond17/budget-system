<template>
    <FormField
        v-slot="{ id, invalid }"
        :label="label"
        :name="name"
        :error="error"
        :hint="hint"
        :required="required"
    >
        <input
            :id="id"
            :type="type"
            :value="modelValue ?? ''"
            :placeholder="placeholder"
            :disabled="disabled"
            :class="[
                'h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:ring-3 focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-50 dark:text-white/90 dark:placeholder:text-white/30 dark:disabled:bg-white/5',
                invalid
                    ? 'border-error-500 focus:border-error-300 focus:ring-error-500/10 dark:border-error-500'
                    : 'border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800',
            ]"
            @input="
                $emit(
                    'update:modelValue',
                    ($event.target as HTMLInputElement).value,
                )
            "
        />
    </FormField>
</template>

<script setup lang="ts">
import type { ApiError } from '@/api/client';
import FormField from './FormField.vue';

interface Props {
    label: string;
    name: string;
    modelValue: string | number | null;
    type?: string;
    placeholder?: string;
    hint?: string;
    error?: ApiError | null;
    required?: boolean;
    disabled?: boolean;
}

withDefaults(defineProps<Props>(), {
    type: 'text',
    error: null,
    required: false,
    disabled: false,
});

defineEmits<{ 'update:modelValue': [value: string] }>();
</script>
