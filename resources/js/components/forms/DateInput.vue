<template>
    <div class="relative">
        <FlatPickr
            :model-value="modelValue ?? ''"
            :config="config"
            :disabled="disabled"
            :placeholder="t('forms.datePlaceholder')"
            v-bind="$attrs"
            @update:model-value="onChange"
        />

        <span
            class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-gray-400"
        >
            <CalendarIcon class="h-5 w-5" />
        </span>
    </div>
</template>

<script setup lang="ts">
import type { Instance as FlatpickrInstance } from 'flatpickr/dist/types/instance';
import type { CustomLocale } from 'flatpickr/dist/types/locale';
import { computed, onBeforeUnmount } from 'vue';
import FlatPickr from 'vue-flatpickr-component';
import { useI18n } from 'vue-i18n';
import CalendarIcon from '~icons/tabler/calendar-event';
import 'flatpickr/dist/flatpickr.css';

/**
 * ຊ່ອງວັນທີດິບ — ບໍ່ມີປ້າຍຊື່ ບໍ່ມີຂໍ້ຄວາມຜິດພາດ.
 * ໃຊ້ໃນແຖບກັ່ນຕອງທີ່ມີປ້າຍຊື່ຂອງຕົນເອງຢູ່ແລ້ວ.
 * ຢູ່ໃນຟອມໃຫ້ໃຊ້ `DatePicker.vue` ແທນ ເຊິ່ງຫຸ້ມອັນນີ້ດ້ວຍ FormField.
 *
 * ຂໍ້ຕົກລົງສຳຄັນ — **ສອງຮູບແບບ, ຢ່າສັບສົນ**:
 *   ສິ່ງທີ່ຜູ້ໃຊ້ເຫັນ  = ວ/ດ/ປ  (altFormat 'd/m/Y')
 *   ສິ່ງທີ່ສົ່ງໄປ API = ປ-ດ-ວ  (dateFormat 'Y-m-d')
 *
 * ຫ້າມສົ່ງ 'd/m/Y' ໄປ server: Carbon::parse('08/09/2026') ຕີຄວາມເປັນ
 * ວັນທີ 9 ເດືອນ 8 ແບບອາເມລິກາ — ລາຍການຈະໄປລົງຜິດເດືອນໂດຍບໍ່ມີໃຜເຫັນ.
 */
interface Props {
    /** ຄ່າໃນຮູບແບບ ISO 'YYYY-MM-DD' */
    modelValue: string | null;
    disabled?: boolean;
    /** ຫ້າມເລືອກວັນອະນາຄົດ */
    maxToday?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
    maxToday: false,
});

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

defineOptions({ inheritAttrs: false });

const { t, locale } = useI18n();

/**
 * flatpickr ບໍ່ມີພາສາລາວມາໃຫ້ — ປະກາດເອງ.
 * ບໍ່ໄດ້ແປງເປັນ ພ.ສ.: ຖານຂໍ້ມູນເກັບເປັນ ຄ.ສ. ການສະແດງ ພ.ສ. ຢູ່ບ່ອນດຽວ
 * ຈະເຮັດໃຫ້ຜູ້ໃຊ້ປຽບທຽບກັບລາຍງານບໍ່ໄດ້.
 */
const laoLocale: CustomLocale = {
    weekdays: {
        shorthand: ['ອາ', 'ຈ', 'ອຄ', 'ພ', 'ພຫ', 'ສຸ', 'ສ'],
        longhand: ['ອາທິດ', 'ຈັນ', 'ອັງຄານ', 'ພຸດ', 'ພະຫັດ', 'ສຸກ', 'ເສົາ'],
    },
    months: {
        shorthand: [
            'ມ.ກ',
            'ກ.ພ',
            'ມີ.ນ',
            'ມ.ສ',
            'ພ.ພ',
            'ມິ.ຖ',
            'ກ.ລ',
            'ສ.ຫ',
            'ກ.ຍ',
            'ຕ.ລ',
            'ພ.ຈ',
            'ທ.ວ',
        ],
        longhand: [
            'ມັງກອນ',
            'ກຸມພາ',
            'ມີນາ',
            'ເມສາ',
            'ພຶດສະພາ',
            'ມິຖຸນາ',
            'ກໍລະກົດ',
            'ສິງຫາ',
            'ກັນຍາ',
            'ຕຸລາ',
            'ພະຈິກ',
            'ທັນວາ',
        ],
    },
    firstDayOfWeek: 1,
    rangeSeparator: ' ຫາ ',
};

