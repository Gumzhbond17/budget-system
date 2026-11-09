<template>
    <div ref="dropdownRef" class="relative">
        <button
            class="flex items-center text-gray-700 dark:text-gray-400"
            :aria-expanded="dropdownOpen"
            aria-haspopup="menu"
            @click.prevent="toggleDropdown"
        >
            <UserAvatar
                :name="auth.user?.name"
                :url="auth.user?.avatar_url"
                class="mr-3 h-11 w-11 text-sm"
            />

            <span class="mr-1 block text-theme-sm font-medium">{{
                auth.user?.name
            }}</span>

            <ChevronDownIcon :class="{ 'rotate-180': dropdownOpen }" />
        </button>

        <div
            v-if="dropdownOpen"
            role="menu"
            class="absolute right-0 mt-[17px] flex w-[260px] flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark"
        >
            <div>
                <span
                    class="block text-theme-sm font-medium text-gray-700 dark:text-gray-400"
                >
                    {{ auth.user?.name }}
                </span>
                <span
                    class="mt-0.5 block text-theme-xs text-gray-500 dark:text-gray-400"
                >
                    {{ auth.user?.email }}
                </span>
            </div>

            <div
                class="mt-3 flex flex-col gap-1 border-t border-gray-200 pt-3 dark:border-gray-800"
            >
                <RouterLink
                    :to="{ name: 'profile' }"
                    role="menuitem"
                    class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-theme-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300"
                    @click="closeDropdown"
                >
                    <ProfileIcon
                        class="h-6 w-6 text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-300"
                    />
                    {{ t('profile.menu') }}
                </RouterLink>

                <button
                    type="button"
                    role="menuitem"
                    :disabled="auth.loading"
                    class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-theme-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-700 disabled:cursor-not-allowed disabled:opacity-60 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300"
                    @click="signOut"
                >
                    <LogoutIcon
                        class="text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-300"
                    />
                    {{ t('auth.signOut') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import UserAvatar from '@/components/common/UserAvatar.vue';
import { ChevronDownIcon, LogoutIcon } from '@/icons';
import { useAuthStore } from '@/stores/auth';
import ProfileIcon from '~icons/tabler/user-circle';

const { t } = useI18n();
const auth = useAuthStore();
const router = useRouter();

const dropdownOpen = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);

function toggleDropdown(): void {
    dropdownOpen.value = !dropdownOpen.value;
}

function closeDropdown(): void {
    dropdownOpen.value = false;
}

async function signOut(): Promise<void> {
    await auth.logout();
    closeDropdown();
    await router.replace({ name: 'login' });
}

function handleClickOutside(event: MouseEvent): void {
    if (
        dropdownRef.value &&
        !dropdownRef.value.contains(event.target as Node)
    ) {
        closeDropdown();
    }
}

onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));
</script>
