<template>
    <div
        class="mb-6 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]"
    >
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <label class="xl:col-span-3">
                <span class="filter-label">{{ t('ledger.project') }}</span>
                <SearchableSelect
                    v-model="projectUuid"
                    variant="filter"
                    class="w-full"
                    :options="projectOptions"
                    placeholder="ledger.selectProject"
                />
            </label>

            <!--
                ຄົ້ນຫາ ແລະ ຜູ້ຈ່າຍ ຢູ່ເທິງສຸດຮອງຈາກໂຄງການ — ສອງອັນນີ້ຄືສິ່ງທີ່ຄົນ
                ປ່ຽນຖີ່ທີ່ສຸດຕອນຫາລາຍການໃດໜຶ່ງ
            -->
            <label class="sm:col-span-2 xl:col-span-1">
                <span class="filter-label">{{ t('reports.search') }}</span>
                <input
                    v-model="search"
                    type="search"
                    class="filter-control w-full"
                    :placeholder="t(searchHint)"
                />
            </label>

            <label>
                <span class="filter-label">{{ t(payerLabel) }}</span>
                <SearchableSelect
                    v-model="payerValue"
                    variant="filter"
                    class="w-full"
                    :options="payerOptions"
                    nullable
                    :null-label="allPayersLabel"
                />
            </label>

            <!-- ໝວດມີສະເພາະລາຍງານລາຍຈ່າຍ — ລາຍຮັບບໍ່ມີມິຕິນີ້ໃນຖານຂໍ້ມູນ -->
            <label v-if="categories">
                <span class="filter-label">{{ t('tx.category') }}</span>
                <SearchableSelect
                    v-model="categoryValue"
                    variant="filter"
                    class="w-full"
                    :options="categoryOptions"
                    nullable
                    null-label="reports.allCategories"
                />
            </label>

            <label>
                <span class="filter-label">{{ t('ledger.dateFrom') }}</span>
                <DateInput
                    v-model="dateFrom"
                    class="filter-control w-full pl-11"
                />
            </label>

            <label>
                <span class="filter-label">{{ t('ledger.dateTo') }}</span>
                <DateInput
                    v-model="dateTo"
                    class="filter-control w-full pl-11"
                />
            </label>

            <label>
                <span class="filter-label">{{ t('ledger.status') }}</span>
                <SearchableSelect
                    :model-value="statusMode"
                    variant="filter"
                    class="w-full"
                    :options="statusOptions"
                    @update:model-value="statusMode = $event as StatusMode"
                />
            </label>
        </div>

        <div
            class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4 dark:border-gray-800"
        >
            <p class="text-theme-xs text-gray-500 dark:text-gray-400">
                {{ t('reports.printHint') }}
            </p>

            <div class="flex items-center gap-2">
                <Button
                    v-if="hasFilters"
                    size="sm"
                    variant="outline"
                    :on-click="() => emit('reset')"
                >
                    {{ t('reports.reset') }}
                </Button>

                <!--
                    ປິດປຸ່ມເມື່ອບໍ່ມີແຖວ ຫຼື ເກີນເພດານ — server ຈະປະຕິເສດຢູ່ດີ
                    ໃຫ້ຮູ້ຕັ້ງແຕ່ຢູ່ໜ້າຈໍດີກວ່າກົດແລ້ວຈຶ່ງໄດ້ error
                -->
                <Button
                    size="sm"
                    :start-icon="PrinterIcon"
                    :disabled="!canPrint"
                    :on-click="() => emit('print')"
                >
                    {{ t('reports.print') }}
                </Button>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SearchableSelect from '@/components/common/SearchableSelect.vue';
import DateInput from '@/components/forms/DateInput.vue';
import Button from '@/components/ui/Button.vue';
import type { StatusMode } from '@/composables/useReportFilters';
import type { Project } from '@/types/settings';
import PrinterIcon from '~icons/tabler/printer';

/**
 * ແຖບຕົວກັ່ນຕອງຮ່ວມຂອງໜ້າລາຍງານທັງສາມ.
 *
 * ຮັບ label ຂອງຊ່ອງຜູ້ຈ່າຍເປັນ prop ເພາະຄຳສັບຕ່າງກັນຕາມລາຍງານ:
 * ລາຍຈ່າຍເອີ້ນ "ຜູ້ຈ່າຍ" ສ່ວນລາຍຮັບເອີ້ນ "ແຫຼ່ງທຶນ" ເຖິງແມ່ນເປັນຕາຕະລາງ payers ອັນດຽວກັນ.
 */
const props = withDefaults(
    defineProps<{
        projects: Project[];
        payers: { id: number; name: string }[];
        /** ໃສ່ເມື່ອລາຍງານນັ້ນມີມິຕິໝວດ (ລາຍຈ່າຍເທົ່ານັ້ນ) — ບໍ່ໃສ່ = ເຊື່ອງຊ່ອງ */
        categories?: { id: number; name_lo: string }[];
        payerLabel?: string;
        allPayersLabel?: string;
        searchHint?: string;
        hasFilters: boolean;
        canPrint: boolean;
    }>(),
    {
        categories: undefined,
        payerLabel: 'tx.payer',
        allPayersLabel: 'reports.allPayers',
        searchHint: 'reports.searchHint',
    },
);

const emit = defineEmits<{ print: []; reset: [] }>();

const projectUuid = defineModel<string>('projectUuid', { required: true });
const search = defineModel<string>('search', { required: true });
const payerId = defineModel<number | ''>('payerId', { required: true });
const categoryId = defineModel<number | ''>('categoryId', { default: '' });
const dateFrom = defineModel<string>('dateFrom', { required: true });
const dateTo = defineModel<string>('dateTo', { required: true });
const statusMode = defineModel<StatusMode>('statusMode', { required: true });

const { t } = useI18n();

const projectOptions = computed(() =>
    props.projects.map((project) => ({
        value: project.uuid,
        label: project.name,
        secondary: project.code,
        translate: false,
    })),
);

const payerOptions = computed(() =>
    props.payers.map((payer) => ({
        value: String(payer.id),
        label: payer.name,
        translate: false,
    })),
);

const categoryOptions = computed(() =>
    (props.categories ?? []).map((category) => ({
        value: String(category.id),
        label: category.name_lo,
        translate: false,
    })),
);

const statusOptions = [
    { value: 'approved', label: 'ledger.approvedOnly' },
    { value: 'all', label: 'ledger.allStatuses' },
];

/**
 * ຕົວກັ່ນຕອງເກັບ id ເປັນ number ແຕ່ຊ່ອງເລືອກເຮັດວຽກກັບ string —
 * ແປງບ່ອນນີ້ບ່ອນດຽວ ດີກວ່າປ່ຽນຊະນິດຂອງ useReportFilters ທັງໝົດ.
 * '' = ບໍ່ກັ່ນຕອງ (ບໍ່ແມ່ນ id 0).
 */
const payerValue = computed<string>({
    get: () => (payerId.value === '' ? '' : String(payerId.value)),
    set: (value) => {
        payerId.value = value === '' ? '' : Number(value);
    },
});

const categoryValue = computed<string>({
    get: () => (categoryId.value === '' ? '' : String(categoryId.value)),
    set: (value) => {
        categoryId.value = value === '' ? '' : Number(value);
    },
});
</script>
