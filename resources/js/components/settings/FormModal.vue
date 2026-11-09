<template>
    <Modal v-if="open" full-screen-backdrop @close="$emit('close')">
        <template #body>
            <!--
                `flex-col` + `max-h`: ກ້ອງນີ້ບໍ່ເຄີຍສູງເກີນຈໍ ແລະ ບໍ່ເລື່ອນເອງ.
                ພາຍໃນແບ່ງເປັນ 3 ສ່ວນ — ຫົວຂໍ້ (ນິ້ງ) / ຊ່ອງຕ່າງໆ (ເລື່ອນໄດ້
                ເມື່ອຈຳເປັນ) / ແຖວປຸ່ມ (ນິ້ງ) — ຈຶ່ງເຫັນປຸ່ມ "ບັນທຶກ" ສະເໝີ
                ໂດຍທີ່ຕົວ modal ຢູ່ກັບທີ່ບໍ່ຂະຍັບ
            -->
            <div
                class="relative z-10 m-4 flex max-h-[calc(100dvh-2rem)] w-full flex-col rounded-3xl bg-white p-6 lg:p-8 dark:bg-gray-900"
                :class="wide ? 'max-w-7xl' : 'max-w-2xl'"
            >
                <button
                    type="button"
                    class="absolute top-5 right-5 flex h-9 w-9 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/5 dark:hover:text-white/80"
                    :aria-label="t('common.close')"
                    @click="$emit('close')"
                >
                    &times;
                </button>

                <!-- `shrink-0` ທົ່ວສ່ວນຫົວ: ຢູ່ໃນ flex-col ຄ່າເລີ່ມຕົ້ນຄືຫົດໄດ້
                     ຊຶ່ງຈະບີບຫົວຂໍ້ໃຫ້ແປຢູ່ຈໍເຕ້ຍ ແທນທີ່ຈະໃຫ້ຊ່ອງເລື່ອນແທນ -->
                <div class="mb-1 flex shrink-0 items-center gap-3 pr-10">
                    <!--
                        ໄອຄອນຢູ່ໃນກອບສີແບຣນ — ບອກປະເພດຂອງຟອມໄດ້ໃນພິບຕາດຽວ
                        ຕອນມີຫຼາຍໜ້າຕ່າງທີ່ໜ້າຕາຄືກັນ.
                        `aria-hidden` ຜ່ານ `<span>`: ໄອຄອນເປັນການຕົກແຕ່ງ
                        ຫົວຂໍ້ຂ້າງໆບອກຄວາມໝາຍຄົບຢູ່ແລ້ວ
                    -->
                    <span
                        v-if="icon"
                        aria-hidden="true"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/15"
                    >
                        <component :is="icon" class="h-5 w-5" />
                    </span>

                    <h4
                        class="text-xl font-semibold text-gray-800 dark:text-white/90"
                    >
                        {{ t(title) }}
                    </h4>
                </div>
                <p
                    v-if="subtitle"
                    class="mb-5 shrink-0 text-sm text-gray-500 dark:text-gray-400"
                    :class="icon ? 'pl-13' : ''"
                >
                    {{ t(subtitle) }}
                </p>

                <!--
          ຂໍ້ຜິດພາດລະດັບຟອມ (ບໍ່ຜູກກັບ field ໃດ) — ເຊັ່ນ 403 ຫຼື ກົດເກນ
          ທາງທຸລະກິດຈາກ server. ຂໍ້ຜິດພາດຕໍ່ field ສະແດງໃຕ້ຊ່ອງນັ້ນເອງ.
        -->
                <Alert
                    v-if="generalError"
                    variant="error"
                    :title="t('settings.saveFailed')"
                    :message="generalError"
                    class="mb-5 shrink-0"
                />

                <form
                    class="flex min-h-0 flex-1 flex-col"
                    @submit.prevent="$emit('submit')"
                >
                    <!--
                        ສະເພາະຊ່ອງຕ່າງໆທີ່ເລື່ອນໄດ້ ບໍ່ແມ່ນທັງຟອມ — ແຖບເລື່ອນຈຶ່ງ
                        ບໍ່ພາປຸ່ມ "ບັນທຶກ" ຫາຍໄປນຳ.

                        `min-h-0` ຈຳເປັນ: ຄ່າເລີ່ມຕົ້ນຂອງ flex item ຄື
                        `min-height:auto` ເຊິ່ງບໍ່ຍອມຫົດລົງກວ່າເນື້ອໃນ ແລ້ວ
                        `overflow-y-auto` ຈະບໍ່ມີຜົນເລີຍ ກ້ອງຈະລົ້ນອອກແທນ.

                        ຢູ່ຈໍປົກກະຕິເນື້ອໃນສັ້ນກວ່າພື້ນທີ່ ຈຶ່ງບໍ່ມີແຖບເລື່ອນ
                        ໃຫ້ເຫັນ — ມັນປາກົດສະເພາະຈໍເຕ້ຍທີ່ໃສ່ບໍ່ໝົດແທ້ໆ
                    -->
                    <div class="min-h-0 flex-1 overflow-y-auto pr-1">
                        <slot />
                    </div>

                    <div
                        class="mt-7 flex shrink-0 items-center justify-end gap-3"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            :on-click="() => $emit('close')"
                        >
                            {{ t('common.cancel') }}
                        </Button>

                        <button
                            type="submit"
                            :disabled="saving"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ saving ? t('common.saving') : t('common.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </template>
    </Modal>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { Component } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ApiError } from '@/api/client';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import Modal from '@/components/ui/Modal.vue';

