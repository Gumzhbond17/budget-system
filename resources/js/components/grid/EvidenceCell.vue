<template>
    <!-- ບໍ່ມີຫຼັກຖານ = ຄວາມສ່ຽງຕອນກວດສອບ ຈຶ່ງເຕືອນເປັນສີ.
         ກົດແລ້ວເປີດ **ຮູບ** ເລີຍ — ລາຍລະອຽດຢູ່ປຸ່ມຮູບຕາ -->
    <button
        type="button"
        class="rounded-full transition hover:opacity-80 focus:ring-3 focus:ring-brand-500/20 focus:outline-none disabled:cursor-default disabled:opacity-100"
        :disabled="count === 0"
        :title="count === 0 ? t('tx.noEvidence') : t('tx.viewEvidence')"
        @click="onView"
    >
        <Badge size="sm" :color="count > 0 ? 'success' : 'warning'">
            {{ count }}
        </Badge>
    </button>
</template>

<script setup lang="ts">
import type { ICellRendererParams } from 'ag-grid-community';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Badge from '@/components/ui/Badge.vue';

/** ຈຳນວນຫຼັກຖານແນບ — ກົດເພື່ອເປີດຮູບ */
interface EvidenceCellParams extends ICellRendererParams {
    onView: (uuid: string) => void;
    uuid: (params: ICellRendererParams) => string | undefined;
}

const props = defineProps<{ params: EvidenceCellParams }>();

const { t } = useI18n();

const count = computed(() => Number(props.params.value ?? 0));

function onView(): void {
    const uuid = props.params.uuid(props.params);

    if (uuid) {
        props.params.onView(uuid);
    }
}
</script>
