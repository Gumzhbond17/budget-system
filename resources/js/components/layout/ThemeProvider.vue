<template>
    <slot></slot>
</template>

<script setup lang="ts">
import { ref, provide, onMounted, watch, computed } from 'vue';

type Theme = 'light' | 'dark';

const theme = ref<Theme>('light');
const isInitialized = ref(false);

const isDarkMode = computed(() => theme.value === 'dark');

const toggleTheme = () => {
    theme.value = theme.value === 'light' ? 'dark' : 'light';
};

onMounted(() => {
    const savedTheme = localStorage.getItem('theme') as Theme | null;
    const initialTheme = savedTheme || 'light'; // Default to light theme

    theme.value = initialTheme;
    isInitialized.value = true;
});

watch([theme, isInitialized], ([newTheme, newIsInitialized]) => {
    if (newIsInitialized) {
        localStorage.setItem('theme', newTheme);

        if (newTheme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        // AG Grid ສີດ CSS ຂອງຕົນເອງເຂົ້າ DOM ແລະ ບໍ່ຮູ້ຈັກ class `dark` ຂອງ
        // Tailwind — ມັນເບິ່ງ attribute ນີ້ແທນ (ເບິ່ງ gridTheme ໃນ lib/ag-grid)
        document.body.dataset.agThemeMode = newTheme;
    }
});

provide('theme', {
    isDarkMode,
    toggleTheme,
});
</script>

<script lang="ts">
import { inject } from 'vue';
import type { ComputedRef } from 'vue';

/**
 * ຮູບຮ່າງຂອງສິ່ງທີ່ ThemeProvider ໃຫ້ — `inject()` ທີ່ບໍ່ລະບຸ type ຄືນ `{}`
 * ເຊິ່ງເຮັດໃຫ້ຜູ້ໃຊ້ດຶງ `isDarkMode` ອອກມາບໍ່ໄດ້ຢູ່ຊັ້ນ type.
 */
interface ThemeContext {
    isDarkMode: ComputedRef<boolean>;
    toggleTheme: () => void;
}

export function useTheme(): ThemeContext {
    const theme = inject<ThemeContext>('theme');

    if (!theme) {
        throw new Error('useTheme must be used within a ThemeProvider');
    }

    return theme;
}
</script>