/** ໄລຍະຫ່າງລະຫວ່າງຊ່ອງປ້ອນ ກັບ ປະຕິທິນ (px) */
const GAP = 8;

/** ບໍ່ໃຫ້ປະຕິທິນຕິດຂອບຈໍ (px) */
const VIEWPORT_MARGIN = 8;

/**
 * ວາງປະຕິທິນເອງດ້ວຍ `position: fixed`.
 *
 * ⚠️ ເປັນຫຍັງບໍ່ໃຊ້ການວາງຕຳແໜ່ງມາດຕະຖານຂອງ flatpickr:
 * ມັນເອົາປະຕິທິນໄປຕໍ່ທ້າຍ `<body>` ແບບ `position: absolute` ແລ້ວຄິດ
 * `top = window.pageYOffset + inputBounds.top`. ສູດນັ້ນຖືກ ສະເພາະເມື່ອຊ່ອງ
 * ປ້ອນເລື່ອນໄປພ້ອມກັບໜ້າ — ແຕ່ຊ່ອງວັນທີຂອງພວກເຮົາຢູ່ໃນ modal ທີ່ເປັນ
 * `position: fixed` ຈຶ່ງ `inputBounds.top` ນັບຈາກຂອບຈໍ ບໍ່ແມ່ນຈາກຫົວເອກະສານ.
 * ຜົນຄື: ຖ້າຜູ້ໃຊ້ເລື່ອນໜ້າລົງກ່ອນເປີດ modal ປະຕິທິນຈະຖືກຍູ້ລົງໄປ
 * `pageYOffset` px = ເປີດແລ້ວບໍ່ເຫັນຫຍັງເລີຍ.
 *
 * `fixed` ໃຊ້ພິກັດຈໍໂດຍກົງ ຈຶ່ງບໍ່ຕ້ອງພົວພັນກັບ pageYOffset ເລີຍ.
 */
function positionCalendar(instance: FlatpickrInstance): void {
    const calendar = instance.calendarContainer;
    const anchor = instance._positionElement;

    if (!calendar || !anchor) {
        return;
    }

    const bounds = anchor.getBoundingClientRect();

    // `mt-2` ຈາກ app.css ຈະບວກເພີ່ມໃສ່ top ທີ່ຄິດໄວ້ແລ້ວ ແລະ ຕອນວາງຂ້າງເທິງ
    // ມັນຍູ້ປະຕິທິນລົງມາທັບຊ່ອງປ້ອນ — ໄລຍະຫ່າງຄຸມຢູ່ໃນ GAP ບ່ອນດຽວແທນ
    calendar.style.marginTop = '0px';
    calendar.style.position = 'fixed';

    const height = calendar.offsetHeight;
    const width = calendar.offsetWidth;

    // ບໍ່ພໍບ່ອນຢູ່ລຸ່ມ ແລະ ຂ້າງເທິງພໍ → ພິກຂຶ້ນ
    const showAbove =
        window.innerHeight - bounds.bottom < height + GAP &&
        bounds.top > height + GAP;

    calendar.style.top = showAbove
        ? `${bounds.top - height - GAP}px`
        : `${bounds.bottom + GAP}px`;

    // ບີບໃຫ້ຢູ່ໃນຈໍ — ຊ່ອງທີ່ຢູ່ຂອບຂວາຂອງ modal ຈະລົ້ນອອກນອກຈໍຖ້າບໍ່ບີບ
    const maxLeft = window.innerWidth - width - VIEWPORT_MARGIN;

    calendar.style.left = `${Math.max(VIEWPORT_MARGIN, Math.min(bounds.left, maxLeft))}px`;
    calendar.style.right = 'auto';

    calendar.classList.toggle('arrowTop', !showAbove);
    calendar.classList.toggle('arrowBottom', showAbove);
}

