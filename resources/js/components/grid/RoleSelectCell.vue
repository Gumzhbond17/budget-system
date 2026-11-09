<template>
    <SearchableSelect
        :model-value="String(params.value ?? '')"
        :aria-label="t('settings.members.role')"
        :options="roleOptions"
        class="h-9! py-0!"
        @update:model-value="onChange"
    />
</template>

<script setup lang="ts">
import type { ICellRendererParams } from 'ag-grid-community';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import SearchableSelect from '@/components/common/SearchableSelect.vue';
import type { ProjectMember, ProjectRole } from '@/types/settings';

/**
 * ປ່ຽນລະດັບສິດຂອງສະມາຊິກໄດ້ຈາກໃນຕາຕະລາງເລີຍ.
 *
 * ບໍ່ແມ່ນ cell editor ຂອງ AG Grid: ການປ່ຽນຢູ່ນີ້ຍິງ request ທັນທີ
 * ບໍ່ມີຂັ້ນຕອນ "ກຳລັງແກ້ໄຂ → ຢືນຢັນ" ໃຫ້ຕ້ອງຈັດການ.
 *
 * SearchableSelect ເປີດລາຍການຜ່ານ Teleport ໄປ body — ຈຳເປັນຢູ່ນີ້ ເພາະ
 * ແຖວຂອງ grid ເປັນຂອບຕັດ, dropdown ທີ່ຢູ່ພາຍໃນຈະຖືກຕັດເຫຼືອຄວາມສູງແຖວ.
 */
interface RoleSelectParams extends ICellRendererParams<ProjectMember> {
    roles: readonly ProjectRole[];
    onChange: (member: ProjectMember, role: ProjectRole) => void;
}

const props = defineProps<{ params: RoleSelectParams }>();

const { t } = useI18n();

const roleOptions = computed(() =>
    props.params.roles.map((role) => ({
        value: role,
        label: `settings.role.${role}`,
    })),
);

function onChange(role: string): void {
    const member = props.params.data;

    if (member) {
        props.params.onChange(member, role as ProjectRole);
    }
}
</script>
