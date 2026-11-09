<template>
    <FullScreenLayout>
        <div class="flex min-h-screen items-center justify-center p-6">
            <div class="w-full max-w-md text-center">
                <div
                    class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-full bg-warning-50 dark:bg-warning-500/15"
                >
                    <ErrorHexaIcon class="text-warning-500" />
                </div>

                <h1
                    class="mb-3 text-2xl font-semibold text-gray-800 dark:text-white/90"
                >
                    {{ t(titleKey) }}
                </h1>

                <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('accessExpired.body', { company: companyName }) }}
                </p>

                <p class="mb-8 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('accessExpired.contact') }}
                </p>

                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white transition hover:bg-brand-600"
                    @click="signOut"
                >
                    {{ t('header.signOut') }}
                </button>
            </div>
        </div>
    </FullScreenLayout>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import FullScreenLayout from '@/components/layout/FullScreenLayout.vue';
import { ErrorHexaIcon } from '@/icons';
import { useAuthStore } from '@/stores/auth';

const { t } = useI18n();
const auth = useAuthStore();
const router = useRouter();

const companyName = computed(() => auth.user?.company?.name ?? '');

/** ຖືກລະງັບ ກັບ ໝົດອາຍຸ ຄືຄົນລະເລື່ອງ — ບອກໃຫ້ຖືກຈະຊ່ວຍໃຫ້ຕິດຕໍ່ຖືກຄົນ */
const titleKey = computed(() =>
    auth.user?.company?.plan === 'suspended'
        ? 'accessExpired.suspended'
        : 'accessExpired.title',
);

async function signOut(): Promise<void> {
    await auth.logout();
    await router.push({ name: 'login' });
}
</script>
