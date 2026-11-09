<template>
    <Modal v-if="open" full-screen-backdrop @close="$emit('cancel')">
        <template #body>
            <div
                class="relative z-10 m-4 w-full max-w-md rounded-3xl bg-white p-6 lg:p-8 dark:bg-gray-900"
            >
                <div
                    class="mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-warning-50 dark:bg-warning-500/15"
                >
                    <LockIcon class="h-6 w-6 text-warning-500" />
                </div>

                <h4
                    class="mb-2 text-lg font-semibold text-gray-800 dark:text-white/90"
                >
                    {{ t('settings.credentials.revealTitle') }}
                </h4>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ t('settings.credentials.revealHint', { name: title }) }}
                </p>

                <!--
                    ເປັນ <form> ບໍ່ແມ່ນ <div>: ຜູ້ໃຊ້ພິມລະຫັດແລ້ວກົດ Enter ເປັນ
                    ນິດໄສ — ຖ້າບໍ່ມີ form ການກົດ Enter ຈະບໍ່ເຮັດຫຍັງເລີຍ
                -->
                <form class="mt-5" @submit.prevent="submit">
                    <!--
                        `autocomplete="current-password"` ບອກ password manager
                        ວ່ານີ້ຄືລະຫັດຂອງ **ຜູ້ໃຊ້ເອງ** ບໍ່ແມ່ນລະຫັດໃໝ່ຂອງບັນຊີ
                        ໃດໜຶ່ງ — ບໍ່ດັ່ງນັ້ນມັນຈະສະເໜີບັນທຶກທັບລະຫັດທີ່ຜິດ
                    -->
                    <label
                        :for="inputId"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        {{ t('settings.credentials.yourPassword') }}
                    </label>

                    <input
                        :id="inputId"
                        ref="passwordInput"
                        v-model="password"
                        type="password"
                        autocomplete="current-password"
                        :placeholder="t('auth.enterPassword')"
                        :aria-invalid="error !== null"
                        :class="[
                            'h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:ring-3 focus:outline-none dark:text-white/90 dark:placeholder:text-white/30',
                            error
                                ? 'border-error-500 focus:border-error-300 focus:ring-error-500/10 dark:border-error-500'
                                : 'border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800',
                        ]"
                    />

                    <p
                        v-if="error"
                        role="alert"
                        class="mt-1.5 text-theme-xs text-error-500"
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
                            type="submit"
                            :disabled="saving || password === ''"
                            class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{
                                saving
                                    ? t('settings.credentials.revealing')
                                    : t('settings.credentials.revealAction')
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </template>
    </Modal>
</template>

<script setup lang="ts">
import { nextTick, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from '@/components/ui/Button.vue';
import Modal from '@/components/ui/Modal.vue';
import LockIcon from '~icons/tabler/lock';

interface Props {
    open: boolean;
    /** ຊື່ຂອງບັນຊີທີ່ກຳລັງຈະເປີດເບິ່ງ — ບອກໃຫ້ຮູ້ວ່າກົດຖືກແຖວບໍ */
    title: string;
    saving?: boolean;
    /** ຂໍ້ຄວາມຈາກ server (ເຊັ່ນ "ລະຫັດຜ່ານບໍ່ຖືກຕ້ອງ") — null = ຍັງບໍ່ມີ */
    error?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    saving: false,
    error: null,
});

const emit = defineEmits<{ cancel: []; confirm: [password: string] }>();

const { t } = useI18n();

const inputId = useId();
const password = ref('');
const passwordInput = ref<HTMLInputElement | null>(null);

/**
 * ລ້າງຄ່າທຸກເທື່ອທີ່ເປີດ ແລະ **ທຸກເທື່ອທີ່ປິດ**.
 *
 * ລ້າງຕອນປິດຄືສ່ວນສຳຄັນ: ຖ້າປະໄວ້ ລະຫັດຜ່ານຂອງຜູ້ໃຊ້ຈະຄ້າງຢູ່ໃນ memory
 * ຂອງ component ຕະຫຼອດເວລາທີ່ໜ້ານີ້ເປີດຢູ່ ທັງທີ່ໃຊ້ໄປແລ້ວ.
 */
watch(
    () => props.open,
    async (open) => {
        password.value = '';

        if (open) {
            await nextTick();
            passwordInput.value?.focus();
        }
    },
);

function submit(): void {
    if (props.saving || password.value === '') {
        return;
    }

    emit('confirm', password.value);
}
</script>
