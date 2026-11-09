<template>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h2
            class="flex items-center gap-2.5 text-xl font-semibold text-gray-800 dark:text-white/90"
        >
            <!--
                ໄອຄອນເປັນການຕົກແຕ່ງ — ຂໍ້ຄວາມຂ້າງໆບອກຄວາມໝາຍຄົບຢູ່ແລ້ວ
                ຈຶ່ງ `aria-hidden` ບໍ່ດັ່ງນັ້ນ screen reader ຈະອ່ານຊ້ຳສອງເທື່ອ.
                ວາງໃນກອບສີແບຣນຄືກັບຫົວຂໍ້ຂອງ FormModal ເພື່ອໃຫ້ໜ້າຈໍທັງແອັບ
                ໃຊ້ພາສາທາງສາຍຕາອັນດຽວກັນ.
            -->
            <span
                v-if="icon"
                aria-hidden="true"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-500 dark:bg-brand-500/15"
            >
                <component :is="icon" class="h-5 w-5" />
            </span>

            {{ t(pageTitle) }}
        </h2>
        <nav>
            <ol class="flex items-center gap-1.5">
                <li>
                    <router-link
                        class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400"
                        to="/"
                    >
                        {{ t('common.home') }}
                        <svg
                            class="stroke-current"
                            width="17"
                            height="16"
                            viewBox="0 0 17 16"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                d="M6.0765 12.667L10.2432 8.50033L6.0765 4.33366"
                                stroke=""
                                stroke-width="1.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </router-link>
                </li>
                <li class="text-sm text-gray-800 dark:text-white/90">
                    {{ t(pageTitle) }}
                </li>
            </ol>
        </nav>
    </div>
</template>

<script setup lang="ts">
import type { Component } from 'vue';
import { useI18n } from 'vue-i18n';

interface BreadcrumbProps {
    pageTitle: string;
    /**
     * ໄອຄອນຂ້າງຫົວຂໍ້ — ສົ່ງ component ມາ ເຊັ່ນ `~icons/tabler/book-2`
     * (unplugin-icons ແປງ Iconify ເປັນ component ຕອນ build ຈຶ່ງບໍ່ມີ runtime
     * ຫຼື request ເພີ່ມ ແລະ ໄອຄອນທີ່ບໍ່ໄດ້ໃຊ້ຈະບໍ່ຕິດເຂົ້າ bundle).
     *
     * ບໍ່ບັງຄັບ ເພື່ອບໍ່ໃຫ້ໜ້າທີ່ຍັງບໍ່ໄດ້ເລືອກໄອຄອນພັງ — ຫົວຂໍ້ຈະສະແດງ
     * ແບບບໍ່ມີໄອຄອນຄືເກົ່າ.
     */
    icon?: Component;
}

defineProps<BreadcrumbProps>();

const { t } = useI18n();
</script>
