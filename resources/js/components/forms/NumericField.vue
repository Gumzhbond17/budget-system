<template>
    <FormField
        v-slot="{ id, invalid }"
        :label="label"
        :name="name"
        :error="error"
        :hint="hint"
        :required="required"
    >
        <input
            :id="id"
            ref="input"
            :value="modelValue"
            type="text"
            inputmode="decimal"
            autocomplete="off"
            :placeholder="placeholder"
            :disabled="disabled"
            :class="[
                'h-11 w-full rounded-lg border bg-transparent px-4 py-2.5 text-sm text-gray-800 tabular-nums shadow-theme-xs placeholder:text-gray-400 focus:ring-3 focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-50 dark:text-white/90 dark:placeholder:text-white/30 dark:disabled:bg-white/5',
                invalid
                    ? 'border-error-500 focus:border-error-300 focus:ring-error-500/10 dark:border-error-500'
                    : 'border-gray-300 focus:border-brand-300 focus:ring-brand-500/10 dark:border-gray-700 dark:focus:border-brand-800',
            ]"
            @input="onInput"
        />
    </FormField>
</template>

<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import type { ApiError } from '@/api/client';
import FormField from '@/components/settings/FormField.vue';
import {
    caretAfter,
    countSignificant,
    groupNumeric,
    trimTrailingZeros,
} from '@/utils/money';

/**
 * ຊ່ອງປ້ອນຕົວເລກທີ່ຈັດກຸ່ມຫຼັກພັນໃຫ້ຂະນະພິມ.
 *
 * ຄ່າທີ່ຜູກກັບ v-model ຄື **ຂໍ້ຄວາມທີ່ຈັດຮູບແບບແລ້ວ** ("1,200,000") ບໍ່ແມ່ນ
 * ຕົວເລກດິບ. ສົ່ງໄປ API ໄດ້ເລີຍ — `ExpenseRequest::prepareForValidation()`
 * ລ້າງເຄື່ອງໝາຍຈຸດອອກກ່ອນກວດ ແລະ `Money::fromDecimal()` ກໍລ້າງອີກຊັ້ນ.
 */
interface Props {
    label: string;
    /** ຊື່ field ຢູ່ຝັ່ງ server — ໃຊ້ຫາຂໍ້ຄວາມ 422 */
    name: string;
    modelValue: string;
    /** ຫຼັກທົດສະນິຍົມສູງສຸດ — LAK = 0, USD/THB = 2, ອັດຕາແລກປ່ຽນ = 4 */
    decimals: number;
    /**
     * ຕັດສູນທ້າຍອອກຕອນຮັບຄ່າຈາກ server.
     * ເປີດໃຫ້ອັດຕາແລກປ່ຽນ ("21760.0000000000" → "21,760") ແຕ່ **ບໍ່ແມ່ນ**
     * ຈຳນວນເງິນ — USD 12.50 ຕັດແລ້ວກາຍເປັນ 12.5 ເຊິ່ງເບິ່ງຄືຕົກຫຼັກ.
     */
    trimZeros?: boolean;
    placeholder?: string;
    hint?: string;
    error?: ApiError | null;
    required?: boolean;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    error: null,
    required: false,
    disabled: false,
    trimZeros: false,
});

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const input = ref<HTMLInputElement | null>(null);

function onInput(event: Event): void {
    const element = event.target as HTMLInputElement;
    const caret = element.selectionStart ?? element.value.length;
    const significant = countSignificant(element.value, caret);
    const formatted = groupNumeric(element.value, props.decimals);

    emit('update:modelValue', formatted);

    // ຂຽນຄືນເອງ: ຖ້າ formatted ຄືກັບຄ່າ prop ເດີມ Vue ຈະບໍ່ patch DOM
    // ແລ້ວຕົວອັກສອນທີ່ບໍ່ຖືກຕ້ອງທີ່ຜູ້ໃຊ້ພິມຈະຄ້າງຢູ່ໃນຊ່ອງ
    void nextTick(() => {
        element.value = formatted;

        const position = caretAfter(formatted, significant);
        element.setSelectionRange(position, position);
    });
}

/**
 * ຈັດຮູບແບບຄ່າທີ່ມາຈາກພາຍນອກ.
 *
 * `immediate` ຈັດການຄ່າຕັ້ງຕົ້ນຕອນເປີດຟອມແກ້ໄຂ — ຄ່າຈາກ API ມາເປັນຕົວເລກ
 * ດິບ ("1200000") ຈຶ່ງຕ້ອງຈັດກຸ່ມໃຫ້ ບໍ່ດັ່ງນັ້ນຈະຈັດຮູບແບບສະເພາະຕອນພິມ.
 *
 * ການປ່ຽນສະກຸນເງິນ (LAK 0 ຫຼັກ → USD 2 ຫຼັກ) ກໍຕ້ອງຈັດຄືນເໝືອນກັນ.
 */
watch(
    () => [props.decimals, props.modelValue],
    () => {
        const source = props.trimZeros
            ? trimTrailingZeros(props.modelValue)
            : props.modelValue;
        const reformatted = groupNumeric(source, props.decimals);

        if (reformatted !== props.modelValue) {
            emit('update:modelValue', reformatted);
        }
    },
    { immediate: true },
);
</script>
