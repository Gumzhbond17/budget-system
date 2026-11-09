<template>
    <div>
        <PageBreadcrumb :page-title="title" :icon="icon" />

        <div
            class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <!-- ຫົວຕາຕະລາງ: ຄຳອະທິບາຍ + ຄົ້ນຫາ + ປຸ່ມເພີ່ມ -->
            <div
                class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-800"
            >
                <div class="min-w-0">
                    <h3
                        class="text-base font-medium text-gray-800 dark:text-white/90"
                    >
                        {{ t(title) }}
                    </h3>
                    <p
                        v-if="description"
                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    >
                        {{ t(description) }}
                    </p>
                </div>

                <!-- flex-wrap: ໜ້າທີ່ມີຕົວກັ່ນຫຼາຍ (ອັດຕາແລກປ່ຽນ) ລົງແຖວໃໝ່ແທນທີ່ຈະບີບປຸ່ມ -->
                <div class="flex shrink-0 flex-wrap items-center gap-3">
                    <div v-if="searchable" class="relative">
                        <input
                            :value="search"
                            :aria-label="t('settings.searchPlaceholder')"
                            type="search"
                            :placeholder="t('settings.searchPlaceholder')"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3.5 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-none sm:w-56 dark:border-gray-700 dark:text-white/90 dark:placeholder:text-white/30"
                            @input="
                                $emit(
                                    'update:search',
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                        />
                    </div>

                    <slot name="filters" />

                    <Button
                        v-if="canCreate"
                        size="compact"
                        :start-icon="PlusIcon"
                        :on-click="() => $emit('create')"
                    >
                        {{ createLabel ? t(createLabel) : t('common.add') }}
                    </Button>
                </div>
            </div>

            <!--
                ສະຖານະ "ກຳລັງໂຫຼດ / ບໍ່ມີລາຍການ" ຢູ່ໃນ grid ເອງ ບໍ່ໄດ້ຢູ່ຊັ້ນນີ້
                ອີກຕໍ່ໄປ: ຫົວຕາຕະລາງຄືບ່ອນທີ່ຕົວກັ່ນຕອງຢູ່ ຖ້າຫົວຫາຍໄປພ້ອມ
                ແຖວ ຄົນທີ່ກັ່ນຈົນບໍ່ເຫຼືອຫຍັງຈະຕິດຄ້າງ ຖອຍກັບບໍ່ໄດ້.

                ຂໍ້ຜິດພາດຍັງຢູ່ນີ້ ເພາະມັນເປັນເລື່ອງຂອງທັງໜ້າ ບໍ່ແມ່ນຂອງຕາຕະລາງ.
            -->
            <Alert
                v-if="error"
                variant="error"
                :title="t('settings.loadFailed')"
                :message="error"
                class="m-5"
            />

            <slot />
        </div>
    </div>
</template>

<script setup lang="ts">
import type { Component } from 'vue';
import { useI18n } from 'vue-i18n';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import { PlusIcon } from '@/icons';

interface Props {
    title: string;
    description?: string;
    error?: string | null;
    canCreate?: boolean;
    createLabel?: string;
    searchable?: boolean;
    search?: string;
    /** ໄອຄອນຂ້າງຫົວຂໍ້ໜ້າ — ສົ່ງຕໍ່ໃຫ້ PageBreadcrumb ຊື່ໆ */
    icon?: Component;
}

withDefaults(defineProps<Props>(), {
    error: null,
    canCreate: false,
    searchable: true,
    search: '',
});

defineEmits<{
    create: [];
    'update:search': [value: string];
}>();

const { t } = useI18n();
</script>
