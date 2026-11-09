<template>
    <button
        :id="id"
        ref="trigger"
        v-bind="$attrs"
        type="button"
        role="combobox"
        aria-haspopup="listbox"
        :aria-expanded="open"
        :aria-controls="open ? listId : undefined"
        :disabled="disabled"
        :class="triggerClass"
        @click="toggle"
        @keydown="onTriggerKeydown"
    >
        <span class="truncate">{{ triggerLabel }}</span>

        <ChevronIcon
            class="h-4 w-4 shrink-0 text-gray-400 transition"
            :class="open ? 'rotate-180' : ''"
        />
    </button>

    <!--
        Teleport + position:fixed ບໍ່ແມ່ນ absolute ທຳມະດາ — ເຫດຜົນອັນດຽວກັນ
        ກັບ BankSelect: ຟອມຢູ່ໃນ modal ທີ່ມີ `overflow-y-auto` ເຊິ່ງເປັນຂອບຕັດ,
        dropdown ທີ່ຢູ່ພາຍໃນຈະຖືກຕັດຫາຍເມື່ອຊ່ອງນີ້ຢູ່ໃກ້ຂອບລຸ່ມ.
    -->
    <Teleport to="body">
        <div
            v-if="open"
            ref="panel"
            :style="panelStyle"
            class="fixed z-100000 flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-theme-lg dark:border-gray-700 dark:bg-gray-900"
        >
            <div
                v-if="searchable"
                class="shrink-0 border-b border-gray-100 p-2 dark:border-gray-800"
            >
                <div class="relative">
                    <SearchIcon
                        class="pointer-events-none absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2 text-gray-400"
                    />
                    <input
                        ref="searchInput"
                        v-model="query"
                        type="text"
                        autocomplete="off"
                        :placeholder="t('common.searchOptions')"
                        :aria-label="t('common.searchOptions')"
                        class="h-9 w-full rounded-md border border-gray-200 bg-transparent pr-3 pl-8 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-none dark:border-gray-700 dark:text-white/90"
                        @keydown="onPanelKeydown"
                    />
                </div>
            </div>

            <ul
                :id="listId"
                ref="list"
                role="listbox"
                class="min-h-0 flex-1 overflow-y-auto py-1"
            >
                <li
                    v-for="(option, index) in filtered"
                    :key="option.value"
                    role="option"
                    :aria-selected="option.value === modelValue"
                    :class="
                        optionClass(
                            index === activeIndex,
                            option.value === modelValue,
                        )
                    "
                    @click="choose(option.value)"
                    @mousemove="activeIndex = index"
                >
                    <span class="min-w-0">
                        <span class="block truncate">{{ option.label }}</span>
                        <span
                            v-if="option.secondary"
                            class="block truncate text-theme-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ option.secondary }}
                        </span>
                    </span>
                </li>

                <li
                    v-if="filtered.length === 0"
                    class="px-3 py-3 text-center text-theme-xs text-gray-500 dark:text-gray-400"
                >
                    {{ t('common.noResults') }}
                </li>
            </ul>
        </div>
    </Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, useId, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ChevronIcon from '~icons/tabler/chevron-down';
import SearchIcon from '~icons/tabler/search';

/**
 * ຕົວເລືອກທີ່ຄົ້ນຫາໄດ້ — ຕົວແທນຂອງ `<select>` ພື້ນເມືອງທົ່ວທັງລະບົບ.
 *
 * ເປັນຫຍັງບໍ່ໃຊ້ select2: select2 ເປັນ plugin ຂອງ jQuery ທີ່ແກ້ DOM ເອງ
 * ເຊິ່ງຈະຊົນກັບ virtual DOM ຂອງ Vue (ຕ້ອງ init/destroy ແລະ sync ດ້ວຍມື
 * ທຸກຄັ້ງທີ່ options ປ່ຽນ) ແລະ theme ຂອງມັນເປັນໂໝດສະຫວ່າງຢ່າງດຽວ —
 * ຕ້ອງຂຽນ CSS ໂໝດມືດຄືນໃໝ່ທັງໝົດ. ອັນນີ້ໃຫ້ພຶດຕິກຳອັນດຽວກັນ
 * (ຄົ້ນຫາ, ແປ້ນພິມ, ARIA) ໂດຍບໍ່ເພີ່ມ dependency ເລີຍ.
 *
 * ໂຄງສ້າງລອກມາຈາກ BankSelect — ຖ້າແກ້ການວາງຕຳແໜ່ງຢູ່ນີ້ ໃຫ້ເບິ່ງບ່ອນນັ້ນນຳ.
 */
