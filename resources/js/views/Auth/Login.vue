<template>
    <FullScreenLayout>
        <div class="relative z-1 bg-white p-6 sm:p-0 dark:bg-gray-900">
            <div
                class="relative flex h-screen w-full flex-col justify-center lg:flex-row dark:bg-gray-900"
            >
                <!-- ເລືອກພາສາ — ວາງເທິງສຸດເພື່ອໃຫ້ເຫັນກ່ອນອ່ານຟອມ -->
                <div class="absolute top-4 right-4 z-10 sm:top-6 sm:right-6">
                    <LocaleToggle />
                </div>

                <!-- ຊ້າຍ: ຟອມ -->
                <div class="flex w-full flex-1 flex-col lg:w-1/2">
                    <div
                        class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center"
                    >
                        <div class="mb-5 sm:mb-8">
                            <h1
                                class="mb-2 text-title-sm font-semibold text-gray-800 sm:text-title-md dark:text-white/90"
                            >
                                {{ t('auth.signInTitle') }}
                            </h1>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ t('auth.signInSubtitle') }}
                            </p>
                        </div>

                        <form novalidate @submit.prevent="submit">
                            <div class="space-y-5">
                                <!-- ຂໍ້ຄວາມ error ຂຶ້ນເປັນກ່ອງ SweetAlert2 (ເບິ່ງ submit()) —
                                     ຢູ່ຟອມເຫຼືອແຕ່ຂອບແດງບອກວ່າ field ໃດຜິດ -->

                                <!-- ອີເມວ -->
                                <div>
                                    <label
                                        for="email"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                                    >
                                        {{ t('auth.email')
                                        }}<span class="text-error-500">*</span>
                                    </label>
                                    <input
                                        id="email"
                                        v-model.trim="email"
                                        type="email"
                                        name="email"
                                        autocomplete="username"
                                        required
                                        :disabled="auth.loading"
                                        :aria-invalid="
                                            emailError ? 'true' : undefined
                                        "
                                        :placeholder="t('auth.enterEmail')"
                                        :class="inputClass(emailError)"
                                    />
                                </div>

                                <!-- ລະຫັດຜ່ານ -->
                                <div>
                                    <label
                                        for="password"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                                    >
                                        {{ t('auth.password')
                                        }}<span class="text-error-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input
                                            id="password"
                                            v-model="password"
                                            :type="
                                                showPassword
                                                    ? 'text'
                                                    : 'password'
                                            "
                                            name="password"
                                            autocomplete="current-password"
                                            required
                                            :disabled="auth.loading"
                                            :aria-invalid="
                                                passwordError
                                                    ? 'true'
                                                    : undefined
                                            "
                                            :placeholder="
                                                t('auth.enterPassword')
                                            "
                                            :class="[
                                                inputClass(passwordError),
                                                'pr-11',
                                            ]"
                                        />
                                        <button
                                            type="button"
                                            class="absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-500 dark:text-gray-400"
                                            :aria-label="
                                                showPassword
                                                    ? t('auth.hidePassword')
                                                    : t('auth.showPassword')
                                            "
                                            @click="
                                                showPassword = !showPassword
                                            "
                                        >
                                            <svg
                                                v-if="!showPassword"
                                                class="fill-current"
                                                width="20"
                                                height="20"
                                                viewBox="0 0 20 20"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    clip-rule="evenodd"
                                                    d="M10.0002 13.8619C7.23361 13.8619 4.86803 12.1372 3.92328 9.70241C4.86804 7.26761 7.23361 5.54297 10.0002 5.54297C12.7667 5.54297 15.1323 7.26762 16.0771 9.70243C15.1323 12.1372 12.7667 13.8619 10.0002 13.8619ZM10.0002 4.04297C6.48191 4.04297 3.49489 6.30917 2.4155 9.4593C2.3615 9.61687 2.3615 9.78794 2.41549 9.94552C3.49488 13.0957 6.48191 15.3619 10.0002 15.3619C13.5184 15.3619 16.5055 13.0957 17.5849 9.94555C17.6389 9.78797 17.6389 9.6169 17.5849 9.45932C16.5055 6.30919 13.5184 4.04297 10.0002 4.04297ZM9.99151 7.84413C8.96527 7.84413 8.13333 8.67606 8.13333 9.70231C8.13333 10.7286 8.96527 11.5605 9.99151 11.5605H10.0064C11.0326 11.5605 11.8646 10.7286 11.8646 9.70231C11.8646 8.67606 11.0326 7.84413 10.0064 7.84413H9.99151Z"
                                                    fill="#98A2B3"
                                                />
                                            </svg>
                                            <svg
                                                v-else
                                                class="fill-current"
                                                width="20"
                                                height="20"
                                                viewBox="0 0 20 20"
                                                aria-hidden="true"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    clip-rule="evenodd"
                                                    d="M4.63803 3.57709C4.34513 3.2842 3.87026 3.2842 3.57737 3.57709C3.28447 3.86999 3.28447 4.34486 3.57737 4.63775L4.85323 5.91362C3.74609 6.84199 2.89363 8.06395 2.4155 9.45936C2.3615 9.61694 2.3615 9.78801 2.41549 9.94558C3.49488 13.0957 6.48191 15.3619 10.0002 15.3619C11.255 15.3619 12.4422 15.0737 13.4994 14.5598L15.3625 16.4229C15.6554 16.7158 16.1302 16.7158 16.4231 16.4229C16.716 16.13 16.716 15.6551 16.4231 15.3622L4.63803 3.57709ZM12.3608 13.4212L10.4475 11.5079C10.3061 11.5423 10.1584 11.5606 10.0064 11.5606H9.99151C8.96527 11.5606 8.13333 10.7286 8.13333 9.70237C8.13333 9.5461 8.15262 9.39434 8.18895 9.24933L5.91885 6.97923C5.03505 7.69015 4.34057 8.62704 3.92328 9.70247C4.86803 12.1373 7.23361 13.8619 10.0002 13.8619C10.8326 13.8619 11.6287 13.7058 12.3608 13.4212ZM16.0771 9.70249C15.7843 10.4569 15.3552 11.1432 14.8199 11.7311L15.8813 12.7925C16.6329 11.9813 17.2187 11.0143 17.5849 9.94561C17.6389 9.78803 17.6389 9.61696 17.5849 9.45938C16.5055 6.30925 13.5184 4.04303 10.0002 4.04303C9.13525 4.04303 8.30244 4.17999 7.52218 4.43338L8.75139 5.66259C9.1556 5.58413 9.57311 5.54303 10.0002 5.54303C12.7667 5.54303 15.1323 7.26768 16.0771 9.70249Z"
                                                    fill="#98A2B3"
                                                />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- ຈື່ຂ້ອຍໄວ້ -->
                                <label
                                    for="remember"
                                    class="flex cursor-pointer items-center text-sm font-normal text-gray-700 select-none dark:text-gray-400"
                                >
                                    <div class="relative">
                                        <input
                                            id="remember"
                                            v-model="remember"
                                            type="checkbox"
                                            class="sr-only"
                                        />
                                        <div
                                            class="mr-3 flex h-5 w-5 items-center justify-center rounded-md border-[1.25px]"
                                            :class="
                                                remember
                                                    ? 'border-brand-500 bg-brand-500'
                                                    : 'border-gray-300 bg-transparent dark:border-gray-700'
                                            "
                                        >
                                            <span
                                                :class="
                                                    remember ? '' : 'opacity-0'
                                                "
                                            >
                                                <svg
                                                    width="14"
                                                    height="14"
                                                    viewBox="0 0 14 14"
                                                    fill="none"
                                                    aria-hidden="true"
                                                >
                                                    <path
                                                        d="M11.6666 3.5L5.24992 9.91667L2.33325 7"
                                                        stroke="white"
                                                        stroke-width="1.94437"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    />
                                                </svg>
                                            </span>
                                        </div>
                                    </div>
                                    {{ t('auth.keepLoggedIn') }}
                                </label>

                                <button
                                    type="submit"
                                    :disabled="auth.loading"
                                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-brand-500 px-4 py-3 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    <Spinner v-if="auth.loading" size="sm" />
                                    {{
                                        auth.loading
                                            ? t('auth.signingIn')
                                            : t('auth.signIn')
                                    }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ຂວາ: brand -->
                <div
                    class="relative hidden h-full w-full items-center bg-brand-950 lg:grid lg:w-1/2 dark:bg-white/5"
                >
                    <div class="z-1 flex items-center justify-center">
                        <CommonGridShape />
                        <div class="flex max-w-xs flex-col items-center">
                            <!--
                ໃຊ້ AppLogo ອັນດຽວກັບໃນແອັບ — brand ດຽວກັນທຸກບ່ອນ.
                ເດີມເປັນ sss-logo.png (2.5 MB) ທີ່ຖືກລຶບອອກແລ້ວ.
              -->
                            <AppLogo :size="80" class="mb-5" />
                            <span
                                class="text-center text-2xl font-bold text-white"
                                >{{ t('app.name') }}</span
                            >
                            <span
                                class="mb-2 text-center text-sm font-medium text-brand-300"
                                >{{ t('app.subtitle') }}</span
                            >
                            <p
                                class="text-center text-gray-400 dark:text-white/60"
                            >
                                {{ t('app.tagline') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </FullScreenLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { firstError } from '@/api/client';
import AppLogo from '@/components/common/AppLogo.vue';
import CommonGridShape from '@/components/common/CommonGridShape.vue';
import LocaleToggle from '@/components/common/LocaleToggle.vue';
import FullScreenLayout from '@/components/layout/FullScreenLayout.vue';
import Spinner from '@/components/ui/Spinner.vue';
import { useAuthStore } from '@/stores/auth';
import { alertDialog } from '@/utils/alert';
import { toastSuccess } from '@/utils/toast';

const { t } = useI18n();
const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

/**
 * ຈື່ອີເມວໄວ້ໃນ browser ເມື່ອຕິ໊ກ "ຈື່ຂ້ອຍໄວ້" — ກັບມາເທື່ອໜ້າພິມແຕ່ລະຫັດຜ່ານ.
 * ເກັບໄດ້ແຕ່ອີເມວ ຫ້າມເກັບລະຫັດຜ່ານ ຫຼື token (docs/architecture.md ພາກ 9).
 */
const REMEMBERED_EMAIL_KEY = 'login.remembered-email';

function readRememberedEmail(): string | null {
    try {
        return localStorage.getItem(REMEMBERED_EMAIL_KEY);
    } catch {
        // private mode — ເລີ່ມດ້ວຍຟອມຫວ່າງ
        return null;
    }
}

function writeRememberedEmail(value: string | null): void {
    try {
        if (value === null) {
            localStorage.removeItem(REMEMBERED_EMAIL_KEY);
        } else {
            localStorage.setItem(REMEMBERED_EMAIL_KEY, value);
        }
    } catch {
        // ຈື່ບໍ່ໄດ້ກໍບໍ່ເປັນຫຍັງ — login ສຳເລັດຢູ່ດີ
    }
}

const rememberedEmail = readRememberedEmail();

const email = ref(rememberedEmail ?? '');
const password = ref('');
// ເຄີຍຕິ໊ກໄວ້ = ຍັງຢາກໃຫ້ຈື່ — ຕິ໊ກໃຫ້ຄືນເພື່ອບໍ່ໃຫ້ລືມແບບງຽບໆ
const remember = ref(rememberedEmail !== null);
const showPassword = ref(false);

const emailError = computed(() =>
    auth.error ? firstError(auth.error.errors, 'email') : null,
);
const passwordError = computed(() =>
    auth.error ? firstError(auth.error.errors, 'password') : null,
);

/**
 * ແຈ້ງ login ບໍ່ຜ່ານເປັນກ່ອງ — ລະຫັດຜິດ (422), ລອງຫຼາຍເທື່ອເກີນ (429), server ລົ້ມ.
 * 422 ລວມທຸກ field ໄວ້ກ່ອງດຽວ; ອັນອື່ນບໍ່ມີ errors ຈຶ່ງໃຊ້ message ແທນ.
 */
async function showLoginError(): Promise<void> {
    if (!auth.error) {
        return;
    }

    const messages = [...new Set(Object.values(auth.error.errors).flat())];

    await alertDialog(
        'error',
        t('auth.signInFailed'),
        messages.length > 0 ? messages.join('\n') : auth.error.message,
    );
}

/**
 * ລະຫັດຖືກ ແຕ່ບັນຊີ/ອົງກອນໃຊ້ບໍ່ໄດ້ (ປິດ, ໝົດອາຍຸ, ຖືກລະງັບ) — ບອກເຫດແລ້ວ
 * ອອກຈາກລະບົບທັນທີ ໃຫ້ຢູ່ໜ້າ login ຕໍ່ ແທນທີ່ຈະຖືກພາໄປໜ້າ access-expired.
 * API ກໍບລັອກຢູ່ຝັ່ງ server ຢູ່ແລ້ວ (EnsureAccessIsActive) — ອັນນີ້ພຽງແຕ່ບອກໃຫ້ຮູ້.
 */
async function rejectInactiveAccess(): Promise<void> {
    const user = auth.user;
    const company = user?.company;

    let title = t('accessExpired.title');
    let body = company
        ? t('accessExpired.body', { company: company.name })
        : '';

    if (user?.is_active === false) {
        title = t('auth.accountDisabled');
        body = '';
    } else if (company?.plan === 'suspended') {
        title = t('accessExpired.suspended');
    }

    await auth.logout();
    password.value = '';

    await alertDialog(
        'warning',
        title,
        [body, t('accessExpired.contact')].filter(Boolean).join('\n'),
    );
}

function inputClass(error: string | null): string {
    return [
        'h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs',
        'placeholder:text-gray-400 focus:outline-hidden focus:ring-3',
        'disabled:cursor-not-allowed disabled:bg-gray-50 dark:disabled:bg-white/5',
        'dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30',
        error
            ? 'border-error-500 focus:border-error-500 focus:ring-error-500/10'
            : 'border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800',
    ].join(' ');
}

async function submit(): Promise<void> {
    if (!(await auth.login(email.value, password.value, remember.value))) {
        await showLoginError();

        return;
    }

    // ຂຽນຫຼັງສຳເລັດເທົ່ານັ້ນ — ບໍ່ຈື່ອີເມວທີ່ພິມຜິດ
    writeRememberedEmail(remember.value ? email.value : null);

    if (!auth.hasActiveAccess) {
        await rejectInactiveAccess();

        return;
    }

    password.value = '';

    const redirect = route.query.redirect;
    await router.replace(
        typeof redirect === 'string' ? redirect : { name: 'dashboard' },
    );

    // ແຈ້ງຫຼັງປ່ຽນໜ້າແລ້ວ — toast ຈະໂຜ່ຢູ່ໜ້າໃໝ່ ບໍ່ແມ່ນກະພິບຢູ່ໜ້າ login
    toastSuccess(
        t('auth.signedIn'),
        t('auth.welcomeBack', { name: auth.user?.name ?? '' }),
    );
}
</script>
