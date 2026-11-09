<template>
    <!--
        `dense` = ຢູ່ໃນຟອມກວ້າງ: ສະກຸນ / ຈຳນວນ / ອັດຕາ / ຕົວຢ່າງ ຢູ່ແຖວດຽວກັນ
        (5 ຖັນ ໂດຍຕົວຢ່າງກິນ 2) ແທນທີ່ຈະຊ້ອນເປັນ 2 ແຖວ
    -->
    <div
        class="grid gap-x-5 gap-y-4 sm:grid-cols-2"
        :class="{ 'lg:grid-cols-5': dense }"
    >
        <SelectField
            :model-value="currency"
            label="tx.currency"
            name="currency_code"
            :error="error"
            :options="currencyOptions"
            required
            @update:model-value="onCurrencyChange"
        />

        <NumericField
            :model-value="amount"
            label="tx.amount"
            name="amount"
            :decimals="amountDecimals"
            :error="error"
            hint="tx.amountHint"
            required
            @update:model-value="$emit('update:amount', $event)"
        />

        <!--
            ອັດຕາແລກປ່ຽນສະແດງສະເພາະສະກຸນທີ່ບໍ່ແມ່ນ USD — ໃສ່ຊ່ອງ rate
            ໃຫ້ USD ຈະສັບສົນ (rate ຂອງ USD ຄື 1 ສະເໝີ).

            ພິມເປັນກີບແບບປ້າຍ BCEL: LAK = ກີບຕໍ່ 1 USD, THB = ກີບຕໍ່ 1 ບາດ.
            server ແປງເປັນ "ຕໍ່ 1 USD" ຜ່ານ CurrencyConverter.
        -->
        <NumericField
            v-if="!isUsd"
            :model-value="rate"
            :label="t('tx.fxRateLak', { code: quoteCode })"
            name="fx_rate_lak"
            :decimals="RATE_DECIMALS"
            trim-zeros
            :error="error"
            :hint="rateHint"
            @update:model-value="$emit('update:rate', $event)"
        />

        <!--
            ຕົວຢ່າງຍອດແປງແບບສົດ — ນີ້ຄືສິ່ງທີ່ຈັບຂໍ້ຜິດພາດ "rate 2117
            ແທນ 21170" ທີ່ເຮັດໃຫ້ຍອດໃນ Excel ເດີມຜິດໄປ 5,314 USD.
            ຄົນເຫັນ "USD 5,904" ແທນ "USD 590" ແລ້ວຈະຮູ້ທັນທີວ່າຜິດ.
        -->
        <div
            v-if="previews.length > 0"
            :class="[
                { 'sm:col-span-2': isUsd },
                // USD ບໍ່ມີຊ່ອງອັດຕາ ຈຶ່ງເຫຼືອບ່ອນໃຫ້ຕົວຢ່າງອີກ 1 ຖັນ
                dense && (isUsd ? 'lg:col-span-3' : 'lg:col-span-2'),
            ]"
        >
            <!--
                ບ່ອນເວັ້ນແທນປ້າຍຊື່: ຊ່ອງປ້ອນຂ້າງໆມີປ້າຍ (text-sm = 20px) +
                mb-1.5 (6px) ຢູ່ເທິງ ແຕ່ກ່ອງນີ້ບໍ່ມີ — ຖ້າບໍ່ເວັ້ນໄວ້ເທົ່າກັນ
                ກ່ອງຈະລອຍສູງກວ່າແຖວຂອງຊ່ອງປ້ອນ. ໃຊ້ຫົວໜ່ວຍດຽວກັບ FormField
                ຈຶ່ງຍັງກົງກັນເຖິງແມ່ນຂະໜາດຕົວອັກສອນຈະປ່ຽນ.

                ສະແດງສະເພາະຕອນກ່ອງຢູ່ແຖວດຽວກັບຊ່ອງປ້ອນຈິງ — ຖ້າມັນຢູ່ແຖວ
                ຂອງມັນເອງ (ມືຖື ຫຼື USD ທີ່ບໍ່ມີຊ່ອງອັດຕາ) ບ່ອນເວັ້ນຈະກາຍເປັນ
                ຊ່ອງຫວ່າງທີ່ບໍ່ມີເຫດຜົນ
            -->
            <span
                class="mb-1.5 hidden text-sm select-none"
                :class="[!isUsd && 'sm:block', dense && 'lg:block']"
                >&nbsp;</span
            >

            <div
                class="w-full rounded-lg border px-4 py-2.5"
                :class="
                    warning
                        ? 'border-warning-300 bg-warning-50 dark:border-warning-500/30 dark:bg-warning-500/10'
                        : 'border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03]'
                "
            >
                <div
                    class="grid gap-3"
                    :class="{ 'grid-cols-2': previews.length > 1 }"
                >
                    <div v-for="preview in previews" :key="preview.code">
                        <p
                            class="text-theme-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ preview.label }}
                        </p>
                        <p
                            class="text-lg font-semibold text-gray-800 tabular-nums dark:text-white/90"
                        >
                            {{ preview.value }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ຄຳເຕືອນຈາກ server (ອັດຕານອກຂອບເຂດປົກກະຕິ) — ເຕືອນ ບໍ່ໄດ້ຂວາງ -->
    <p
        v-if="warning"
        class="mt-3 flex items-start gap-2 rounded-lg bg-warning-50 p-3 text-theme-xs text-warning-700 dark:bg-warning-500/10 dark:text-orange-400"
    >
        <WarningIcon class="mt-0.5 h-4 w-4 shrink-0" />
        <span>{{ warning }}</span>
    </p>

    <!--
        ທາງອອກໃຫ້ຜູ້ໃຊ້ເມື່ອອັດຕາຜິດປົກກະຕິແທ້ (ແລກເງິນຕາມຕະຫຼາດມືດ ຯລຯ).
        server ປະຕິເສດ rate ທີ່ຜ່ານ sanity check ບໍ່ໄດ້ ເວັ້ນແຕ່ສົ່ງ
        `confirm_unusual_rate` ມານຳ — ບໍ່ມີກ່ອງນີ້ ຜູ້ໃຊ້ຈະຕິດຢູ່ບັນທຶກບໍ່ໄດ້.
        ສະແດງສະເພາະເມື່ອ parent ຜູກ v-model:confirm-rate (ລາຍຮັບບໍ່ໄດ້ບັງຄັບ)
    -->
    <label
        v-if="confirmRate !== null && !isUsd && (warning || hasRateError)"
        class="mt-3 flex cursor-pointer items-center gap-2 text-theme-sm text-gray-700 dark:text-gray-300"
    >
        <input
            type="checkbox"
            class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700"
            :checked="confirmRate"
            @change="
                $emit(
                    'update:confirmRate',
                    ($event.target as HTMLInputElement).checked,
                )
            "
        />
        {{ t('tx.confirmUnusualRate') }}
    </label>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { api } from '@/api/client';
import type { ApiError } from '@/api/client';
import NumericField from '@/components/forms/NumericField.vue';
import SelectField from '@/components/settings/SelectField.vue';
import { trimTrailingZeros } from '@/utils/money';
import WarningIcon from '~icons/tabler/alert-triangle';

interface Currency {
    code: string;
    name_lo: string;
    decimal_places: number;
}

interface Props {
    amount: string;
    currency: string;
    /** ອັດຕາເປັນກີບ (`fx_rate_lak`): LAK = ກີບຕໍ່ 1 USD, ອື່ນ = ກີບຕໍ່ 1 ໜ່ວຍ */
    rate: string;
    /** ວັນທີຂອງລາຍການ — ໃຊ້ຫາອັດຕາອ້າງອີງຂອງມື້ນັ້ນ */
    date: string;
    currencies: Currency[];
    error?: ApiError | null;
    /** ຢູ່ໃນ FormModal ແບບ `wide` — ຈັດ 4 ຊ່ອງໃສ່ແຖວດຽວແທນສອງແຖວ */
    dense?: boolean;
    /**
     * ຜູ້ໃຊ້ຢືນຢັນອັດຕາຜິດປົກກະຕິ (`confirm_unusual_rate`).
     * null = parent ບໍ່ໄດ້ໃຊ້ → ບໍ່ສະແດງກ່ອງໝາຍ
     */
    confirmRate?: boolean | null;
}

const props = withDefaults(defineProps<Props>(), {
    error: null,
    dense: false,
    confirmRate: null,
});

const emit = defineEmits<{
    'update:amount': [value: string];
    'update:currency': [value: string];
    'update:rate': [value: string];
    'update:confirmRate': [value: boolean];
}>();

const { t } = useI18n();

/**
 * ອັດຕາແລກປ່ຽນເກັບເປັນ decimal(24,10) ແຕ່ຄົນບໍ່ໄດ້ພິມ 10 ຫຼັກ —
 * ຮັບໄດ້ 4 ຫຼັກກໍພຽງພໍກັບ ກີບຕໍ່ບາດ (675.48) ແລະ ກີບຕໍ່ USD (22239).
 */
const RATE_DECIMALS = 4;

const warning = ref<string | null>(null);
const referenceRate = ref<string | null>(null);

/**
 * ອັດຕາອ້າງອີງຂອງວັນນັ້ນເປັນກີບ — ໃຊ້ສະເພາະຕົວຢ່າງ ບໍ່ໄດ້ບັນທຶກ.
 * `lakPerUsd` = ກີບຕໍ່ 1 USD, `lakPerThb` = ກີບຕໍ່ 1 ບາດ
 */
const lakPerUsd = ref<string | null>(null);
const lakPerThb = ref<string | null>(null);

const isUsd = computed(() => props.currency === 'USD');

/** ຊ່ອງອັດຕາແມ່ນ "ກີບຕໍ່ 1 ຫຍັງ": ລາຍການກີບ → ຕໍ່ 1 USD */
const quoteCode = computed(() =>
    props.currency === 'LAK' ? 'USD' : props.currency,
);

/** ຫຼັກທົດສະນິຍົມຂອງສະກຸນທີ່ເລືອກ — LAK = 0, USD/THB = 2 */
const amountDecimals = computed(
    () =>
        props.currencies.find((c) => c.code === props.currency)
            ?.decimal_places ?? 2,
);

const currencyOptions = computed(() =>
    props.currencies.map((c) => ({
        value: c.code,
        label: `${c.code} — ${c.name_lo}`,
        translate: false,
    })),
);

const rateHint = computed(() =>
    referenceRate.value
        ? t('tx.fxRateHint', { rate: referenceRate.value })
        : t('tx.fxRateLakHint', { code: quoteCode.value }),
);

/** server ປະຕິເສດ rate (422 ຢູ່ຊ່ອງ fx_rate_lak) */
const hasRateError = computed(
    () =>
        !!props.error?.errors?.fx_rate_lak?.length ||
        !!props.error?.errors?.fx_rate_per_usd?.length,
);

/** ຊ່ອງມີເຄື່ອງໝາຍຈຸດຫຼັກພັນ — ລ້າງອອກກ່ອນຄິດ ຫຼື ສົ່ງໄປ server */
function stripSeparators(value: string): string {
    return value.replace(/[,\s]/g, '');
}

/** Number("21,760") = NaN ຈຶ່ງຕ້ອງລ້າງກ່ອນ */
function toNumber(value: string): number {
    return Number(stripSeparators(value));
}

/**
 * ຜູ້ໃຊ້ປ່ຽນສະກຸນເອງ → ອັດຕາເກົ່າເປັນຂອງສະກຸນອື່ນ ໃຊ້ຕໍ່ບໍ່ໄດ້.
 *
 * ເຄີຍເກີດ: ຟອມເລີ່ມທີ່ LAK ແລະ ເຕີມ 22239 ໃຫ້, ປ່ຽນເປັນ THB ແລ້ວ
 * 22239 ຄ້າງຢູ່ (lookupRate ເຕີມສະເພາະຊ່ອງຫວ່າງ) → 1,000 ບາດ = $0.04.
 * ລ້າງຊ່ອງຢູ່ນີ້ ແລ້ວ watcher ຂອງ currency ຈະເຕີມອັດຕາອ້າງອີງຂອງສະກຸນໃໝ່.
 *
 * ເຮັດຢູ່ event ຂອງ select ບໍ່ແມ່ນ watcher — openEdit ກໍປ່ຽນ currency
 * ພ້ອມ rate ທີ່ບັນທຶກໄວ້ ເຊິ່ງຕ້ອງບໍ່ຖືກລ້າງ.
 */
function onCurrencyChange(value: string): void {
    if (value === props.currency) {
        return;
    }

    emit('update:currency', value);
    emit('update:rate', '');
}

/** ຄ່າບວກທີ່ໃຊ້ໄດ້ ຫຼື null */
function positive(value: number): number | null {
    return Number.isFinite(value) && value > 0 ? value : null;
}

/**
 * ກີບຕໍ່ 1 USD ທີ່ໃຊ້ຄິດຕົວຢ່າງ: ລາຍການກີບ = ຄ່າທີ່ຜູ້ໃຊ້ພິມ,
 * ສະກຸນອື່ນ = ອັດຕາອ້າງອີງຂອງວັນນັ້ນ (ຄືກັບທີ່ server ໃຊ້ແປງ).
 */
const effectiveLakPerUsd = computed<number | null>(() =>
    props.currency === 'LAK'
        ? positive(toNumber(props.rate))
        : positive(lakPerUsd.value === null ? NaN : Number(lakPerUsd.value)),
);

/**
 * ຍອດເປັນກີບ — ຕົວກາງທີ່ຕົວຢ່າງອື່ນທັງໝົດຄິດຕໍ່ຈາກນີ້ ເພາະອັດຕາຕັ້ງເປັນກີບ.
 *
 * ຄິດຢູ່ browser ເພື່ອຄວາມໄວ — ຕົວເລກທີ່ **ບັນທຶກຈິງ** ຍັງຄິດຢູ່ server
 * ຜ່ານ bcmath ສະເໝີ (CurrencyConverter). ອັນນີ້ເປັນພຽງຕົວຊ່ວຍສາຍຕາ
 * ຈຶ່ງໃຊ້ float ໄດ້.
 */
const lakAmount = computed<number | null>(() => {
    const amount = toNumber(props.amount);

    if (!Number.isFinite(amount)) {
        return null;
    }

    if (props.currency === 'LAK') {
        return amount;
    }

    const lakPerUnit = isUsd.value
        ? effectiveLakPerUsd.value
        : positive(toNumber(props.rate));

    return lakPerUnit === null ? null : amount * lakPerUnit;
});

const usdAmount = computed<number | null>(() => {
    if (isUsd.value) {
        const amount = toNumber(props.amount);

        return Number.isFinite(amount) ? amount : null;
    }

    const rate = effectiveLakPerUsd.value;

    return lakAmount.value === null || rate === null
        ? null
        : lakAmount.value / rate;
});

function formatCurrency(value: number | null, code: string): string {
    if (value === null) {
        return '—';
    }

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: code,
        // ກີບບໍ່ມີຫົວໜ່ວຍຍ່ອຍໃຊ້ຈິງ (currencies.decimal_places = 0)
        ...(code === 'LAK' ? { maximumFractionDigits: 0 } : {}),
    }).format(value);
}

