<template>
    <AdminLayout>
        <PageBreadcrumb :page-title="t('audit.title')" :icon="PageIcon" />

        <!-- ຕົວກັ່ນ — ທຸກການປ່ຽນແປງໂຫຼດໃໝ່ທັນທີ (ຄຳຄົ້ນລໍຖ້າຢຸດພິມກ່ອນ) -->
        <div
            class="mb-6 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="grid gap-x-5 gap-y-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"
            >
                <DatePicker
                    v-model="filters.from"
                    label="audit.from"
                    name="from"
                    max-today
                />
                <DatePicker
                    v-model="filters.to"
                    label="audit.to"
                    name="to"
                    max-today
                />
                <SelectField
                    v-model="filters.event"
                    label="audit.event"
                    name="event"
                    :options="eventOptions"
                    nullable
                    null-label="audit.allEvents"
                />
                <SelectField
                    v-model="filters.subject"
                    label="audit.subject"
                    name="subject"
                    :options="subjectOptions"
                    nullable
                    null-label="audit.allSubjects"
                />
                <SelectField
                    v-model="filters.causer_id"
                    label="audit.user"
                    name="causer_id"
                    :options="userOptions"
                    nullable
                    null-label="audit.allUsers"
                />
                <TextField
                    v-model="search"
                    label="audit.search"
                    name="search"
                    placeholder="audit.searchHint"
                />
            </div>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="border-b border-gray-200 px-5 py-4 dark:border-gray-800"
            >
                <h3
                    class="text-base font-medium text-gray-800 dark:text-white/90"
                >
                    {{ t('audit.heading') }}
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ t('audit.description') }}
                </p>
            </div>

            <!-- ເກີນເພດານ = ຜູ້ໃຊ້ບໍ່ໄດ້ເຫັນຄົບ — ຕ້ອງບອກ ບໍ່ດັ່ງນັ້ນຈະເຂົ້າໃຈວ່າຄົບແລ້ວ -->
            <Alert
                v-if="truncated"
                variant="warning"
                :title="t('audit.truncatedTitle')"
                :message="t('audit.truncated', { cap })"
                class="m-5"
            />

            <DataGrid
                :column-defs="columnDefs"
                :row-data="rows"
                :loading="loading"
                :error="loadError"
                :get-row-id="getRowId"
                :row-height="60"
                pagination
                :page-size="50"
                :empty-label="t('audit.empty')"
            />
        </div>

        <!-- ລາຍລະອຽດ 1 ເຫດການ: ໃຜ, ເມື່ອໃດ, ຈາກໃສ, ປ່ຽນຫຍັງ -->
        <Modal v-if="selected" full-screen-backdrop @close="selected = null">
            <template #body>
                <div
                    class="relative z-10 m-4 max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-3xl bg-white p-6 lg:p-8 dark:bg-gray-900"
                >
                    <div class="mb-5 flex items-start justify-between gap-4">
                        <div>
                            <Badge
                                size="sm"
                                :color="eventColor(selected.event)"
                            >
                                {{ eventLabel(selected.event) }}
                            </Badge>
                            <h4
                                class="mt-2 text-lg font-semibold text-gray-800 dark:text-white/90"
                            >
                                {{ subjectText(selected) }}
                            </h4>
                        </div>
                        <button
                            type="button"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/5"
                            :aria-label="t('common.close')"
                            @click="selected = null"
                        >
                            <CloseIcon class="h-5 w-5" />
                        </button>
                    </div>

                    <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-theme-xs text-gray-500">
                                {{ t('audit.time') }}
                            </dt>
                            <dd class="text-gray-800 dark:text-white/90">
                                {{ formatDateTime(selected.created_at) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-theme-xs text-gray-500">
                                {{ t('audit.user') }}
                            </dt>
                            <dd class="text-gray-800 dark:text-white/90">
                                {{ causerText(selected) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-theme-xs text-gray-500">
                                {{ t('audit.ip') }}
                            </dt>
                            <dd
                                class="font-mono text-gray-800 dark:text-white/90"
                            >
                                {{ selected.ip_address ?? '—' }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-theme-xs text-gray-500">
                                {{ t('audit.userAgent') }}
                            </dt>
                            <dd
                                class="text-theme-xs break-all text-gray-600 dark:text-gray-400"
                            >
                                {{ selected.user_agent ?? '—' }}
                            </dd>
                        </div>
                    </dl>

                    <h5
                        class="mt-6 mb-3 text-sm font-semibold text-gray-800 dark:text-white/90"
                    >
                        {{ t('audit.changes') }}
                    </h5>

                    <p
                        v-if="changeRows.length === 0"
                        class="text-sm text-gray-500 dark:text-gray-400"
                    >
                        {{ t('audit.noChanges') }}
                    </p>

                    <div
                        v-else
                        class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800"
                    >
                        <table class="w-full text-left text-sm">
                            <thead
                                class="bg-gray-50 text-theme-xs text-gray-500 dark:bg-white/[0.03] dark:text-gray-400"
                            >
                                <tr>
                                    <th class="px-3 py-2 font-medium">
                                        {{ t('audit.field') }}
                                    </th>
                                    <th
                                        v-if="hasOld"
                                        class="px-3 py-2 font-medium"
                                    >
                                        {{ t('audit.before') }}
                                    </th>
                                    <th
                                        v-if="hasNew"
                                        class="px-3 py-2 font-medium"
                                    >
                                        {{ t('audit.after') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-100 dark:divide-gray-800"
                            >
                                <tr v-for="row in changeRows" :key="row.field">
                                    <td
                                        class="px-3 py-2 font-mono text-theme-xs whitespace-nowrap text-gray-600 dark:text-gray-400"
                                    >
                                        {{ row.field }}
                                    </td>
                                    <td
                                        v-if="hasOld"
                                        class="px-3 py-2 break-all text-error-600 dark:text-error-400"
                                    >
                                        {{ row.before }}
                                    </td>
                                    <td
                                        v-if="hasNew"
                                        class="px-3 py-2 break-all text-success-700 dark:text-success-500"
                                    >
                                        {{ row.after }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </Modal>
    </AdminLayout>
</template>

<script setup lang="ts">
import type { ColDef, ICellRendererParams } from 'ag-grid-community';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { api, toApiError } from '@/api/client';
import DataGrid from '@/components/common/DataGrid.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import DatePicker from '@/components/forms/DatePicker.vue';
import BadgeCell from '@/components/grid/BadgeCell.vue';
import TwoLineCell from '@/components/grid/TwoLineCell.vue';
import ViewCell from '@/components/grid/ViewCell.vue';
import AdminLayout from '@/components/layout/AdminLayout.vue';
import SelectField from '@/components/settings/SelectField.vue';
import TextField from '@/components/settings/TextField.vue';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Modal from '@/components/ui/Modal.vue';
import { useAutoRefresh } from '@/composables/useAutoRefresh';
import { useDebouncedSearch } from '@/composables/useDebouncedSearch';
import { actionColDef } from '@/lib/ag-grid';
import type { AppUser } from '@/types/settings';
import { formatDateTime } from '@/utils/date';
import PageIcon from '~icons/tabler/history';
import CloseIcon from '~icons/tabler/x';

interface ActivityLogRow {
    id: number;
    event: string;
    subject_type: string | null;
    subject_label: string | null;
    causer: { id: number; name: string; email: string } | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string;
}

type BadgeColor =
    'primary' | 'success' | 'error' | 'warning' | 'info' | 'light' | 'dark';

const { t, te } = useI18n();

/** ຕ້ອງກົງກັບ ActivityLogController::EVENTS */
const EVENTS: Record<string, BadgeColor> = {
    login: 'success',
    logout: 'light',
    login_failed: 'error',
    searched: 'info',
    created: 'success',
    updated: 'warning',
    deleted: 'error',
    restored: 'info',
    submitted: 'info',
    approved: 'success',
    rejected: 'error',
    revealed: 'warning',
    reveal_failed: 'error',
};

/** ຕ້ອງກົງກັບ ActivityLogger::SUBJECTS */
const SUBJECTS = [
    'expense',
    'revenue',
    'attachment',
    'budget_item',
    'project',
    'project_member',
    'company',
    'user',
    'payer',
    'payee',
    'expense_category',
    'currency',
    'exchange_rate',
    'bank',
    'credential',
];

/** 'YYYY-MM-DD' ຕາມເວລາເຄື່ອງ — toISOString() ເປັນ UTC ຈະເລື່ອນວັນຕອນເຊົ້າ */
function isoDate(date: Date): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

const today = new Date();
const weekAgo = new Date(today);
weekAgo.setDate(today.getDate() - 6);

const filters = reactive({
    from: isoDate(weekAgo),
    to: isoDate(today),
    event: '',
    subject: '',
    causer_id: '',
});
const search = ref('');

const rows = ref<ActivityLogRow[]>([]);
const users = ref<AppUser[]>([]);
const loading = ref(false);
const loadError = ref<string | null>(null);
const truncated = ref(false);
const cap = ref(2000);

const selected = ref<ActivityLogRow | null>(null);

const eventOptions = Object.keys(EVENTS).map((event) => ({
    value: event,
    label: `audit.events.${event}`,
}));

const subjectOptions = SUBJECTS.map((subject) => ({
    value: subject,
    label: `audit.subjects.${subject}`,
}));

const userOptions = computed(() =>
    users.value.map((u) => ({
        value: String(u.id),
        label: `${u.name} — ${u.email}`,
        translate: false,
    })),
);

function eventLabel(event: string): string {
    return te(`audit.events.${event}`) ? t(`audit.events.${event}`) : event;
}

function eventColor(event: string): BadgeColor {
    return EVENTS[event] ?? 'light';
}

function subjectTypeLabel(type: string | null): string {
    if (!type) {
        return '';
    }

    return te(`audit.subjects.${type}`) ? t(`audit.subjects.${type}`) : type;
}

/** "ລາຍຈ່າຍ · EXP-2026-0074" — ການຄົ້ນຫາບໍ່ມີປະເພດ ຈຶ່ງເຫຼືອແຕ່ຄຳຄົ້ນ */
function subjectText(row: ActivityLogRow): string {
    return [subjectTypeLabel(row.subject_type), row.subject_label]
        .filter(Boolean)
        .join(' · ');
}

/** ບໍ່ມີຜູ້ກະທຳ = ລະບົບ (seeder/artisan) ຫຼື login ລົ້ມເຫຼວ */
function causerText(row: ActivityLogRow): string {
    return row.causer
        ? `${row.causer.name} (${row.causer.email})`
        : t('audit.system');
}

function formatValue(value: unknown): string {
    if (value === undefined) {
        return '';
    }

    if (value === null) {
        return '—';
    }

    return typeof value === 'object' ? JSON.stringify(value) : String(value);
}

const hasOld = computed(() => !!selected.value?.old_values);
const hasNew = computed(() => !!selected.value?.new_values);

const changeRows = computed(() => {
    const row = selected.value;

    if (!row) {
        return [];
    }

    const before = row.old_values ?? {};
    const after = row.new_values ?? {};
    const fields = [
        ...new Set([...Object.keys(before), ...Object.keys(after)]),
    ];

    return fields.map((field) => ({
        field,
        before: formatValue(before[field]),
        after: formatValue(after[field]),
    }));
});

/** ຄຳຕອບທີ່ມາຊ້າກວ່າການໂຫຼດອັນໃໝ່ (ປ່ຽນຕົວກອງໄວໆ / ຮອບອັດຕະໂນມັດ) ຖືກຖິ້ມ */
let loadSeq = 0;

/** @param silent ຮອບໂຫຼດຄືນອັດຕະໂນມັດ — ບໍ່ສະແດງ "ກຳລັງໂຫຼດ", ລົ້ມແລ້ວຮັກສາແຖວເກົ່າ */
async function load(silent = false): Promise<void> {
    const seq = ++loadSeq;

    if (!silent) {
        loading.value = true;
        loadError.value = null;
    }

    try {
        const { data } = await api.get<{
            data: ActivityLogRow[];
            meta: { truncated: boolean; cap: number };
        }>('/activity-logs', {
            params: {
                from: filters.from || undefined,
                to: filters.to || undefined,
                event: filters.event || undefined,
                subject: filters.subject || undefined,
                causer_id: filters.causer_id || undefined,
                search: search.value.trim() || undefined,
            },
        });

        if (seq !== loadSeq) {
            return;
        }

        rows.value = data.data;
        truncated.value = data.meta.truncated;
        cap.value = data.meta.cap;
    } catch (e) {
        if (silent || seq !== loadSeq) {
            return;
        }

        loadError.value = toApiError(e).message;
        rows.value = [];
        truncated.value = false;
    } finally {
        if (seq === loadSeq) {
            loading.value = false;
        }
    }
}

// ເຫດການໃໝ່ (login, ອະນຸມັດ, ແກ້ໄຂ) ປາກົດເອງ. ຢຸດຕອນເປີດລາຍລະອຽດຢູ່ —
// ແຖວທີ່ກຳລັງອ່ານຈະບໍ່ເລື່ອນໜີ
useAutoRefresh(() => load(true), { paused: () => selected.value !== null });

/** ລາຍຊື່ຜູ້ໃຊ້ສຳລັບຕົວກັ່ນ — ລວມບັນຊີທີ່ປິດແລ້ວ: log ເກົ່າຍັງອ້າງເຖິງເຂົາເຈົ້າ */
async function loadUsers(): Promise<void> {
    try {
        const { data } = await api.get<{ data: AppUser[] }>('/users', {
            params: { per_page: 500, include_inactive: 1 },
        });

        users.value = data.data;
    } catch {
        users.value = [];
    }
}

// ---------- ຕາຕະລາງ ----------

const getRowId = (params: { data: ActivityLogRow }) => String(params.data.id);

const columnDefs = computed<ColDef[]>(() => [
    {
        field: 'created_at',
        headerName: t('audit.time'),
        width: 180,
        sort: 'desc',
        valueFormatter: (params) => formatDateTime(params.value),
    },
    {
        colId: 'causer',
        headerName: t('audit.user'),
        flex: 1,
        minWidth: 200,
        valueGetter: (params) => params.data?.causer?.name ?? t('audit.system'),
        cellRenderer: TwoLineCell,
        cellRendererParams: {
            secondary: (params: ICellRendererParams<ActivityLogRow>) =>
                params.data?.causer?.email ?? null,
        },
    },
    {
        field: 'event',
        headerName: t('audit.event'),
        width: 170,
        cellRenderer: BadgeCell,
        cellRendererParams: {
            color: (params: ICellRendererParams<ActivityLogRow>) =>
                eventColor(params.data?.event ?? ''),
        },
        valueGetter: (params) => eventLabel(params.data?.event ?? ''),
    },
    {
        colId: 'subject',
        headerName: t('audit.subject'),
        flex: 2,
        minWidth: 240,
        valueGetter: (params) => params.data?.subject_label ?? '—',
        cellRenderer: TwoLineCell,
        cellRendererParams: {
            secondary: (params: ICellRendererParams<ActivityLogRow>) =>
                subjectTypeLabel(params.data?.subject_type ?? null) || null,
        },
    },
    {
        field: 'ip_address',
        headerName: t('audit.ip'),
        width: 150,
        valueFormatter: (params) => params.value ?? '—',
    },
    {
        ...actionColDef,
        colId: 'actions',
        headerName: t('common.actions'),
        width: 90,
        cellRenderer: ViewCell,
        cellRendererParams: {
            onView: (row: ActivityLogRow) => (selected.value = row),
        },
    },
]);

watch(
    () => ({ ...filters }),
    () => void load(),
);

// ໂຫຼດທຳອິດເກີດຢູ່ນີ້ (immediate) — ບໍ່ຕ້ອງເອີ້ນ load() ແຍກອີກ
useDebouncedSearch(search, () => void load());

void loadUsers();
</script>
