<template>
    <router-link to="/" class="flex items-center gap-2 lg:hidden">
        <AppLogo :size="32" />
        <span class="text-lg font-bold text-gray-800 dark:text-white">{{
            t('app.name')
        }}</span>
    </router-link>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { RouterLink } from 'vue-router';
import AppLogo from '@/components/common/AppLogo.vue';

const { t } = useI18n();
</script>
