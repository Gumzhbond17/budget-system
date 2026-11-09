<template>
    <input
        ref="input"
        :value="initial"
        type="text"
        inputmode="decimal"
        autocomplete="off"
        class="h-full w-full bg-transparent px-3 text-right text-sm tabular-nums outline-none"
        @input="onInput"
    />
</template>

<script setup lang="ts">
import type { ICellEditorParams } from 'ag-grid-community';
import { ref } from 'vue';
import type { Money } from '@/types/settings';
import { caretAfter, countSignificant, groupNumeric } from '@/utils/money';

/**
 * ຕົວແກ້ໄຂຈຳນວນເງິນໃນຕາຕະລາງ — ຈັດກຸ່ມຫຼັກພັນຂະນະພິມຄືກັບ NumericField.
 *
 * ຖັນເງິນຄືນ minor unit ເປັນ `value` (ເພື່ອໃຫ້ຮຽງ/ກັ່ນຕອງເປັນຕົວເລກ) ເຊິ່ງ
 * ບໍ່ແມ່ນສິ່ງທີ່ຄົນຄວນເຫັນຕອນແກ້ — ຈຶ່ງອ່ານ Money ຂອງແຖວຜ່ານ `money()` ແທນ.
 *
 * `getValue()` ຄືນ **ຂໍ້ຄວາມທົດສະນິຍົມ** ("40,000,000") ບໍ່ແມ່ນ Number —
 * server ລ້າງເຄື່ອງໝາຍຈຸດ ແລ້ວແປງຜ່ານ `Money::fromDecimal()` ເອງ.
 */
interface MoneyCellEditorParams extends ICellEditorParams {
    money: (data: unknown) => Money | null;
}

const props = defineProps<{ params: MoneyCellEditorParams }>();

const input = ref<HTMLInputElement | null>(null);

const money = props.params.money(props.params.data);
const decimals = money?.decimals ?? 2;

/**
 * ເລີ່ມແກ້ດ້ວຍການພິມຕົວເລກ = ແທນຄ່າເກົ່າດ້ວຍຕົວນັ້ນ (ຄື Excel);
 * ເລີ່ມດ້ວຍ Backspace/Delete = ລ້າງຊ່ອງ; ດັບເບີນຄລິກ = ຄ່າເດີມ.
 */
function initialValue(): string {
    const key = props.params.eventKey;

    if (key === 'Backspace' || key === 'Delete') {
        return '';
    }

    if (key && key.length === 1) {
        return groupNumeric(key, decimals);
    }

    return money && money.minor !== 0
        ? groupNumeric(money.decimal, decimals)
        : '';
}

const initial = initialValue();
let current = initial;

function onInput(event: Event): void {
    const element = event.target as HTMLInputElement;
    const caret = element.selectionStart ?? element.value.length;
    const significant = countSignificant(element.value, caret);

    current = groupNumeric(element.value, decimals);
    element.value = current;

    const position = caretAfter(current, significant);
    element.setSelectionRange(position, position);
}

function getValue(): string {
    return current;
}

function afterGuiAttached(): void {
    input.value?.focus();

    // ດັບເບີນຄລິກເຂົ້າມາ = ເລືອກທັງໝົດ ພິມທັບໄດ້ເລີຍ; ເລີ່ມດ້ວຍການພິມ =
    // cursor ຢູ່ທ້າຍ ບໍ່ດັ່ງນັ້ນຕົວຕໍ່ໄປຈະທັບຕົວທຳອິດທີ່ຫາກໍ່ພິມ
    if (props.params.eventKey) {
        const end = current.length;
        input.value?.setSelectionRange(end, end);
    } else {
        input.value?.select();
    }
}

defineExpose({ getValue, afterGuiAttached });
</script>
