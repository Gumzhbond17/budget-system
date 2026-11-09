<template>
    <div class="min-h-screen xl:flex">
        <app-sidebar />
        <Backdrop />
        <div
            class="flex-1 transition-[margin] duration-150 ease-out"
            :class="[
                isExpanded || isHovered ? 'lg:ml-[290px]' : 'lg:ml-[90px]',
            ]"
        >
            <app-header />
            <div class="mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6">
                <slot></slot>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useSidebar } from '@/composables/useSidebar';
import AppHeader from './AppHeader.vue';
import AppSidebar from './AppSidebar.vue';
import Backdrop from './Backdrop.vue';
const { isExpanded, isHovered } = useSidebar();
</script>