interface Props {
    open: boolean;
    title: string;
    subtitle?: string;
    saving?: boolean;
    error?: ApiError | null;
    /**
     * ໄອຄອນຂ້າງຫົວຂໍ້ — ສົ່ງ component ມາ ເຊັ່ນ `~icons/tabler/building-bank`
     * (unplugin-icons ແປງ Iconify ເປັນ component ຕອນ build ຈຶ່ງບໍ່ມີ runtime
     * ຫຼື request ເພີ່ມ, ແລະ ໄອຄອນທີ່ບໍ່ໄດ້ໃຊ້ຈະບໍ່ຕິດເຂົ້າ bundle)
     */
    icon?: Component;
    /**
     * ຟອມກວ້າງ (max-w-7xl = 1280px) ສຳລັບຟອມທຸລະກຳທີ່ມີຊ່ອງຫຼາຍ — ວາງເປັນ
     * 4 ຖັນ ແລ້ວຄວາມສູງຫຼຸດລົງຈົນບໍ່ຕ້ອງເລື່ອນ. ຖັນກວ້າງຂຶ້ນຍັງເຮັດໃຫ້ຂໍ້ຄວາມ
     * ຄຳແນະນຳໃຕ້ຊ່ອງຕັດແຖວໜ້ອຍລົງ ຟອມຈຶ່ງເຕ້ຍລົງອີກ.
     *
     * ຟອມຕັ້ງຄ່າອື່ນມີ 3–5 ຊ່ອງ ຈຶ່ງຍັງໃຊ້ max-w-2xl ຄືເກົ່າ — ກວ້າງກວ່ານັ້ນ
     * ຊ່ອງດຽວຈະຍືດເຕັມແຖວຈົນອ່ານຍາກ.
     */
    wide?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    saving: false,
    error: null,
    wide: false,
});

defineEmits<{ close: []; submit: [] }>();

const { t } = useI18n();

/**
 * 422 ຖືກສະແດງໃຕ້ຊ່ອງແຕ່ລະຊ່ອງຢູ່ແລ້ວ — ຢູ່ນີ້ສະແດງສະເພາະ error
 * ປະເພດອື່ນ (403, 500, ຕິດຕໍ່ server ບໍ່ໄດ້) ບໍ່ດັ່ງນັ້ນຜູ້ໃຊ້ຈະເຫັນ
 * ຂໍ້ຄວາມຊ້ຳສອງບ່ອນ.
 */
const generalError = computed(() => {
    if (!props.error) {
        return null;
    }

    return Object.keys(props.error.errors).length > 0
        ? null
        : props.error.message;
});
</script>
