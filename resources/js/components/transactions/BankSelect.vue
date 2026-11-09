<template>
    <FormField
        v-slot="{ id, invalid }"
        :label="label"
        :name="name"
        :error="error"
        :hint="hint"
    >
        <button
            :id="id"
            ref="trigger"
            type="button"
            role="combobox"
            aria-haspopup="listbox"
            :aria-expanded="open"
            :disabled="disabled"
            :class="[
                'flex h-11 w-full items-center justify-between gap-2 rounded-lg border bg-transparent px-3 py-2.5 text-left text-sm shadow-theme-xs focus:ring-3 focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-50 dark:bg-gray-900 dark:disabled:bg-white/5',
                selected
                    ? 'text-gray-800 dark:text-white/90'
                    : 'text-gray-400 dark:text-white/30',
                invalid
                    ? 'border-error-500 focus:border-error-300 focus:ring-error-500/10 dark:border-error-500'
                    : 'border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800',
            ]"
            @click="toggle"
            @keydown="onTriggerKeydown"
        >
            <span class="flex min-w-0 items-center gap-2.5">
                <BankLogo v-if="selected" :bank="selected" size="sm" />
                <span class="truncate">{{
                    selected ? bankLabel(selected) : t(nullLabel)
                }}</span>
            </span>

            <ChevronIcon
                class="h-4 w-4 shrink-0 text-gray-400 transition"
                :class="open ? 'rotate-180' : ''"
            />
        </button>

        <!--
            Teleport + position:fixed ບໍ່ແມ່ນ absolute ທຳມະດາ:
            ຟອມຢູ່ໃນ modal ທີ່ມີ `overflow-y-auto` ເຊິ່ງເປັນຂອບຕັດ — dropdown
            ທີ່ຢູ່ພາຍໃນຈະຖືກຕັດຫາຍເມື່ອຊ່ອງນີ້ຢູ່ໃກ້ຂອບລຸ່ມຂອງຟອມ.
            ຍ້າຍໄປ body ແລ້ວຄິດຕຳແໜ່ງເອງ ຈຶ່ງບໍ່ມີຫຍັງມາຕັດໄດ້.
        -->
        <Teleport to="body">
            <ul
                v-if="open"
                ref="panel"
                role="listbox"
                :style="panelStyle"
                class="fixed z-100000 overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-theme-lg dark:border-gray-700 dark:bg-gray-900"
            >
                <li
                    role="option"
                    :aria-selected="modelValue === ''"
                    :class="optionClass(activeIndex === 0, modelValue === '')"
                    @click="choose('')"
                    @mousemove="activeIndex = 0"
                >
                    <!-- ຊ່ອງເປົ່າຂະໜາດເທົ່າໂລໂກ້ — ໃຫ້ຂໍ້ຄວາມທຸກແຖວຮຽງກັນເປັນຖັນ -->
                    <span class="h-8 w-8 shrink-0" />
                    <span class="truncate">{{ t(nullLabel) }}</span>
                </li>

                <li
                    v-for="(bank, index) in banks"
                    :key="bank.id"
                    role="option"
                    :aria-selected="modelValue === String(bank.id)"
                    :class="
                        optionClass(
                            activeIndex === index + 1,
                            modelValue === String(bank.id),
                        )
                    "
                    @click="choose(String(bank.id))"
                    @mousemove="activeIndex = index + 1"
                >
                    <BankLogo :bank="bank" size="sm" />

                    <span class="min-w-0">
                        <span class="block truncate">{{ bank.name_lo }}</span>
                        <span
                            class="block truncate font-mono text-theme-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ bank.code
                            }}<template v-if="bank.swift_code">
                                · {{ bank.swift_code }}</template
                            >
                        </span>
                    </span>
                </li>
            </ul>
        </Teleport>
    </FormField>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ApiError } from '@/api/client';
import BankLogo from '@/components/common/BankLogo.vue';
import FormField from '@/components/settings/FormField.vue';
import type { Bank } from '@/types/settings';
import ChevronIcon from '~icons/tabler/chevron-down';

/**
 * ຕົວເລືອກທະນາຄານ ພ້ອມໂລໂກ້.
 *
 * ບໍ່ໄດ້ໃຊ້ `SelectField` (ເຊິ່ງເປັນ `<select>` ພື້ນເມືອງ) ເພາະ `<option>`
 * ສະແດງໄດ້ແຕ່ຂໍ້ຄວາມ — ວາງ `<img>` ໃສ່ບໍ່ໄດ້ໃນທຸກ browser.
 *
 * ແລກມາດ້ວຍການຕ້ອງເຮັດເອງທຸກຢ່າງທີ່ `<select>` ໃຫ້ຟຣີ: ການນຳທາງດ້ວຍ
 * ແປ້ນພິມ, ບົດບາດ ARIA, ປິດເມື່ອກົດນອກ. ຖ້າຂາດອັນໃດອັນໜຶ່ງ ອັນນີ້ຈະເປັນ
 * ການຖອຍຫຼັງຈາກ `<select>` ບໍ່ແມ່ນການປັບປຸງ.
 */
