<template>
    <aside
        :class="[
            'fixed top-0 left-0 z-99999 mt-16 flex h-screen flex-col border-r border-gray-200 bg-white px-5 text-gray-900 transition-[width,translate] duration-150 ease-out lg:mt-0 dark:border-gray-800 dark:bg-gray-900',
            {
                'lg:w-[290px]': isExpanded || isMobileOpen || isHovered,
                'lg:w-[90px]': !isExpanded && !isHovered,
                'w-[290px] translate-x-0': isMobileOpen,
                '-translate-x-full': !isMobileOpen,
                'lg:translate-x-0': true,
            },
        ]"
        @mouseenter="!isExpanded && (isHovered = true)"
        @mouseleave="isHovered = false"
    >
        <div
            :class="[
                'flex py-8',
                !isExpanded && !isHovered
                    ? 'lg:justify-center'
                    : 'justify-start',
            ]"
        >
            <router-link to="/" class="flex items-center gap-2">
                <AppLogo
                    :size="isExpanded || isHovered || isMobileOpen ? 40 : 32"
                />
                <span
                    v-if="isExpanded || isHovered || isMobileOpen"
                    class="text-xl font-bold text-gray-800 dark:text-white"
                >
                    {{ t('app.name') }}
                </span>
            </router-link>
        </div>
        <div
            class="no-scrollbar flex flex-col overflow-y-auto"
        >
            <nav class="mb-6">
                <div class="flex flex-col gap-4">
                    <div
                        v-for="(menuGroup, groupIndex) in menuGroups"
                        :key="groupIndex"
                    >
                        <h2
                            :class="[
                                'mb-4 flex text-xs leading-[20px] text-gray-400 uppercase',
                                !isExpanded && !isHovered
                                    ? 'lg:justify-center'
                                    : 'justify-start',
                            ]"
                        >
                            <template
                                v-if="isExpanded || isHovered || isMobileOpen"
                            >
                                {{ t(menuGroup.title) }}
                            </template>
                            <HorizontalDots v-else />
                        </h2>
                        <ul class="flex flex-col gap-4">
                            <li
                                v-for="(item, index) in menuGroup.items"
                                :key="item.name"
                            >
                                <button
                                    v-if="item.subItems"
                                    @click="toggleSubmenu(groupIndex, index)"
                                    :class="[
                                        'group menu-item w-full',
                                        {
                                            'menu-item-active': isSubmenuOpen(
                                                groupIndex,
                                                index,
                                            ),
                                            'menu-item-inactive':
                                                !isSubmenuOpen(
                                                    groupIndex,
                                                    index,
                                                ),
                                        },
                                        !isExpanded && !isHovered
                                            ? 'lg:justify-center'
                                            : 'lg:justify-start',
                                    ]"
                                >
                                    <!--
                                        ບັງຄັບຂະໜາດຢູ່ນີ້ ບໍ່ປ່ອຍໃຫ້ໄອຄອນກຳນົດເອງ:
                                        ໄອຄອນຈາກ `@/icons` ຝັງ width/height="24"
                                        ໄວ້ໃນ SVG ແຕ່ໄອຄອນຈາກ `~icons/*` ອອກມາເປັນ
                                        1em (= ຂະໜາດຕົວອັກສອນ ~14px) ຕາມ `scale: 1`
                                        ໃນ vite.config — ສອງແຫຼ່ງນີ້ຈຶ່ງໃຫຍ່ບໍ່ເທົ່າກັນ
                                        ຖ້າບໍ່ໃສ່ class ທັບ
                                    -->
                                    <span
                                        :class="[
                                            isSubmenuOpen(groupIndex, index)
                                                ? 'menu-item-icon-active'
                                                : 'menu-item-icon-inactive',
                                        ]"
                                    >
                                        <component
                                            :is="item.icon"
                                            class="h-6 w-6"
                                        />
                                    </span>
                                    <span
                                        v-if="
                                            isExpanded ||
                                            isHovered ||
                                            isMobileOpen
                                        "
                                        class="menu-item-text"
                                        >{{ t(item.name) }}</span
                                    >
                                    <ChevronDownIcon
                                        v-if="
                                            isExpanded ||
                                            isHovered ||
                                            isMobileOpen
                                        "
                                        :class="[
                                            'ml-auto h-5 w-5 transition-transform duration-200',
                                            {
                                                'rotate-180 text-brand-500':
                                                    isSubmenuOpen(
                                                        groupIndex,
                                                        index,
                                                    ),
                                            },
                                        ]"
                                    />
                                </button>
                                <router-link
                                    v-else-if="item.path"
                                    :to="item.path"
                                    :class="[
                                        'group menu-item',
                                        {
                                            'menu-item-active': isActive(
                                                item.path,
                                            ),
                                            'menu-item-inactive': !isActive(
                                                item.path,
                                            ),
                                        },
                                    ]"
                                >
                                    <!-- ຂະໜາດຄົງທີ່ ເຫດຜົນດຽວກັບຂ້າງເທິງ -->
                                    <span
                                        :class="[
                                            isActive(item.path)
                                                ? 'menu-item-icon-active'
                                                : 'menu-item-icon-inactive',
                                        ]"
                                    >
                                        <component
                                            :is="item.icon"
                                            class="h-6 w-6"
                                        />
                                    </span>
                                    <span
                                        v-if="
                                            isExpanded ||
                                            isHovered ||
                                            isMobileOpen
                                        "
                                        class="menu-item-text"
                                        >{{ t(item.name) }}</span
                                    >
                                </router-link>
                                <transition
                                    @enter="startTransition"
                                    @after-enter="endTransition"
                                    @before-leave="startTransition"
                                    @after-leave="endTransition"
                                >
                                    <div
                                        v-show="
                                            isSubmenuOpen(groupIndex, index) &&
                                            (isExpanded ||
                                                isHovered ||
                                                isMobileOpen)
                                        "
                                    >
                                        <ul class="mt-2 ml-9 space-y-1">
                                            <li
                                                v-for="subItem in item.subItems"
                                                :key="subItem.name"
                                            >
                                                <router-link
                                                    :to="subItem.path"
                                                    :class="[
                                                        'menu-dropdown-item',
                                                        {
                                                            'menu-dropdown-item-active':
                                                                isActive(
                                                                    subItem.path,
                                                                ),
                                                            'menu-dropdown-item-inactive':
                                                                !isActive(
                                                                    subItem.path,
                                                                ),
                                                        },
                                                    ]"
                                                >
                                                    {{ t(subItem.name) }}
                                                    <span
                                                        class="ml-auto flex items-center gap-1"
                                                    >
                                                        <span
                                                            v-if="subItem.new"
                                                            :class="[
                                                                'menu-dropdown-badge',
                                                                {
                                                                    'menu-dropdown-badge-active':
                                                                        isActive(
                                                                            subItem.path,
                                                                        ),
                                                                    'menu-dropdown-badge-inactive':
                                                                        !isActive(
                                                                            subItem.path,
                                                                        ),
                                                                },
                                                            ]"
                                                        >
                                                            new
                                                        </span>
                                                        <span
                                                            v-if="subItem.pro"
                                                            :class="[
                                                                'menu-dropdown-badge',
                                                                {
                                                                    'menu-dropdown-badge-active':
                                                                        isActive(
                                                                            subItem.path,
                                                                        ),
                                                                    'menu-dropdown-badge-inactive':
                                                                        !isActive(
                                                                            subItem.path,
                                                                        ),
                                                                },
                                                            ]"
                                                        >
                                                            pro
                                                        </span>
                                                    </span>
                                                </router-link>
                                            </li>
                                        </ul>
                                    </div>
                                </transition>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
        </div>
    </aside>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute } from 'vue-router';

