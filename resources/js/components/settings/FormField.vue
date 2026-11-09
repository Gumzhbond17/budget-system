<template>
    <div>
        <label
            :for="id"
            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
        >
            {{ t(label) }}
            <span v-if="required" class="text-error-500">*</span>
        </label>

        <slot :id="id" :invalid="Boolean(message)" />

        <p v-if="message" class="mt-1.5 text-theme-xs text-error-500">
            {{ message }}
        </p>
        <p
            v-else-if="hint"
            class="mt-1.5 text-theme-xs text-gray-500 dark:text-gray-400"
        >
            {{ t(hint) }}
        </p>
    </div>
</template>

<script setup lang="ts">
import { computed, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import { firstError } from '@/api/client';
import type { ApiError } from '@/api/client';

interface Props {
    label: string;
    /** ຊື່ field ຢູ່ຝັ່ງ server — ໃຊ້ຫາຂໍ້ຄວາມຈາກ 422 */
    name: string;
    error?: ApiError | null;
    hint?: string;
    required?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    error: null,
    required: false,
});

const { t } = useI18n();

const id = useId();

const message = computed(() =>
    props.error ? firstError(props.error.errors, props.name) : null,
);
</script>
