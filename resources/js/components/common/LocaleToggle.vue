<template>
    <div
        role="group"
        :aria-label="t('common.language')"
        class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-white p-1 dark:border-gray-800 dark:bg-gray-900"
    >
        <button
            v-for="language in languages"
            :key="language.code"
            type="button"
            :aria-pressed="locale === language.code"
            :lang="language.code"
            class="flex items-center gap-2 rounded-full px-3 py-1.5 text-theme-sm font-medium transition-colors"
            :class="
                locale === language.code
                    ? 'bg-brand-500 text-white'
                    : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white/90'
            "
            @click="select(language.code)"
        >
            <img
                :src="language.flag"
                alt=""
                class="h-4 w-4 rounded-full object-cover"
            />
            <span>{{ language.label }}</span>
        </button>
    </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { setLocale } from '@/i18n';
import type { SupportedLocale } from '@/i18n';

const { t, locale } = useI18n();

/**
 * ປ້າຍຊື່ພາສາຂຽນດ້ວຍພາສານັ້ນເອງ (endonym) ບໍ່ແມ່ນແປຕາມພາສາປັດຈຸບັນ —
 * ຄົນທີ່ອ່ານພາສາທີ່ກຳລັງສະແດງຢູ່ບໍ່ອອກ ຕ້ອງຫາພາສາຂອງຕົນເຫັນໄດ້ທັນທີ.
 */
const languages: { code: SupportedLocale; label: string; flag: string }[] = [
    { code: 'lo', label: 'ລາວ', flag: '/images/flags/lo.svg' },
    { code: 'en', label: 'English', flag: '/images/flags/en.svg' },
];

function select(code: SupportedLocale): void {
    setLocale(code);
}
</script>
