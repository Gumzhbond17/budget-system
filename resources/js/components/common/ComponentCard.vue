<template>
    <div
        :class="[
            'rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]',
            className,
        ]"
    >
        <!-- Card Header -->
        <div class="px-6 py-5">
            <h3 class="text-base font-medium text-gray-800 dark:text-white/90">
                {{ t(title) }}
            </h3>
            <p
                v-if="desc"
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                {{ t(desc) }}
            </p>
        </div>

        <!-- Card Body -->
        <div class="border-t border-gray-100 p-4 sm:p-6 dark:border-gray-800">
            <div class="space-y-5">
                <slot></slot>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';

interface Props {
    title: string;
    className?: string;
    desc?: string;
}

defineProps<Props>();

const { t } = useI18n();
</script>
