import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const router = createRouter({
    history: createWebHistory(),
    scrollBehavior(_to, _from, savedPosition) {
        return savedPosition || { left: 0, top: 0 };
    },
    routes: [
        {
            path: '/login',
            name: 'login',
            component: () => import('@/views/Auth/Login.vue'),
            meta: { title: 'ເຂົ້າສູ່ລະບົບ', guestOnly: true },
        },
        {
            path: '/',
            name: 'dashboard',
            component: () => import('@/views/Dashboard.vue'),
            meta: { title: 'ພາບລວມ', requiresAuth: true },
        },

        // ---------- ທຸລະກຳ ----------
        {
            path: '/transactions/revenues',
            name: 'transactions.revenues',
            component: () => import('@/views/Transactions/Revenues.vue'),
            meta: { title: 'ລາຍຮັບ', requiresAuth: true },
        },
        {
            path: '/transactions/expenses',
            name: 'transactions.expenses',
            component: () => import('@/views/Transactions/Expenses.vue'),
            meta: { title: 'ລາຍຈ່າຍ', requiresAuth: true },
        },
        {
            path: '/ledger',
            name: 'ledger',
            component: () => import('@/views/Ledger.vue'),
            meta: { title: 'ບັນຊີແຍກປະເພດ', requiresAuth: true },
        },

        // ປາຍທາງຂອງກະດິ່ງແຈ້ງເຕືອນ — ຄິວລໍຖ້າອະນຸມັດຂ້າມທຸກໂຄງການ.
        // ບໍ່ຈຳກັດສິດຢູ່ router: ຄົນທີ່ອະນຸມັດຫຍັງບໍ່ໄດ້ຈະເຫັນຄິວຫວ່າງ
        // (server ຕອບລາຍການຫວ່າງ ບໍ່ແມ່ນ 403 — ເບິ່ງ ApprovalController)
        {
            path: '/approvals',
            name: 'approvals',
            component: () => import('@/views/Approvals.vue'),
            meta: { title: 'ລໍຖ້າອະນຸມັດ', requiresAuth: true },
        },

        // ເຂົ້າຈາກເມນູຜູ້ໃຊ້ (ມຸມຂວາເທິງ) ເທົ່ານັ້ນ — ບໍ່ມີໃນ sidebar
        {
            path: '/profile',
            name: 'profile',
            component: () => import('@/views/Profile.vue'),
            meta: { title: 'ໂປຣໄຟລ໌ຂອງຂ້ອຍ', requiresAuth: true },
        },

        // ---------- ລາຍງານ ----------
        // ບໍ່ຈຳກັດສິດຢູ່ router: ລາຍງານໃຊ້ ExpensePolicy::viewAny ຢູ່ຝັ່ງ server
        // ແລະ ໜ້ານີ້ສະແດງ "ທ່ານບໍ່ແມ່ນສະມາຊິກ" ໃຫ້ເອງເມື່ອບໍ່ມີສິດ
        {
            path: '/reports/revenues',
            name: 'reports.revenues',
            component: () => import('@/views/Reports/Revenues.vue'),
            meta: { title: 'ລາຍງານລາຍຮັບ', requiresAuth: true },
        },
        {
            path: '/reports/expenses',
            name: 'reports.expenses',
            component: () => import('@/views/Reports/Expenses.vue'),
            meta: { title: 'ລາຍງານລາຍຈ່າຍ', requiresAuth: true },
        },
        {
            path: '/reports/ledger',
            name: 'reports.ledger',
            component: () => import('@/views/Reports/Ledger.vue'),
            meta: { title: 'ລາຍງານລາຍຮັບ-ລາຍຈ່າຍ', requiresAuth: true },
        },
        {
            path: '/reports/audit-logs',
            name: 'reports.auditLogs',
            component: () => import('@/views/Reports/AuditLogs.vue'),
            // ActivityLogPolicy: ຜູ້ດູແລລະບົບເທົ່ານັ້ນ — log ຂ້າມທຸກອົງກອນ
            // (IP, login ລົ້ມເຫຼວ, ຄຳຄົ້ນຂອງຄົນອື່ນ). ເຕະອອກຕັ້ງແຕ່ router
            meta: {
                title: 'ບັນທຶກການໃຊ້ງານ',
                requiresAuth: true,
                requiresPlatformAdmin: true,
            },
        },

        // ---------- ຂໍ້ມູນຫຼັກ / ຕັ້ງຄ່າ ----------
        // ຂັ້ນຕອນ: ອົງກອນ → ໂຄງການ → ມອບສິດຜູ້ໃຊ້
        {
            path: '/settings/companies',
            name: 'settings.companies',
            component: () => import('@/views/Settings/Companies.vue'),
            meta: { title: 'ອົງກອນ', requiresAuth: true },
        },
        {
            path: '/settings/projects',
            name: 'settings.projects',
            component: () => import('@/views/Settings/Projects.vue'),
            meta: { title: 'ໂຄງການ', requiresAuth: true },
        },
        {
            path: '/settings/categories',
            name: 'settings.categories',
            component: () => import('@/views/Settings/Categories.vue'),
            // ບໍ່ຈຳກັດສິດຢູ່ router: ທຸກຄົນເບິ່ງໝວດໄດ້ (MasterDataPolicy::viewAny)
            // ສ່ວນປຸ່ມເພີ່ມ/ແກ້/ລຶບ ຫາຍໄປເອງເມື່ອບໍ່ມີສິດຂຽນ
            meta: { title: 'ໝວດລາຍຈ່າຍ', requiresAuth: true },
        },
        {
            path: '/settings/payers',
            name: 'settings.payers',
            component: () => import('@/views/Settings/Payers.vue'),
            meta: { title: 'ຜູ້ຈ່າຍ', requiresAuth: true },
        },
        {
            path: '/settings/payees',
            name: 'settings.payees',
            component: () => import('@/views/Settings/Payees.vue'),
            meta: { title: 'ຜູ້ຮັບເງິນ', requiresAuth: true },
        },
        {
            path: '/settings/banks',
            name: 'settings.banks',
            component: () => import('@/views/Settings/Banks.vue'),
            meta: { title: 'ທະນາຄານ', requiresAuth: true },
        },
        {
            path: '/settings/currencies',
            name: 'settings.currencies',
            component: () => import('@/views/Settings/Currencies.vue'),
            meta: { title: 'ສະກຸນເງິນ', requiresAuth: true },
        },
        {
            path: '/settings/exchange-rates',
            name: 'settings.exchangeRates',
            component: () => import('@/views/Settings/ExchangeRates.vue'),
            // ໜ້ານີ້ຖືກເປີດທຸກມື້ — ທຸກຄົນເບິ່ງອັດຕາໄດ້ ສ່ວນປຸ່ມຕັ້ງ/ແກ້
            // ຫາຍໄປເອງເມື່ອບໍ່ມີສິດຂຽນ (MasterDataPolicy)
            meta: { title: 'ອັດຕາແລກປ່ຽນ', requiresAuth: true },
        },
        {
            path: '/settings/credentials',
            name: 'settings.credentials',
            component: () => import('@/views/Settings/Credentials.vue'),
            // ບັນຊີເຂົ້າໃຊ້ລະບົບ — ຄືກັບ "ຜູ້ໃຊ້": ບໍ່ມີສະຖານະ "ເຫັນແຕ່
            // ແກ້ບໍ່ໄດ້" ເລີຍ (CredentialPolicy ບໍ່ໃຫ້ຄົນອື່ນອ່ານແມ່ນແຕ່
            // ລາຍຊື່) ຈຶ່ງເຕະອອກຕັ້ງແຕ່ router ແທນທີ່ຈະປ່ອຍໃຫ້ເຫັນໜ້າ 403
            meta: {
                title: 'ບັນຊີເຂົ້າໃຊ້ລະບົບ',
                requiresAuth: true,
                requiresPlatformAdmin: true,
            },
        },
        {
            path: '/settings/users',
            name: 'settings.users',
            component: () => import('@/views/Settings/Users.vue'),
            // ມີແຕ່ຜູ້ດູແລລະບົບທີ່ຈັດການບັນຊີໄດ້ — ຄົນອື່ນເປີດແລ້ວຈະເຫັນ
            // ແຕ່ຕາຕະລາງອ່ານຢ່າງດຽວ ຈຶ່ງເຕະອອກຕັ້ງແຕ່ router ເລີຍ
            meta: {
                title: 'ຜູ້ໃຊ້',
                requiresAuth: true,
                requiresPlatformAdmin: true,
            },
        },

        {
            path: '/access-expired',
            name: 'access-expired',
            component: () => import('@/views/Errors/AccessExpired.vue'),
            meta: { title: 'ໝົດອາຍຸການໃຊ້ງານ', requiresAuth: true },
        },
        {
            path: '/:pathMatch(.*)*',
            name: 'not-found',
            component: () => import('@/views/Errors/FourZeroFour.vue'),
            meta: { title: 'ບໍ່ພົບໜ້ານີ້' },
        },
    ],
});