interface Props {
    label: string;
    /** ຊື່ field ຢູ່ຝັ່ງ server — ໃຊ້ຫາຂໍ້ຄວາມຈາກ 422 */
    name: string;
    modelValue: string;
    banks: Bank[];
    nullLabel?: string;
    hint?: string;
    error?: ApiError | null;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    nullLabel: 'tx.noBank',
    error: null,
    disabled: false,
});

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const { t } = useI18n();

const trigger = ref<HTMLButtonElement | null>(null);
const panel = ref<HTMLUListElement | null>(null);
const open = ref(false);

/** 0 = "ບໍ່ໄດ້ລະບຸ", 1..n = ທະນາຄານ — ຕົວຊີ້ຂອງແປ້ນພິມ */
const activeIndex = ref(0);

const panelStyle = ref<Record<string, string>>({});

const selected = computed(
    () => props.banks.find((b) => String(b.id) === props.modelValue) ?? null,
);

/** ຊື່ຫຍໍ້ກ່ອນ (BCEL ອ່ານໄວກວ່າຊື່ເຕັມ) ແລ້ວຄ່ອຍຊື່ເຕັມ */
function bankLabel(bank: Bank): string {
    return bank.short_name
        ? `${bank.short_name} — ${bank.name_lo}`
        : bank.name_lo;
}

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
    const openUp = below < 200 && above > below;

    panelStyle.value = {
        left: `${rect.left}px`,
        width: `${rect.width}px`,
        maxHeight: `${Math.min(280, (openUp ? above : below) - 12)}px`,
        ...(openUp
            ? { bottom: `${window.innerHeight - rect.top + 4}px` }
            : { top: `${rect.bottom + 4}px` }),
    };
}

function openPanel(): void {
    if (props.disabled) {
        return;
    }

    // ເລີ່ມທີ່ລາຍການທີ່ເລືອກຢູ່ — ກົດລູກສອນຄັ້ງທຳອິດຈຶ່ງຂຶ້ນຈາກບ່ອນນັ້ນ
    activeIndex.value = selected.value
        ? props.banks.findIndex((b) => b.id === selected.value?.id) + 1
        : 0;

    open.value = true;
    updatePosition();

    void nextTick(scrollActiveIntoView);
}

function closePanel(): void {
    open.value = false;
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
    panel.value?.children[activeIndex.value]?.scrollIntoView({
        block: 'nearest',
    });
}

function move(step: number): void {
    const last = props.banks.length;
    activeIndex.value = Math.min(Math.max(activeIndex.value + step, 0), last);

    void nextTick(scrollActiveIntoView);
}

function onTriggerKeydown(event: KeyboardEvent): void {
    if (!open.value) {
        // ເປີດດ້ວຍລູກສອນ/Enter/ຍະຫວ່າງ ຄືກັບ `<select>` ພື້ນເມືອງ
        if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
            event.preventDefault();
            openPanel();
        }

        return;
    }

    switch (event.key) {
        case 'Escape':
            event.preventDefault();
            closePanel();
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
            move(-props.banks.length);
            break;
        case 'End':
            event.preventDefault();
            move(props.banks.length);
            break;
        case 'Enter':
        case ' ':
            event.preventDefault();
            choose(
                activeIndex.value === 0
                    ? ''
                    : String(props.banks[activeIndex.value - 1].id),
            );
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
 *
 * ຕຳແໜ່ງຂອງ dropdown ຄິດມາຈາກ rect ຂອງປຸ່ມເທື່ອດຽວ ຖ້າໜ້າເລື່ອນມັນຈະ
 * ຄ້າງຢູ່ບ່ອນເກົ່າ ຈຶ່ງຕ້ອງປິດ. ແຕ່ `capture: true` (ຈຳເປັນ ເພາະ scroll
 * ຂອງ element ບໍ່ bubble ຂຶ້ນຫາ window) ເຮັດໃຫ້ listener ນີ້ໄດ້ຍິນການເລື່ອນ
 * **ພາຍໃນລາຍການ** ນຳ — ຜົນຄື ພໍໝູນລໍ້ເບິ່ງທະນາຄານລຸ່ມໆ ລາຍການກໍປິດທັນທີ.
 */
function onScroll(event: Event): void {
    if (panel.value?.contains(event.target as Node)) {
        return;
    }

    closePanel();
}

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