export interface SearchableOption {
    value: string;
    label: string;
    /** ແຖວທີສອງ ຕົວນ້ອຍ (ເຊັ່ນ ອີເມວ) — ຖືກຄົ້ນຫານຳ */
    secondary?: string | null;
    /** false = ໃຊ້ label ດິບ (ຊື່ອົງກອນ/ຜູ້ໃຊ້) ບໍ່ຕ້ອງແປ */
    translate?: boolean;
}

interface Props {
    modelValue: string | null;
    options: SearchableOption[];
    /** id ຈາກ FormField — ຜູກ <label> ເຂົ້າກັບປຸ່ມ */
    id?: string;
    /** ຂໍ້ຄວາມເມື່ອຍັງບໍ່ໄດ້ເລືອກ (ເລືອກກັບຄືນບໍ່ໄດ້) */
    placeholder?: string;
    /** ອະນຸຍາດໃຫ້ບໍ່ເລືອກ — ເພີ່ມແຖວ "ບໍ່ມີ" ເທິງສຸດ */
    nullable?: boolean;
    nullLabel?: string;
    disabled?: boolean;
    invalid?: boolean;
    /** 'filter' = ຊ່ອງກັ່ນຕອງເທິງໜ້າລາຍການ, 'field' = ຊ່ອງໃນຟອມ */
    variant?: 'field' | 'filter';
    /**
     * ຈຳນວນຕົວເລືອກຕ່ຳສຸດທີ່ຈຶ່ງສະແດງຊ່ອງຄົ້ນຫາ.
     *
     * ລາຍການສັ້ນ (ລະດັບສິດ 4 ອັນ, ສະຖານະ 5 ອັນ) ອ່ານໝົດໄວກວ່າພິມ — ຊ່ອງ
     * ຄົ້ນຫາຢູ່ເທິງມັນເປັນພຽງສິ່ງລົບກວນ. ຄືກັບ `minimumResultsForSearch`
     * ຂອງ select2. ປ່ຽນເລກນີ້ບ່ອນດຽວ = ປ່ຽນທົ່ວທັງລະບົບ.
     */
    minSearchable?: number;
}

const props = withDefaults(defineProps<Props>(), {
    id: undefined,
    placeholder: undefined,
    nullLabel: undefined,
    nullable: false,
    disabled: false,
    invalid: false,
    variant: 'field',
    minSearchable: 8,
});

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

defineOptions({ inheritAttrs: false });

const { t } = useI18n();

const trigger = ref<HTMLButtonElement | null>(null);
const panel = ref<HTMLDivElement | null>(null);
const list = ref<HTMLUListElement | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);

const open = ref(false);
const query = ref('');
const activeIndex = ref(0);
const panelStyle = ref<Record<string, string>>({});

const listId = useId();

/** ຕົວເລືອກທີ່ແປແລ້ວ ພ້ອມແຖວ "ບໍ່ມີ" ຖ້າ nullable — ບ່ອນດຽວທີ່ຄິດ label */
const resolved = computed<SearchableOption[]>(() => {
    const rows = props.options.map((option) => ({
        value: option.value,
        label: option.translate === false ? option.label : t(option.label),
        secondary: option.secondary ?? null,
    }));

    if (props.nullable) {
        rows.unshift({
            value: '',
            label: t(props.nullLabel ?? 'common.none'),
            secondary: null,
        });
    }

    return rows;
});

/**
 * ຄົ້ນຫາແບບ substring ທຳມະດາ ບໍ່ແມ່ນແບ່ງຄຳ — ພາສາລາວບໍ່ມີຊ່ອງວ່າງ
 * ລະຫວ່າງຄຳ ການແບ່ງດ້ວຍຊ່ອງວ່າງຈຶ່ງໃຊ້ບໍ່ໄດ້ (ເບິ່ງ docs/architecture.md §2.1.ງ).
 */