/** ຍອດເປັນບາດ = ກີບ ÷ (ກີບຕໍ່ 1 ບາດ) */
const thbAmount = computed<number | null>(() => {
    const rate = positive(
        lakPerThb.value === null ? NaN : Number(lakPerThb.value),
    );

    return lakAmount.value === null || rate === null
        ? null
        : lakAmount.value / rate;
});

/**
 * ຕົວຢ່າງທີ່ຈະສະແດງ — ບໍ່ສະແດງສະກຸນຂອງຕົນເອງ (ຊ້ຳກັບຊ່ອງຈຳນວນເງິນ).
 * ບາດຈະບໍ່ຂຶ້ນເລີຍຖ້າຫາອັດຕາບາດຂອງມື້ນັ້ນບໍ່ພົບ — ດີກວ່າສະແດງ "—" ຄ້າງໄວ້.
 */
const previews = computed(() => {
    const rows: { code: string; label: string; value: string }[] = [];

    if (!isUsd.value) {
        rows.push({
            code: 'USD',
            label: t('tx.usdPreview'),
            value: formatCurrency(usdAmount.value, 'USD'),
        });
    }

    if (props.currency !== 'LAK') {
        rows.push({
            code: 'LAK',
            label: t('tx.lakPreview'),
            value: formatCurrency(lakAmount.value, 'LAK'),
        });
    }

    if (props.currency !== 'THB' && lakPerThb.value !== null) {
        rows.push({
            code: 'THB',
            label: t('tx.thbPreview'),
            value: formatCurrency(thbAmount.value, 'THB'),
        });
    }

    return rows;
});

