<template>
    <Modal v-if="open" full-screen-backdrop @close="$emit('cancel')">
        <template #body>
            <div
                class="relative z-10 m-4 w-full max-w-md rounded-3xl bg-white p-6 lg:p-8 dark:bg-gray-900"
            >
                <div
                    class="mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-error-50 dark:bg-error-500/15"
                >
                    <ErrorHexaIcon class="text-error-500" />
                </div>

                <h4
                    class="mb-2 text-lg font-semibold text-gray-800 dark:text-white/90"
                >
                    {{ t(title) }}
                </h4>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ message }}
                </p>

                <!--
                    server ປະຕິເສດການລຶບ (ເຊັ່ນ 409 ເມື່ອມີຄົນອື່ນເພີ່ມລາຍການ
                    ໃສ່ໝວດນີ້ພໍດີ) — ບອກຢູ່ນີ້ ບໍ່ດັ່ງນັ້ນກົດແລ້ວຈະງຽບໄປເສີຍໆ
                -->
                <p
                    v-if="error"
                    role="alert"
                    class="mt-4 rounded-lg bg-error-50 p-3 text-sm text-error-700 dark:bg-error-500/10 dark:text-error-400"
                >
                    {{ error }}
                </p>

                <div class="mt-7 flex items-center justify-end gap-3">
                    <Button
                        variant="outline"
                        size="sm"
                        :on-click="() => $emit('cancel')"
                    >
                        {{ t('common.cancel') }}
                    </Button>

                    <button
                        type="button"
                        :disabled="saving"
                        class="inline-flex items-center justify-center rounded-lg bg-error-500 px-4 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-error-600 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="$emit('confirm')"
                    >
                        {{ saving ? t('common.saving') : t('common.delete') }}
                    </button>
                </div>
            </div>
        </template>
    </Modal>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import Modal from '@/components/ui/Modal.vue';
import { ErrorHexaIcon } from '@/icons';

interface Props {
    open: boolean;
    /** i18n key */
    title: string;
    /** ຂໍ້ຄວາມທີ່ແປແລ້ວ (ມັກມີຊື່ record ຢູ່ໃນນັ້ນ) */
    message: string;
    saving?: boolean;
    /** ຂໍ້ຜິດພາດຈາກ server ຫຼັງກົດຢືນຢັນ — null = ຍັງບໍ່ມີ */
    error?: string | null;
}

withDefaults(defineProps<Props>(), { saving: false, error: null });

defineEmits<{ cancel: []; confirm: [] }>();

const { t } = useI18n();
</script>