const filtered = computed<SearchableOption[]>(() => {
    const term = query.value.trim().toLowerCase();

    if (term === '') {
        return resolved.value;
    }

    return resolved.value.filter(
        (option) =>
            option.label.toLowerCase().includes(term) ||
            (option.secondary?.toLowerCase().includes(term) ?? false),
    );
});

const selected = computed(
    () =>
        resolved.value.find((option) => option.value === props.modelValue) ??
        null,
);

const triggerLabel = computed(() => {
    if (selected.value) {
        return selected.value.label;
    }

    return props.placeholder ? t(props.placeholder) : '';
});

const searchable = computed(() => resolved.value.length >= props.minSearchable);

const triggerClass = computed(() => {
    const base =
        'flex w-full items-center justify-between gap-2 text-left disabled:cursor-not-allowed';
    const tone = selected.value
        ? 'text-gray-800 dark:text-white/90'
        : 'text-gray-400 dark:text-white/30';

    if (props.variant === 'filter') {
        return `filter-control ${base} ${tone}`;
    }

    const border = props.invalid
        ? 'border-error-500 focus:border-error-300 focus:ring-error-500/10 dark:border-error-500'
        : 'border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800';

    return `${base} h-11 rounded-lg border bg-transparent px-4 py-2.5 text-sm shadow-theme-xs focus:ring-3 focus:outline-none disabled:bg-gray-50 dark:bg-gray-900 dark:disabled:bg-white/5 ${tone} ${border}`;
});

function optionClass(active: boolean, isSelected: boolean): string {
    return [
        'flex cursor-pointer items-center gap-2.5 px-3 py-2 text-sm',
        active ? 'bg-gray-100 dark:bg-white/5' : '',
        isSelected
            ? 'font-medium text-brand-500'
            : 'text-gray-700 dark:text-gray-300',
    ].join(' ');
}

/**
 * ຄິດຕຳແໜ່ງຈາກ rect ຂອງປຸ່ມ ແລະ ພື້ນທີ່ວ່າງທີ່ເຫຼືອ.
 * ເປີດຂຶ້ນເທິງເມື່ອລຸ່ມບໍ່ພໍ — ບໍ່ດັ່ງນັ້ນລາຍການຈະຍາວອອກນອກຈໍ.
 */
function updatePosition(): void {
    const rect = trigger.value?.getBoundingClientRect();

    if (!rect) {
        return;
    }

    const below = window.innerHeight - rect.bottom;
    const above = rect.top;
    const openUp = below < 240 && above > below;

    panelStyle.value = {
        left: `${rect.left}px`,
        width: `${rect.width}px`,
        maxHeight: `${Math.min(320, (openUp ? above : below) - 12)}px`,
        ...(openUp
            ? { bottom: `${window.innerHeight - rect.top + 4}px` }
            : { top: `${rect.bottom + 4}px` }),
    };
}

function openPanel(seed = ''): void {
    if (props.disabled) {
        return;
    }

    query.value = seed;

    // ເລີ່ມທີ່ລາຍການທີ່ເລືອກຢູ່ — ກົດລູກສອນຄັ້ງທຳອິດຈຶ່ງຂຶ້ນຈາກບ່ອນນັ້ນ
    const current = resolved.value.findIndex(
        (option) => option.value === props.modelValue,
    );

    activeIndex.value = current === -1 ? 0 : current;

    open.value = true;
    updatePosition();

    void nextTick(() => {
        searchInput.value?.focus();
        scrollActiveIntoView();
    });
}

function closePanel(): void {
    open.value = false;
    query.value = '';
}

function toggle(): void {
    if (open.value) {
        closePanel();

        return;
    }

    openPanel();
}

function choose(value: string): void {
    emit('update:modelValue', value);
    closePanel();
    trigger.value?.focus();
}

function scrollActiveIntoView(): void {
    list.value?.children[activeIndex.value]?.scrollIntoView({
        block: 'nearest',
    });
}

function move(step: number): void {
    const last = filtered.value.length - 1;

    if (last < 0) {
        return;
    }

    activeIndex.value = Math.min(Math.max(activeIndex.value + step, 0), last);

    void nextTick(scrollActiveIntoView);
}