/**
 * ຫາອັດຕາອ້າງອີງຂອງສະກຸນໃດໜຶ່ງໃນວັນທີທີ່ກຳນົດ.
 *
 * ຄືນ null ເມື່ອຫາບໍ່ພົບ — ບໍ່ໂຍນ error: ບໍ່ມີອັດຕາຂອງມື້ນັ້ນຄືເລື່ອງປົກກະຕິ
 * ແລະ ຜູ້ໃຊ້ພິມເອງໄດ້ ຈຶ່ງບໍ່ຄວນຂວາງຟອມ.
 */
async function fetchRate(currency: string): Promise<string | null> {
    try {
        const { data } = await api.get<{ rate_lak?: string | null }>(
            '/exchange-rates/lookup',
            { params: { currency, date: props.date } },
        );

        // ອັດຕາເປັນກີບ: LAK/USD → ກີບຕໍ່ 1 USD, THB → ກີບຕໍ່ 1 ບາດ.
        // "21760.0000000000" ຈາກ decimal(24,10) — ຕັດສູນທ້າຍອອກກ່ອນ
        // ໃຫ້ຄົນເຫັນ ບໍ່ດັ່ງນັ້ນຊ່ອງຈະເຕັມໄປດ້ວຍສູນທີ່ບໍ່ມີຄວາມໝາຍ
        return data.rate_lak ? trimTrailingZeros(data.rate_lak) : null;
    } catch {
        return null;
    }
}