/**
 * Guard: ລໍຖ້າໃຫ້ຮູ້ສະຖານະ login ກ່ອນຕັດສິນ.
 *
 * ຕອນ reload ໜ້າ store ຍັງຫວ່າງເປົ່າແຕ່ session cookie ອາດຍັງໃຊ້ໄດ້ຢູ່ —
 * ຖ້າ redirect ໄປ /login ທັນທີ ຜູ້ໃຊ້ທີ່ login ຢູ່ຈະຖືກເຕະອອກທຸກຄັ້ງທີ່ F5.
 */
router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (!auth.resolved) {
        await auth.fetchUser();
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        // ຈື່ໜ້າທີ່ຜູ້ໃຊ້ຢາກໄປ ເພື່ອສົ່ງກັບຫຼັງ login ສຳເລັດ
        return {
            name: 'login',
            query: to.fullPath === '/' ? {} : { redirect: to.fullPath },
        };
    }

    if (to.meta.guestOnly && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }

    // ອົງກອນໝົດອາຍຸ/ຖືກລະງັບ → ທຸກໜ້າພາໄປໜ້າອະທິບາຍ ແທນທີ່ຈະປ່ອຍໃຫ້ເຫັນ
    // ໜ້າຫວ່າງເປົ່າທີ່ທຸກ request ຕອບ 403. API ກໍບລັອກຢູ່ຝັ່ງ server ຄືກັນ —
    // ອັນນີ້ພຽງແຕ່ເຮັດໃຫ້ຜູ້ໃຊ້ຮູ້ວ່າເກີດຫຍັງຂຶ້ນ.
    if (
        auth.isAuthenticated &&
        !auth.hasActiveAccess &&
        to.name !== 'access-expired'
    ) {
        return { name: 'access-expired' };
    }

    if (
        auth.isAuthenticated &&
        auth.hasActiveAccess &&
        to.name === 'access-expired'
    ) {
        return { name: 'dashboard' };
    }

    if (to.meta.requiresPlatformAdmin && !auth.isPlatformAdmin) {
        return { name: 'not-found' };
    }

    return true;
});

router.afterEach((to) => {
    const appName =
        document
            .querySelector('meta[name="app-name"]')
            ?.getAttribute('content') ?? 'Budget System';
    document.title = to.meta.title
        ? `${to.meta.title as string} | ${appName}`
        : appName;
});

export default router;