function onTriggerKeydown(event: KeyboardEvent): void {
    if (open.value) {
        // ລາຍການສັ້ນບໍ່ມີຊ່ອງຄົ້ນຫາ → focus ຍັງຢູ່ປຸ່ມ, ຈັດການແປ້ນຢູ່ນີ້.
        // ຍະຫວ່າງ = ເລືອກ ຄືກັບ `<select>` — ຢູ່ຊ່ອງຄົ້ນຫາມັນຄືການພິມ
        // ຈຶ່ງຈັດການຢູ່ນີ້ ບໍ່ແມ່ນໃນ onPanelKeydown ທີ່ໃຊ້ຮ່ວມກັນ
        if (event.key === ' ') {
            event.preventDefault();

            if (filtered.value[activeIndex.value]) {
                choose(filtered.value[activeIndex.value].value);
            }

            return;
        }

        onPanelKeydown(event);

        return;
    }

    // ເປີດດ້ວຍລູກສອນ/Enter/ຍະຫວ່າງ ຄືກັບ `<select>` ພື້ນເມືອງ
    if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
        event.preventDefault();
        openPanel();

        return;
    }

    // ພິມຕົວອັກສອນທັນທີ = ເປີດແລ້ວຄົ້ນຫາເລີຍ ຄືກັບ select2.
    // ສະເພາະລາຍການທີ່ມີຊ່ອງຄົ້ນຫາ — ບໍ່ດັ່ງນັ້ນລາຍການຈະຖືກກັ່ນຕອງດ້ວຍ
    // ຄຳຄົ້ນຫາທີ່ຜູ້ໃຊ້ເບິ່ງບໍ່ເຫັນ ແລະ ລຶບອອກບໍ່ໄດ້
    if (
        searchable.value &&
        event.key.length === 1 &&
        !event.ctrlKey &&
        !event.metaKey &&
        !event.altKey
    ) {
        event.preventDefault();
        openPanel(event.key);
    }
}

function onPanelKeydown(event: KeyboardEvent): void {
    switch (event.key) {
        case 'Escape':
            event.preventDefault();
            closePanel();
            trigger.value?.focus();
            break;
        case 'ArrowDown':
            event.preventDefault();
            move(1);
            break;
        case 'ArrowUp':
            event.preventDefault();
            move(-1);
            break;
        case 'Home':
            event.preventDefault();
            move(-filtered.value.length);
            break;
        case 'End':
            event.preventDefault();
            move(filtered.value.length);
            break;
        case 'Enter':
            event.preventDefault();

            if (filtered.value[activeIndex.value]) {
                choose(filtered.value[activeIndex.value].value);
            }

            break;
        case 'Tab':
            // ອອກຈາກຊ່ອງນີ້ = ປິດ ບໍ່ແມ່ນປະໄວ້ລອຍໆເທິງຟອມ
            closePanel();
            break;
    }
}

function onPointerDown(event: PointerEvent): void {
    const target = event.target as Node;

    if (
        !trigger.value?.contains(target) &&
        !panel.value?.contains(target) &&
        open.value
    ) {
        closePanel();
    }
}

/**
 * ປິດເມື່ອໜ້າຖືກເລື່ອນ — ແຕ່ **ບໍ່ແມ່ນ**ເມື່ອເລື່ອນຢູ່ໃນລາຍການເອງ.
 * ຕຳແໜ່ງຄິດມາຈາກ rect ຂອງປຸ່ມເທື່ອດຽວ ຖ້າໜ້າເລື່ອນມັນຈະຄ້າງຢູ່ບ່ອນເກົ່າ.
 */
function onScroll(event: Event): void {
    if (panel.value?.contains(event.target as Node)) {
        return;
    }

    closePanel();
}

// ພິມຄົ້ນຫາແລ້ວລາຍການສັ້ນລົງ — ຕົວຊີ້ເກົ່າອາດເກີນທ້າຍລາຍການ
watch(filtered, () => {
    activeIndex.value = 0;
});

watch(open, (isOpen) => {
    const method = isOpen ? 'addEventListener' : 'removeEventListener';

    document[method]('pointerdown', onPointerDown as EventListener);
    window[method]('scroll', onScroll, true);
    window[method]('resize', closePanel);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onPointerDown as EventListener);
    window.removeEventListener('scroll', onScroll, true);
    window.removeEventListener('resize', closePanel);
});
</script>
