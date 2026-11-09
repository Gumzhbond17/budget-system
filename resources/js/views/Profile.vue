<template>
    <AdminLayout>
        <PageBreadcrumb :page-title="t('profile.title')" :icon="PageIcon" />

        <Alert
            v-if="loadError"
            variant="error"
            :title="t('settings.loadFailed')"
            :message="loadError"
            class="mb-6"
        />

        <div class="space-y-6">
            <!-- ຫົວບັດ: ຊື່ ອີເມວ ແລະ ສະຖານະບັນຊີ -->
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 lg:p-6 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                    <!--
                        ກົດຮູບ = ເປີດເບິ່ງເຕັມຈໍ (lightbox) ຖ້າມີຮູບ, ບໍ່ມີຮູບ = ເລືອກຮູບ.
                        ປຸ່ມກ້ອງມຸມລຸ່ມຂວາ = ປ່ຽນຮູບສະເໝີ
                    -->
                    <div class="relative shrink-0 self-start sm:self-center">
                        <button
                            type="button"
                            class="group relative block rounded-full focus:outline-hidden focus-visible:ring-4 focus-visible:ring-brand-500/30"
                            :aria-label="
                                user?.avatar_url
                                    ? t('profile.avatar.view')
                                    : t('profile.avatar.upload')
                            "
                            :disabled="avatarBusy"
                            @click="user?.avatar_url ? (lightboxOpen = true) : pickAvatar()"
                        >
                            <UserAvatar
                                :name="user?.name"
                                :url="user?.avatar_url"
                                class="h-24 w-24 text-2xl ring-4 ring-white dark:ring-gray-900"
                            />
                            <!-- ກຳລັງອັບ: ທັບດ້ວຍ spinner ກັນກົດຊ້ຳ -->
                            <span
                                v-if="avatarBusy"
                                class="absolute inset-0 flex items-center justify-center rounded-full bg-black/40"
                            >
                                <span
                                    class="h-7 w-7 animate-spin rounded-full border-2 border-white/40 border-t-white"
                                />
                            </span>
                            <span
                                v-else-if="user?.avatar_url"
                                class="absolute inset-0 flex items-center justify-center rounded-full bg-black/0 text-white opacity-0 transition group-hover:bg-black/40 group-hover:opacity-100"
                            >
                                <ZoomIcon class="h-6 w-6" />
                            </span>
                        </button>

                        <button
                            type="button"
                            class="absolute -right-1 -bottom-1 flex h-9 w-9 items-center justify-center rounded-full bg-brand-500 text-white shadow-theme-md ring-4 ring-white transition hover:bg-brand-600 focus:outline-hidden focus-visible:ring-brand-500/30 disabled:opacity-50 dark:ring-gray-900"
                            :title="t('profile.avatar.change')"
                            :aria-label="t('profile.avatar.change')"
                            :disabled="avatarBusy"
                            @click="pickAvatar"
                        >
                            <CameraIcon class="h-4.5 w-4.5" />
                        </button>

                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="hidden"
                            @change="onAvatarSelected"
                        />
                    </div>

                    <div class="min-w-0">
                        <h3
                            class="truncate text-lg font-semibold text-gray-800 dark:text-white/90"
                        >
                            {{ user?.name }}
                        </h3>
                        <p
                            class="truncate text-sm text-gray-500 dark:text-gray-400"
                        >
                            {{ user?.email }}
                        </p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <Badge
                                size="sm"
                                :color="user?.is_active ? 'success' : 'error'"
                            >
                                {{
                                    user?.is_active
                                        ? t('profile.active')
                                        : t('profile.inactive')
                                }}
                            </Badge>
                            <Badge
                                v-if="user?.is_platform_admin"
                                size="sm"
                                color="primary"
                            >
                                {{ t('profile.platformAdmin') }}
                            </Badge>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand-50 px-3 text-theme-sm font-medium text-brand-600 transition hover:bg-brand-100 disabled:opacity-50 dark:bg-brand-500/15 dark:text-brand-400 dark:hover:bg-brand-500/25"
                                :disabled="avatarBusy"
                                @click="pickAvatar"
                            >
                                <UploadIcon class="h-4 w-4" />
                                {{
                                    user?.avatar_url
                                        ? t('profile.avatar.change')
                                        : t('profile.avatar.upload')
                                }}
                            </button>
                            <button
                                v-if="user?.avatar_url"
                                type="button"
                                class="inline-flex h-9 items-center gap-1.5 rounded-lg px-3 text-theme-sm font-medium text-gray-500 transition hover:bg-error-50 hover:text-error-600 disabled:opacity-50 dark:text-gray-400 dark:hover:bg-error-500/10"
                                :disabled="avatarBusy"
                                @click="removeAvatar"
                            >
                                <TrashIcon class="h-4 w-4" />
                                {{ t('profile.avatar.remove') }}
                            </button>
                            <span
                                class="text-theme-xs text-gray-400 dark:text-gray-500"
                            >
                                {{ t('profile.avatar.hint') }}
                            </span>
                        </div>

                        <p
                            v-if="avatarError"
                            role="alert"
                            class="mt-2 text-theme-sm text-error-500"
                        >
                            {{ avatarError }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- ເບິ່ງຮູບເຕັມຈໍ — ຕົວດຽວກັບຮູບຫຼັກຖານ (vue-easy-lightbox) -->
            <VueEasyLightbox
                :visible="lightboxOpen"
                :imgs="user?.avatar_url ? [user.avatar_url] : []"
                :index="0"
                teleport="body"
                @hide="lightboxOpen = false"
            />

            <!-- ຂໍ້ມູນບັນຊີ -->
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 lg:p-6 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <h4
                    class="mb-5 text-base font-semibold text-gray-800 dark:text-white/90"
                >
                    {{ t('profile.accountInfo') }}
                </h4>

                <dl class="grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="row in infoRows" :key="row.label">
                        <dt
                            class="mb-1 text-theme-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ row.label }}
                        </dt>
                        <dd
                            class="text-sm font-medium text-gray-800 dark:text-white/90"
                        >
                            {{ row.value }}
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- ສິດຕໍ່ໂຄງການ — ຄຳຖາມທີ່ຜູ້ໃຊ້ຕອບເອງບໍ່ໄດ້ຈາກໜ້າອື່ນ -->
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 lg:p-6 dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <h4
                    class="text-base font-semibold text-gray-800 dark:text-white/90"
                >
                    {{ t('profile.projects') }}
                </h4>
                <p class="mt-1 mb-5 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('profile.projectsHint') }}
                </p>

                <p
                    v-if="loading"
                    class="py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                >
                    {{ t('common.loading') }}
                </p>

                <p
                    v-else-if="memberships.length === 0"
                    class="py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                >
                    {{ t('profile.noProjects') }}
                </p>

                <ul
                    v-else
                    class="divide-y divide-gray-100 dark:divide-gray-800"
                >
                    <li
                        v-for="m in memberships"
                        :key="m.project?.uuid"
                        class="flex flex-col gap-2 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <p
                                class="truncate text-sm font-medium text-gray-800 dark:text-white/90"
                            >
                                {{ m.project?.name }}
                                <span
                                    class="ms-1 font-mono text-theme-xs text-gray-400"
                                    >{{ m.project?.code }}</span
                                >
                            </p>
                            <p
                                class="mt-0.5 text-theme-xs text-gray-500 dark:text-gray-400"
                            >
                                {{ t(`settings.roleHint.${m.role}`) }}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-3">
                            <span
                                class="text-theme-xs text-gray-400 dark:text-gray-500"
                            >
                                {{
                                    t('profile.since', {
                                        date: formatDate(m.assigned_at),
                                    })
                                }}
                            </span>
                            <Badge size="sm" :color="roleColor(m.role)">
                                {{ t(`settings.role.${m.role}`) }}
                            </Badge>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import VueEasyLightbox from 'vue-easy-lightbox';
import { useI18n } from 'vue-i18n';
import { api, toApiError } from '@/api/client';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import UserAvatar from '@/components/common/UserAvatar.vue';
import AdminLayout from '@/components/layout/AdminLayout.vue';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import { useAuthStore } from '@/stores/auth';
import type { AppUser, ProjectRole } from '@/types/settings';
import { formatDate, formatDateTime } from '@/utils/date';
import { toastSuccess } from '@/utils/toast';
import CameraIcon from '~icons/tabler/camera';
import TrashIcon from '~icons/tabler/trash';
import UploadIcon from '~icons/tabler/upload';
import PageIcon from '~icons/tabler/user-circle';
import ZoomIcon from '~icons/tabler/zoom-in';

const { t } = useI18n();
const auth = useAuthStore();

/**
 * ເລີ່ມຈາກຜູ້ໃຊ້ໃນ store (ມີຢູ່ແລ້ວຕອນບູດ) ໃຫ້ໜ້າຂຶ້ນທັນທີ ແລ້ວຄ່ອຍທັບດ້ວຍ
 * `/profile` ທີ່ມີລາຍການສິດຕໍ່ໂຄງການມານຳ
 */
const profile = ref<AppUser | null>(null);
const loading = ref(true);
const loadError = ref<string | null>(null);

const user = computed(() => profile.value ?? auth.user);
const memberships = computed(() => profile.value?.memberships ?? []);

// ---------- ຮູບໂປຣໄຟລ໌ ----------

/** ຕ້ອງກົງກັບ StoreAvatarRequest — ກວດກ່ອນສົ່ງ ຈະບໍ່ຕ້ອງລໍຖ້າ 422 */
const AVATAR_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const AVATAR_MAX_BYTES = 8 * 1024 * 1024;

const fileInput = ref<HTMLInputElement | null>(null);
const avatarBusy = ref(false);
const avatarError = ref<string | null>(null);
const lightboxOpen = ref(false);

function pickAvatar(): void {
    avatarError.value = null;
    fileInput.value?.click();
}

/**
 * ເອົາຜົນຈາກ server ໄປທັບທັງໜ້ານີ້ ແລະ store — ຮູບຢູ່ header (UserMenu)
 * ອ່ານຈາກ auth.user ຈຶ່ງປ່ຽນພ້ອມກັນທັນທີ ໂດຍບໍ່ຕ້ອງໂຫຼດໜ້າຄືນ
 */
function applyProfile(updated: AppUser): void {
    profile.value = updated;

    if (auth.user) {
        auth.user.avatar_url = updated.avatar_url ?? null;
    }
}

async function onAvatarSelected(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    // ລ້າງຄ່າໄວ້ — ເລືອກໄຟລ໌ເດີມຊ້ຳ (ຫຼັງລຶບ) ຈະບໍ່ຍິງ change ຖ້າບໍ່ລ້າງ
    input.value = '';

    if (!file) {
        return;
    }

    if (!AVATAR_TYPES.includes(file.type)) {
        avatarError.value = t('profile.avatar.badType');

        return;
    }

    if (file.size > AVATAR_MAX_BYTES) {
        avatarError.value = t('profile.avatar.tooLarge');

        return;
    }

    const body = new FormData();
    body.append('avatar', file);

    avatarBusy.value = true;
    avatarError.value = null;

    try {
        const { data } = await api.post<{ data: AppUser }>(
            '/profile/avatar',
            body,
        );

        applyProfile(data.data);
        toastSuccess(t('profile.avatar.updated'));
    } catch (e) {
        const err = toApiError(e);
        avatarError.value = err.errors.avatar?.[0] ?? err.message;
    } finally {
        avatarBusy.value = false;
    }
}

/** ບໍ່ຖາມຢືນຢັນ: ລຶບຮູບຕົນເອງແລ້ວອັບຄືນໄດ້ທັນທີ ບໍ່ມີຫຍັງເສຍຫາຍ */
async function removeAvatar(): Promise<void> {
    avatarBusy.value = true;
    avatarError.value = null;

    try {
        const { data } = await api.delete<{ data: AppUser }>(
            '/profile/avatar',
        );

        applyProfile(data.data);
        toastSuccess(t('profile.avatar.removed'));
    } catch (e) {
        avatarError.value = toApiError(e).message;
    } finally {
        avatarBusy.value = false;
    }
}

/**
 * ວັນໝົດອາຍຸ: ໃຊ້ `access.expires_at` ທີ່ server ຄິດມາແລ້ວ (ອັນທີ່ມາຮອດກ່ອນ
 * ລະຫວ່າງອົງກອນ ແລະ ບັນຊີ) — ຫ້າມເລືອກເອງຈາກສອງ field ຢູ່ browser
 */
const expiryText = computed(() => {
    const access = user.value?.access;

    if (!access?.expires_at) {
        return t('profile.noExpiry');
    }

    const date = formatDate(access.expires_at);

    return access.expires_in_days === null
        ? date
        : `${date} (${t('settings.daysLeft', { days: access.expires_in_days })})`;
});

const infoRows = computed(() => {
    const u = user.value;

    return [
        { label: t('profile.name'), value: u?.name ?? '—' },
        { label: t('profile.email'), value: u?.email ?? '—' },
        {
            label: t('profile.organisation'),
            value: u?.company?.name ?? t('profile.noOrganisation'),
        },
        {
            label: t('profile.plan'),
            value: u?.access?.plan ? t(`settings.plan.${u.access.plan}`) : '—',
        },
        { label: t('profile.expiresAt'), value: expiryText.value },
        {
            label: t('profile.lastLogin'),
            value: formatDateTime(u?.last_login_at),
        },
        {
            label: t('profile.memberSince'),
            value: formatDate(u?.created_at),
        },
    ];
});

function roleColor(role: ProjectRole) {
    return (
        {
            full_access: 'primary',
            authorize: 'success',
            modify: 'warning',
            read_only: 'light',
        } as const
    )[role];
}

onMounted(async () => {
    try {
        const { data } = await api.get<{ data: AppUser }>('/profile');
        profile.value = data.data;
    } catch (e) {
        loadError.value = toApiError(e).message;
    } finally {
        loading.value = false;
    }
});
</script>