/** ດຶງອັດຕາອ້າງອີງຂອງວັນນັ້ນມາເຕີມໃຫ້ ເມື່ອຜູ້ໃຊ້ຍັງບໍ່ໄດ້ໃສ່ເອງ */
async function lookupRate(): Promise<void> {
    if (isUsd.value || !props.date) {
        referenceRate.value = null;

        return;
    }

    referenceRate.value = await fetchRate(props.currency);

    if (referenceRate.value && !props.rate) {
        emit('update:rate', referenceRate.value);
    }
}

/**
 * ອັດຕາອ້າງອີງສຳລັບຕົວຢ່າງ — ແຍກຈາກຊ່ອງອັດຕາ ເພາະຕົວຢ່າງ USD ຂອງລາຍການ
 * ບາດຕ້ອງການກີບຕໍ່ 1 USD ແລະ ຕົວຢ່າງບາດຂອງລາຍການກີບ/ໂດລາຕ້ອງການກີບຕໍ່ 1 ບາດ.
 */
async function lookupPreviewRates(): Promise<void> {
    if (!props.date) {
        lakPerUsd.value = null;
        lakPerThb.value = null;

        return;
    }

    [lakPerUsd.value, lakPerThb.value] = await Promise.all([
        fetchRate('LAK'),
        // ລາຍການບາດບໍ່ສະແດງຕົວຢ່າງບາດ (ຊ້ຳກັບຊ່ອງຈຳນວນເງິນ)
        props.currency === 'THB' ? Promise.resolve(null) : fetchRate('THB'),
    ]);
}