/**
 * ປະຕິທິນທີ່ເປີດຄ້າງໄວ້ — ຕ້ອງວາງຕຳແໜ່ງຄືນເມື່ອມີການເລື່ອນ.
 *
 * flatpickr ຄິດຕຳແໜ່ງ **ເທື່ອດຽວຕອນເປີດ** ແລະ ຜູກແຕ່ `resize` ຂອງ window
 * ເທົ່ານັ້ນ. ຊ່ອງຂອງພວກເຮົາຢູ່ໃນກ່ອງທີ່ເລື່ອນໄດ້ຂອງ FormModal ດັ່ງນັ້ນ
 * ການໝູນລໍ້ຈຶ່ງເຮັດໃຫ້ຊ່ອງປ້ອນຍ້າຍໜີ ແຕ່ປະຕິທິນຄ້າງຢູ່ບ່ອນເກົ່າ —
 * ລອຍທັບຊ່ອງອື່ນຢູ່ກາງຟອມ.
 */
let openInstance: FlatpickrInstance | null = null;

function reposition(): void {
    if (openInstance) {
        positionCalendar(openInstance);
    }
}

function watchScroll(instance: FlatpickrInstance): void {
    openInstance = instance;

    // capture = true: ຈັບການເລື່ອນຂອງ **ທຸກ** ກ່ອງແມ່ ບໍ່ແມ່ນສະເພາະ window
    // (ເຫດການ scroll ບໍ່ bubble ຈຶ່ງດັກຢູ່ຂາລົງແທນ)
    window.addEventListener('scroll', reposition, true);
    window.addEventListener('resize', reposition);
}

function unwatchScroll(): void {
    openInstance = null;
    window.removeEventListener('scroll', reposition, true);
    window.removeEventListener('resize', reposition);
}

// ປິດ modal ຂະນະປະຕິທິນຍັງເປີດ → onClose ບໍ່ຖືກເອີ້ນ, listener ຈະຄ້າງ
onBeforeUnmount(unwatchScroll);

const config = computed(() => ({
    dateFormat: 'Y-m-d', // ຄ່າຈິງທີ່ຜູກກັບ v-model → ສົ່ງໃຫ້ API
    altInput: true, // ສ້າງ input ທີ່ເຫັນໄດ້ອີກອັນໜຶ່ງ
    altFormat: 'd/m/Y', // ສິ່ງທີ່ຜູ້ໃຊ້ເຫັນ ແລະ ພິມ
    allowInput: true, // ພິມເອງໄດ້ ບໍ່ບັງຄັບໃຫ້ຄລິກປະຕິທິນ
    monthSelectorType: 'static' as const,
    locale: locale.value === 'lo' ? laoLocale : undefined,
    maxDate: props.maxToday ? 'today' : undefined,
    position: positionCalendar,
    onOpen: (_d: Date[], _s: string, instance: FlatpickrInstance) =>
        watchScroll(instance),
    onClose: unwatchScroll,
}));

/**
 * vue-flatpickr ຄືນ string ເມື່ອ dateFormat ເປັນ single date ແຕ່ຊະນິດ
 * ຂອງມັນກວ້າງກວ່ານັ້ນ — ບີບໃຫ້ແຄບກ່ອນສົ່ງອອກ ເພື່ອບໍ່ໃຫ້ `Date` ຫຼື
 * `Date[]` ຫຼຸດອອກໄປຫາ payload ແລ້ວກາຍເປັນ ISO ພ້ອມເວລາ.
 */
function onChange(value: unknown): void {
    emit('update:modelValue', typeof value === 'string' ? value : '');
}
</script>
