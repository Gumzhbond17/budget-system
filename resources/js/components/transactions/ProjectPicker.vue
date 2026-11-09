<template>
    <div
        class="mb-6 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]"
    >
        <label class="block">
            <span
                class="mb-1.5 block text-theme-xs font-medium text-gray-500 dark:text-gray-400"
            >
                {{ t('ledger.project') }}
            </span>
            <SearchableSelect
                :model-value="modelValue"
                variant="filter"
                class="w-full sm:max-w-md"
                :options="projectOptions"
                placeholder="ledger.selectProject"
                @update:model-value="$emit('update:modelValue', $event)"
            />
        </label>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SearchableSelect from '@/components/common/SearchableSelect.vue';
import type { Project } from '@/types/settings';

const props = defineProps<{ modelValue: string; projects: Project[] }>();
defineEmits<{ 'update:modelValue': [value: string] }>();

const { t } = useI18n();

const projectOptions = computed(() =>
    props.projects.map((project) => ({
        value: project.uuid,
        label: project.name,
        secondary: project.code,
        translate: false,
    })),
);
</script>
