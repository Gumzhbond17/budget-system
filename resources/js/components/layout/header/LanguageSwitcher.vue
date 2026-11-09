<template>
    <div class="relative" ref="dropdownRef">
        <button
            class="relative flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
            @click="toggleDropdown"
            :aria-label="t('common.language')"
        >
            <img
                :src="currentLanguage.flag"
                :alt="currentLanguage.label"
                class="h-5 w-5 overflow-hidden rounded-full object-cover"
            />
        </button>

        <!-- Dropdown Start -->
        <div
            v-if="dropdownOpen"
            class="absolute right-0 mt-[17px] flex w-[180px] flex-col rounded-2xl border border-gray-200 bg-white p-2 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark"
        >
            <button
                v-for="language in languages"
                :key="language.code"
                @click="selectLanguage(language.code)"
                class="flex items-center gap-3 rounded-lg px-3 py-2 text-left text-theme-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5"
                :class="{
                    'bg-gray-100 text-gray-800 dark:bg-white/5 dark:text-white/90':
                        locale === language.code,
                }"
            >
                <img
                    :src="language.flag"
                    :alt="language.label"
                    class="h-5 w-5 rounded-full object-cover"
                />
                <span>{{ language.label }}</span>
                <CheckIcon
                    v-if="locale === language.code"
                    class="ml-auto h-4 w-4 text-brand-500"
                />
            </button>
        </div>
        <!-- Dropdown End -->
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { setLocale } from '@/i18n';
import type { SupportedLocale } from '@/i18n';
import { CheckIcon } from '@/icons';

const { t, locale } = useI18n();

const languages: { code: SupportedLocale; label: string; flag: string }[] = [
    { code: 'en', label: 'English', flag: '/images/flags/en.svg' },
    { code: 'lo', label: 'ລາວ', flag: '/images/flags/lo.svg' },
];

const dropdownOpen = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);

const currentLanguage = computed(
    () =>
        languages.find((language) => language.code === locale.value) ??
        languages[0],
);

const toggleDropdown = () => {
    dropdownOpen.value = !dropdownOpen.value;
};

const closeDropdown = () => {
    dropdownOpen.value = false;
};

const selectLanguage = (code: SupportedLocale) => {
    setLocale(code);
    closeDropdown();
};

const handleClickOutside = (event: MouseEvent) => {
    if (
        dropdownRef.value &&
        !dropdownRef.value.contains(event.target as Node)
    ) {
        closeDropdown();
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>