import AppLogo from '@/components/common/AppLogo.vue';
import { useSidebar } from '@/composables/useSidebar';
import { useAuthStore } from '@/stores/auth';
import {
    GridIcon,
    ChevronDownIcon,
    HorizontalDots,
    PieChartIcon,
    SettingsIcon,
} from '../../icons';
import TransactionIcon from '~icons/tabler/arrows-exchange';
import LedgerIcon from '~icons/tabler/book-2';

const route = useRoute();
const { t } = useI18n();
const auth = useAuthStore();

const { isExpanded, isMobileOpen, isHovered, openSubmenu } = useSidebar();

// ເມນູຕາມໂຄງສ້າງລະບົບຈິງ (docs/architecture.md ພາກ 5.1).
// ລາຍການທີ່ຍັງບໍ່ທັນສ້າງໜ້າ ຖືກ comment ໄວ້ — ຫ້າມໃສ່ link ທີ່ກົດແລ້ວຫຼົງ 404
const menuGroups = computed(() => [
    {
        title: 'nav.menu',
        items: [
            {
                icon: GridIcon,
                name: 'nav.dashboard',
                path: '/',
            },
            {
                icon: TransactionIcon,
                name: 'nav.transactions',
                // ລາຍຮັບກ່ອນລາຍຈ່າຍ — ຕາມລຳດັບທີ່ເງິນໄຫຼ (ຮັບເຂົ້າ ແລ້ວຈຶ່ງຈ່າຍອອກ)
                subItems: [
                    { name: 'nav.revenues', path: '/transactions/revenues' },
                    { name: 'nav.expenses', path: '/transactions/expenses' },
                ],
            },
            {
                icon: LedgerIcon,
                name: 'nav.ledger',
                path: '/ledger',
            },
            {
                icon: PieChartIcon,
                name: 'nav.reports',
                // ລາຍຮັບກ່ອນລາຍຈ່າຍ ຕາມລຳດັບທີ່ເງິນໄຫຼ (ຄືກັນກັບເມນູ ທຸລະກຳ)
                // ແລ້ວຈຶ່ງເປັນລາຍງານລວມສອງຝັ່ງ
                subItems: [
                    { name: 'nav.reportRevenues', path: '/reports/revenues' },
                    { name: 'nav.reportExpenses', path: '/reports/expenses' },
                    { name: 'nav.reportLedger', path: '/reports/ledger' },
                    // ບັນທຶກການໃຊ້ງານ: ຜູ້ດູແລລະບົບເທົ່ານັ້ນ (router ເຕະຄົນອື່ນອອກ)
                    ...(auth.isPlatformAdmin
                        ? [
                              {
                                  name: 'nav.reportAuditLogs',
                                  path: '/reports/audit-logs',
                              },
                          ]
                        : []),
                ],
            },
            // {
            //   icon: ListIcon,
            //   name: "nav.expenses",
            //   subItems: [
            //     { name: "nav.expenseList", path: "/expenses", pro: false },
            //     { name: "nav.expenseCreate", path: "/expenses/create", pro: false },
            //   ],
            // },
            // {
            //   icon: TableIcon,
            //   name: "nav.budget",
            //   path: "/budget-items",
            // },
        ],
    },
    {
        title: 'nav.settings',
        items: [
            {
                icon: SettingsIcon,
                name: 'nav.masterData',
                // ຮຽງຕາມຂັ້ນຕອນການໃຊ້ງານຈິງ: ອົງກອນ → ໂຄງການ → ມອບສິດຜູ້ໃຊ້.
                // "ຜູ້ໃຊ້" ສະແດງສະເພາະຜູ້ດູແລລະບົບ ເພາະ router ຈະເຕະຄົນອື່ນອອກຢູ່ແລ້ວ
                // — ຫ້າມສະແດງ link ທີ່ກົດແລ້ວໄປ 404.
                subItems: [
                    { name: 'nav.companies', path: '/settings/companies' },
                    { name: 'nav.projects', path: '/settings/projects' },
                    // ໝວດ/ຜູ້ຈ່າຍ/ຜູ້ຮັບເງິນ ຢູ່ຫຼັງໂຄງການ: ຕັ້ງສາມອັນນີ້ແລ້ວຈຶ່ງ
                    // ບັນທຶກລາຍຈ່າຍໄດ້ຄົບທຸກຊ່ອງ.
                    // ທຸກຄົນເຫັນໄດ້ — ອ່ານໄດ້ໝົດ ສ່ວນປຸ່ມແກ້ໄຂຂຶ້ນກັບສິດ.
                    { name: 'nav.categories', path: '/settings/categories' },
                    { name: 'nav.payers', path: '/settings/payers' },
                    { name: 'nav.payees', path: '/settings/payees' },
                    // ທະນາຄານຢູ່ຫຼັງຜູ້ຮັບເງິນ: ເປັນຂໍ້ມູນປະກອບຂອງບັນຊີຜູ້ຮັບເງິນ
                    { name: 'nav.banks', path: '/settings/banks' },
                    // ສະກຸນເງິນກ່ອນອັດຕາແລກປ່ຽນ — ຕ້ອງມີສະກຸນຈຶ່ງຕັ້ງອັດຕາໃຫ້ມັນໄດ້.
                    // ອັດຕາແລກປ່ຽນຄືໜ້າທີ່ຖືກເປີດຖີ່ທີ່ສຸດໃນກຸ່ມນີ້ (ຕັ້ງທຸກມື້)
                    { name: 'nav.currencies', path: '/settings/currencies' },
                    {
                        name: 'nav.exchangeRates',
                        path: '/settings/exchange-rates',
                    },
                    // ສອງລາຍການສຸດທ້າຍເປັນຂອງຜູ້ດູແລລະບົບເທົ່ານັ້ນ —
                    // router ຈະເຕະຄົນອື່ນອອກຢູ່ແລ້ວ ຈຶ່ງຫ້າມສະແດງ link
                    // ທີ່ກົດແລ້ວໄປ 404
                    ...(auth.isPlatformAdmin
                        ? [
                              {
                                  name: 'nav.credentials',
                                  path: '/settings/credentials',
                              },
                              { name: 'nav.users', path: '/settings/users' },
                          ]
                        : []),
                ],
            },
            // {
            //   icon: BoxCubeIcon,
            //   name: "nav.referenceData",
            //   subItems: [
            //     { name: "nav.categories", path: "/categories", pro: false },
            //     { name: "nav.payers", path: "/payers", pro: false },
            //     { name: "nav.payees", path: "/payees", pro: false },
            //     { name: "nav.exchangeRates", path: "/exchange-rates", pro: false },
            //   ],
            // },
        ],
    },
    {
        title: 'nav.others',
        items: [
            // ລາຍງານທີ່ API ມີແລ້ວແຕ່ຍັງບໍ່ທັນມີໜ້າ — ເມື່ອສ້າງແລ້ວໃຫ້ໃສ່ເປັນ
            // subItems ຂອງ nav.reports ຂ້າງເທິງ ບໍ່ແມ່ນກຸ່ມໃໝ່:
            //   /reports/by-category      → ReportController@byCategory
            //   /reports/budget-vs-actual → ReportController@budgetVsActual
            //   /reports/missing-evidence → ReportController@missingEvidence
        ],
    },
]);

const isActive = (path) => route.path === path;

const toggleSubmenu = (groupIndex, itemIndex) => {
    const key = `${groupIndex}-${itemIndex}`;
    openSubmenu.value = openSubmenu.value === key ? null : key;
};

const isAnySubmenuRouteActive = computed(() => {
    return menuGroups.value.some((group) =>
        group.items.some(
            (item) =>
                item.subItems &&
                item.subItems.some((subItem) => isActive(subItem.path)),
        ),
    );
});

const isSubmenuOpen = (groupIndex, itemIndex) => {
    const key = `${groupIndex}-${itemIndex}`;

    return (
        openSubmenu.value === key ||
        (isAnySubmenuRouteActive.value &&
            menuGroups.value[groupIndex].items[itemIndex].subItems?.some(
                (subItem) => isActive(subItem.path),
            ))
    );
};

const startTransition = (el) => {
    el.style.height = 'auto';
    const height = el.scrollHeight;
    el.style.height = '0px';
    el.offsetHeight; // force reflow
    el.style.height = height + 'px';
};

const endTransition = (el) => {
    el.style.height = '';
};
</script>
