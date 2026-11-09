<template>
    <div
        v-if="meta"
        class="flex flex-col gap-4 border-t border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
    >
        <!-- ຊ້າຍ: ຈຳນວນແຖວຕໍ່ໜ້າ + ສະຫຼຸບວ່າກຳລັງເບິ່ງແຖວໃດຢູ່ -->
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
            <label
                :for="selectId"
                class="text-theme-xs text-gray-500 dark:text-gray-400"
            >
                {{ t('pagination.perPage') }}
            </label>
            <SearchableSelect
                :id="selectId"
                class="h-9! w-24 py-0!"
                :model-value="String(meta.per_page)"
                :options="pageSizeOptions"
                @update:model-value="onPerPageChange"
            />

            <span class="text-theme-xs text-gray-500 dark:text-gray-400">
                {{
                    meta.total === 0
                        ? t('pagination.empty')
                        : t('pagination.showing', {
                              from: count(meta.from),
                              to: count(meta.to),
                              total: count(meta.total),
                          })
                }}
            </span>
        </div>

        <!--
            ຕົວແບ່ງໜ້າແບບ Bootstrap 5 (« ‹ 1 … 4 5 6 … 9 › ») ຂຽນດ້ວຍ Tailwind —
            ໂຄງການນີ້ບໍ່ໄດ້ໃຊ້ Bootstrap, ການດຶງເຂົ້າມາເພື່ອຕົວແບ່ງໜ້າອັນດຽວ
            ຈະຊ້ອນທັບ reset ແລະ ສີຂອງ theme ທັງໝົດ.

            ເຊື່ອງເມື່ອມີໜ້າດຽວ ແຕ່ **ບໍ່ເຊື່ອງ** ຊ່ອງເລືອກຈຳນວນແຖວຂ້າງເທິງ —
            ຄົນທີ່ຢາກເຫັນ 150 ແຖວຕໍ່ໜ້າຍັງຕ້ອງປ່ຽນຄ່າໄດ້ ເຖິງວ່າດຽວນີ້ຈະມີ
            ໜ້າດຽວກໍຕາມ
        -->
        <nav
            v-if="meta.last_page > 1"
            :aria-label="t('pagination.label')"
            class="flex items-center gap-1"
        >
            <button
                type="button"
                class="page-link"
                :disabled="isFirst"
                :aria-label="t('pagination.first')"
                :title="t('pagination.first')"
                @click="go(1)"
            >
                &laquo;
            </button>
            <button
                type="button"
                class="page-link"
                :disabled="isFirst"
                :aria-label="t('pagination.previous')"
                :title="t('pagination.previous')"
                @click="go(meta.current_page - 1)"
            >
                &lsaquo;
            </button>

            <template v-for="(page, index) in pages" :key="index">
                <span
                    v-if="page === GAP"
                    class="px-2 text-sm text-gray-400 select-none"
                    aria-hidden="true"
                >
                    &hellip;
                </span>
                <button
                    v-else
                    type="button"
                    class="page-link"
                    :class="
                        page === meta.current_page
                            ? 'border-brand-500 bg-brand-500 text-white hover:bg-brand-600'
                            : ''
                    "
                    :aria-current="
                        page === meta.current_page ? 'page' : undefined
                    "
                    @click="go(page)"
                >
                    {{ page }}
                </button>
            </template>

            <button
                type="button"
                class="page-link"
                :disabled="isLast"
                :aria-label="t('pagination.next')"
                :title="t('pagination.next')"
                @click="go(meta.current_page + 1)"
            >
                &rsaquo;
            </button>
            <button
                type="button"
                class="page-link"
                :disabled="isLast"
                :aria-label="t('pagination.last')"
                :title="t('pagination.last')"
                @click="go(meta.last_page)"
            >
                &raquo;
            </button>
        </nav>
    </div>
</template>

