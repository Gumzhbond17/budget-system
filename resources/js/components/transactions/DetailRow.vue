<template>
    <div>
        <dt class="text-theme-xs text-gray-500 dark:text-gray-400">
            {{ label }}
        </dt>
        <dd
            class="mt-0.5 text-sm font-medium break-words text-gray-800 dark:text-white/90"
        >
            {{ value || '—' }}
        </dd>
    </div>
</template>

<script setup lang="ts">
/** ຄູ່ ປ້າຍຊື່/ຄ່າ ໃນໜ້າຕ່າງລາຍລະອຽດ — ຂໍ້ຄວາມແປແລ້ວ ບໍ່ແມ່ນ key */
defineProps<{ label: string; value: string | null }>();
</script>
