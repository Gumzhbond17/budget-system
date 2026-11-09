<template>
    <div class="space-y-6">
        <!-- Normal Textarea -->
        <div>
            <label
                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
            >
                {{ t('forms.description') }}
            </label>
            <textarea
                v-model="normalDescription"
                :placeholder="t('forms.enterDescription')"
                rows="6"
                class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
            ></textarea>
        </div>

        <!-- Disabled Textarea -->
        <div>
            <label
                class="mb-1.5 block text-sm font-medium text-gray-300 dark:text-white/15"
            >
                {{ t('forms.description') }}
            </label>
            <textarea
                v-model="disabledDescription"
                :placeholder="t('forms.enterDescription')"
                rows="6"
                disabled
                class="dark:bg-dark-900 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:shadow-focus-ring focus:ring-0 focus:outline-hidden disabled:border-gray-100 disabled:bg-gray-50 disabled:placeholder:text-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800 dark:disabled:border-gray-800 dark:disabled:bg-white/[0.03] dark:disabled:placeholder:text-white/15"
            ></textarea>
        </div>

        <!-- Error State Textarea -->
        <div>
            <label
                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
            >
                {{ t('forms.description') }}
            </label>
            <textarea
                v-model="errorDescription"
                :placeholder="t('forms.enterDescription')"
                rows="6"
                class="dark:bg-dark-900 w-full rounded-lg border border-error-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-error-300 focus:ring-3 focus:ring-error-500/10 focus:outline-hidden dark:border-error-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-error-800"
            ></textarea>
            <p class="mt-1.5 text-theme-xs text-error-500">
                {{ t('forms.textareaErrorMessage') }}
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const normalDescription = ref('');
const disabledDescription = ref(t('forms.textareaDisabled'));
const errorDescription = ref('');
</script>
