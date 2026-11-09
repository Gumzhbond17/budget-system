<template>
    <FormField
        v-slot="{ id, invalid }"
        :label="label"
        :name="name"
        :error="error"
        :hint="hint"
        :required="required"
    >
        <SearchableSelect
            :id="id"
            :model-value="modelValue"
            :options="options"
            :placeholder="placeholder"
            :nullable="nullable"
            :null-label="nullLabel"
            :disabled="disabled"
            :invalid="invalid"
            @update:model-value="$emit('update:modelValue', $event)"
        />
    </FormField>
</template>

<script setup lang="ts">
import type { ApiError } from '@/api/client';
import SearchableSelect from '@/components/common/SearchableSelect.vue';
import type { SearchableOption } from '@/components/common/SearchableSelect.vue';
import FormField from './FormField.vue';

/**
 * ຊ່ອງເລືອກໃນຟອມ.
 *
 * ຂ້າງໃນບໍ່ແມ່ນ `<select>` ພື້ນເມືອງອີກຕໍ່ໄປ — ເປັນ SearchableSelect ທີ່ມີ
 * ຊ່ອງຄົ້ນຫາ (ເບິ່ງເຫດຜົນຢູ່ບ່ອນນັ້ນ). API ຂອງ component ນີ້ບໍ່ປ່ຽນ
 * ຈຶ່ງບໍ່ຕ້ອງແກ້ບ່ອນທີ່ເອີ້ນໃຊ້.
 */
export type SelectOption = SearchableOption;

interface Props {
    label: string;
    name: string;
    modelValue: string | null;
    options: SelectOption[];
    placeholder?: string;
    /** ອະນຸຍາດໃຫ້ບໍ່ເລືອກ (ຄ່າຫວ່າງ) — ໃຊ້ກັບ "ອົງກອນແມ່" ທີ່ອາດບໍ່ມີ */
    nullable?: boolean;
    nullLabel?: string;
    hint?: string;
    error?: ApiError | null;
    required?: boolean;
    disabled?: boolean;
}

withDefaults(defineProps<Props>(), {
    placeholder: undefined,
    nullLabel: undefined,
    hint: undefined,
    error: null,
    nullable: false,
    required: false,
    disabled: false,
});

defineEmits<{ 'update:modelValue': [value: string] }>();
</script>