<script setup lang="ts">
import { computed, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import SearchableSelect from '@/components/common/SearchableSelect.vue';
import type { PageMeta } from '@/types/settings';
import { groupNumeric } from '@/utils/money';

/**
 * ຕົວແບ່ງໜ້າທີ່ໃຊ້ຮ່ວມກັນ — ຂັບເຄື່ອນດ້ວຍ `meta` ຂອງ Laravel paginator ໂດຍກົງ
 * ຈຶ່ງບໍ່ມີ state ຂອງຕົນເອງໃຫ້ຄາດເຄື່ອນຈາກ server (ເຊັ່ນ ຄ້າງຢູ່ໜ້າ 5 ຫຼັງ
 * ຈຳນວນແຖວຫຼຸດລົງຈົນເຫຼືອ 2 ໜ້າ).
 */
interface Props {
    /** null = ຍັງບໍ່ໄດ້ໂຫຼດ — ບໍ່ສະແດງຫຍັງ */
    meta: PageMeta | null;
    /** ຈຳນວນໜ້າທີ່ສະແດງເປັນຕົວເລກອ້ອມໜ້າປັດຈຸບັນ (ບໍ່ນັບໜ້າທຳອິດ/ສຸດທ້າຍ) */
    window?: number;
}

const props = withDefaults(defineProps<Props>(), { window: 1 });

const emit = defineEmits<{
    'update:page': [page: number];
    'update:perPage': [perPage: number];
}>();

const { t } = useI18n();

/** ຄ່າທີ່ຜູ້ໃຊ້ຂໍ. ເພດານ 150 ຖືກບັງຄັບຢູ່ server ນຳ (MAX_PER_PAGE) */
const pageSizes = [20, 50, 100, 150];

const pageSizeOptions = pageSizes.map((size) => ({
    value: String(size),
    label: String(size),
    translate: false,
}));

/** ໝາຍຈຸດ "…" — ໃຊ້ຄ່າທີ່ບໍ່ມີທາງເປັນເລກໜ້າຈິງ */
const GAP = -1;

const selectId = useId();

/** ຈຳນວນນັບ (ບໍ່ມີທົດສະນິຍົມ) ພ້ອມຈຸດຫຼັກພັນ: 1234 → "1,234" */
function count(value: number | null): string {
    return groupNumeric(String(value ?? 0), 0);
}

const isFirst = computed(() => (props.meta?.current_page ?? 1) <= 1);

const isLast = computed(
    () => (props.meta?.current_page ?? 1) >= (props.meta?.last_page ?? 1),
);

/**
 * ລຳດັບປຸ່ມເລກໜ້າ: ໜ້າທຳອິດ, ໜ້າສຸດທ້າຍ ແລະ ໜ້າອ້ອມໜ້າປັດຈຸບັນສະເໝີ,
 * ບ່ອນທີ່ຂາດຕອນໃສ່ "…". ຄວາມກວ້າງຄົງທີ່ ປຸ່ມຈຶ່ງບໍ່ກະໂດດໄປມາຕອນປ່ຽນໜ້າ.
 */
const pages = computed<number[]>(() => {
    const meta = props.meta;

    if (!meta) {
        return [];
    }

    const last = meta.last_page;
    const current = meta.current_page;

    const wanted = new Set<number>([1, last]);

    for (let p = current - props.window; p <= current + props.window; p += 1) {
        if (p >= 1 && p <= last) {
            wanted.add(p);
        }
    }

    const sorted = [...wanted].sort((a, b) => a - b);
    const result: number[] = [];

    sorted.forEach((page, index) => {
        // ຊ່ອງຫວ່າງ 1 ໜ້າບໍ່ຄຸ້ມທີ່ຈະໃສ່ "…" — ສະແດງເລກນັ້ນເລີຍສັ້ນກວ່າ
        const previous = sorted[index - 1];

        if (previous !== undefined && page - previous > 1) {
            result.push(page - previous === 2 ? page - 1 : GAP);
        }

        result.push(page);
    });

    return result;
});

function go(page: number): void {
    const meta = props.meta;

    if (!meta || page < 1 || page > meta.last_page || page === meta.current_page) {
        return;
    }

    emit('update:page', page);
}

function onPerPageChange(size: string): void {
    emit('update:perPage', Number(size));
}
</script>