/** ຖາມ server ວ່າອັດຕາທີ່ໃສ່ສົມເຫດສົມຜົນບໍ — guard rail ຂອງພາກ 3.2 */
async function checkRate(): Promise<void> {
    warning.value = null;

    if (isUsd.value || !props.rate || !props.date) {
        return;
    }

    try {
        const { data } = await api.post<{
            ok: boolean;
            message: string | null;
        }>('/exchange-rates/check', {
            currency: props.currency,
            // "22,239" ບໍ່ຜ່ານ regex ຂອງ server → 422 → ຄຳເຕືອນບໍ່ເຄີຍຂຶ້ນ
            rate: stripSeparators(props.rate),
            date: props.date,
            // ຂອບເຂດ ແລະ ຂໍ້ຄວາມເປັນກີບ (ເບິ່ງ sanityCheckLakQuote)
            quote: 'lak',
        });

        warning.value = data.ok ? null : data.message;
    } catch {
        warning.value = null;
    }
}

watch(
    () => [props.currency, props.date],
    () => {
        // ຂະໜານກັນ — ອັນໜຶ່ງບໍ່ໄດ້ຂຶ້ນກັບຜົນຂອງອີກອັນ
        void Promise.all([lookupRate(), lookupPreviewRates()]);
    },
    { immediate: true },
);

watch(() => [props.rate, props.currency, props.date], checkRate);

// ການຢືນຢັນໃຊ້ໄດ້ກັບອັດຕາທີ່ເຫັນຕອນໝາຍເທົ່ານັ້ນ — ປ່ຽນຕົວເລກແລ້ວຕ້ອງຢືນຢັນໃໝ່
watch(
    () => [props.rate, props.currency],
    () => {
        if (props.confirmRate) {
            emit('update:confirmRate', false);
        }
    },
);
</script>
