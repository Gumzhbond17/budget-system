<template>
    <span
        class="inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-100 font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-400"
    >
        <img
            v-if="url && !broken"
            :src="url"
            :alt="name ?? ''"
            class="h-full w-full object-cover"
            @error="broken = true"
        />
        <template v-else>{{ initials }}</template>
    </span>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';

/**
 * ຮູບໂປຣໄຟລ໌ວົງມົນ — ບໍ່ມີຮູບ ຫຼື ໂຫຼດຮູບບໍ່ໄດ້ = ຕົວອັກສອນຫຍໍ້ຂອງຊື່.
 *
 * ຂະໜາດ ແລະ ຂະໜາດຕົວອັກສອນມາຈາກ class ຂອງຜູ້ໃຊ້ (h-11 w-11 text-sm …).
 *
 * `@error` ສຳຄັນ: signed URL ໝົດອາຍຸໃນ 24 ຊົ່ວໂມງ — ແທັບທີ່ເປີດຄ້າງຂ້າມຄືນ
 * ຈະໄດ້ 403 ແລ້ວ browser ສະແດງໄອຄອນຮູບແຕກ. ຕົກມາເປັນຕົວອັກສອນແທນ.
 */
const props = defineProps<{
    name?: string | null;
    url?: string | null;
}>();

const broken = ref(false);

// ຮູບໃໝ່ (ອັບແລ້ວ ຫຼື ໂຫຼດໂປຣໄຟລ໌ຄືນ) → ລອງໂຫຼດອີກຄັ້ງ
watch(
    () => props.url,
    () => {
        broken.value = false;
    },
);

const initials = computed(() => {
    const name = props.name?.trim();

    if (!name) {
        return '?';
    }

    // ຕົວອັກສອນຕົ້ນຂອງສອງຄຳທຳອິດ — ຮອງຮັບທັງລາວ ແລະ ລາຕິນ
    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => [...part][0] ?? '')
        .join('')
        .toUpperCase();
});
</script>
