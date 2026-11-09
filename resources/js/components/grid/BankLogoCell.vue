<template>
    <template v-if="bank">
        <!-- ກົດເບິ່ງເຕັມຈໍໄດ້ສະເພາະແຖວທີ່ມີຮູບຈິງ —
             ວົງມົນສຳຮອງ (ລະຫັດ) ບໍ່ມີຫຍັງໃຫ້ຂະຫຍາຍ -->
        <button
            v-if="bank.logo_url"
            type="button"
            class="rounded-full transition hover:ring-2 hover:ring-brand-500/40 focus:ring-2 focus:ring-brand-500/40 focus:outline-none"
            :aria-label="t('settings.banks.viewLogo', { name: bank.name_lo })"
            @click="params.onView?.(bank)"
        >
            <BankLogo :bank="bank" />
        </button>

        <BankLogo v-else :bank="bank" />
    </template>
</template>

<script setup lang="ts">
import type { ICellRendererParams } from 'ag-grid-community';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import BankLogo from '@/components/common/BankLogo.vue';
import type { Bank } from '@/types/settings';

/** ໂລໂກ້ທະນາຄານ — ກົດເພື່ອເປີດເບິ່ງເຕັມຈໍ */
interface BankLogoCellParams extends ICellRendererParams<Bank> {
    onView?: (bank: Bank) => void;
}

const props = defineProps<{ params: BankLogoCellParams }>();

const { t } = useI18n();

const bank = computed(() => props.params.data ?? null);
</script>
