<template>
    <!--
        ບໍ່ມີສິດເຮັດຫຍັງກັບແຖວນີ້ = ບໍ່ສະແດງຫຍັງ (ບໍ່ແມ່ນປຸ່ມສີຈາງທີ່ກົດບໍ່ໄດ້).
        ຜູ້ອ່ານຢ່າງດຽວ ແລະ ຜູ້ບັນທຶກທີ່ເບິ່ງລາຍການຂອງຕົນເອງລໍຖ້າອະນຸມັດ
        ຈະຕົກຢູ່ກໍລະນີນີ້ — ປຸ່ມທີ່ກົດບໍ່ໄດ້ບອກແຕ່ວ່າ "ບໍ່ແມ່ນເລື່ອງຂອງເຈົ້າ"
        ຊ້ຳໆທຸກແຖວ ໂດຍບໍ່ໃຫ້ຂໍ້ມູນຫຍັງເພີ່ມ.
    -->
    <template
        v-if="
            entry.can.submit ||
            entry.can.approve ||
            entry.can.reject ||
            entry.can.reopen
        "
    >
        <!-- ຮ່າງ / ປະຕິເສດ → ສົ່ງອະນຸມັດ -->
        <button
            v-if="entry.can.submit"
            type="button"
            class="workflow-action bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600 focus-visible:ring-brand-500/20"
            :disabled="busy"
            :title="t('tx.workflow.submitHint')"
            @click="$emit('submit', entry)"
        >
            <SendIcon class="h-4 w-4" />
            {{ t('tx.workflow.submit') }}
        </button>

        <!--
            ສົ່ງແລ້ວ → ອະນຸມັດ / ປະຕິເສດ (ເຫັນສະເພາະຜູ້ມີສິດ ແລະ ບໍ່ແມ່ນຜູ້ສ້າງ).
            ອະນຸມັດ = ປຸ່ມທຶບ (ການກະທຳຫຼັກ), ປະຕິເສດ = ພື້ນອ່ອນ — ສອງປຸ່ມທຶບ
            ສີສົດຕິດກັນແຍ່ງຄວາມສົນໃຈກັນ ແລະ ກົດຜິດງ່າຍ
        -->
        <button
            v-if="entry.can.approve"
            type="button"
            class="workflow-action bg-success-500 text-white shadow-theme-xs hover:bg-success-600 focus-visible:ring-success-500/20"
            :disabled="busy"
            :title="t('tx.workflow.approveHint')"
            @click="$emit('approve', entry)"
        >
            <CheckIcon class="h-4 w-4" />
            {{ t('tx.workflow.approve') }}
        </button>

        <button
            v-if="entry.can.reject"
            type="button"
            class="workflow-action bg-error-50 text-error-600 hover:bg-error-100 focus-visible:ring-error-500/20 dark:bg-error-500/15 dark:text-error-400 dark:hover:bg-error-500/25"
            :disabled="busy"
            :title="t('tx.workflow.rejectHint')"
            @click="$emit('reject', entry)"
        >
            <XIcon class="h-4 w-4" />
            {{ t('tx.workflow.reject') }}
        </button>

        <!-- ອະນຸມັດແລ້ວ → ສົ່ງກັບໃຫ້ຜູ້ບັນທຶກແກ້ (ຜູ້ດູແລລະບົບເທົ່ານັ້ນ) -->
        <button
            v-if="entry.can.reopen"
            type="button"
            class="workflow-action bg-warning-50 text-warning-700 hover:bg-warning-100 focus-visible:ring-warning-500/20 dark:bg-warning-500/15 dark:text-orange-400 dark:hover:bg-warning-500/25"
            :disabled="busy"
            :title="t('tx.workflow.reopenHint')"
            @click="$emit('reopen', entry)"
        >
            <ReopenIcon class="h-4 w-4" />
            {{ t('tx.workflow.reopen') }}
        </button>
    </template>
</template>

<script setup lang="ts" generic="T extends Expense | Revenue">
import { useI18n } from 'vue-i18n';
import type { Expense, Revenue } from '@/types/transactions';
import ReopenIcon from '~icons/tabler/arrow-back-up';
import CheckIcon from '~icons/tabler/check';
import SendIcon from '~icons/tabler/send';
import XIcon from '~icons/tabler/x';

/**
 * ປຸ່ມຂັ້ນຕອນອະນຸມັດຂອງ 1 ແຖວ.
 *
 * ປຸ່ມໃດຂຶ້ນ ຕັດສິນຈາກ `entry.can` ທີ່ Policy ຄຳນວນມາໃຫ້ຝັ່ງ server ເທົ່ານັ້ນ
 * (ExpensePolicy / RevenuePolicy ຜ່ານ trait ExposesWorkflowPermissions).
 * ຢູ່ນີ້ບໍ່ມີການອ່ານ `status` ຫຼື role ມາຄິດຄູ່ຂະໜານ — ຖ້າຄິດຊ້ຳ ມື້ໃດມື້ໜຶ່ງ
 * ສອງບ່ອນຈະບໍ່ກົງກັນ ແລ້ວປຸ່ມທີ່ເຫັນຈະກົດແລ້ວໄດ້ 403.
 *
 * generic ຕໍ່ `T` ບໍ່ແມ່ນຮັບເປັນ `Expense | Revenue`: ຜູ້ຮັບ event ຢູ່ໜ້າ
 * ລາຍຈ່າຍຮັບ `Expense` ຢ່າງດຽວ ຖ້າ emit ເປັນ union TypeScript ຈະປະຕິເສດ
 * (ຟັງຊັນຮັບ union ບໍ່ໄດ້ຖ້າຮັບແຕ່ຂາໜຶ່ງ).
 */
interface Props {
    entry: T;
    /** ມີ request ຂອງແຖວນີ້ຄ້າງຢູ່ — ປິດປຸ່ມກັນກົດຊ້ຳ */
    busy?: boolean;
}

withDefaults(defineProps<Props>(), { busy: false });

defineEmits<{
    submit: [entry: T];
    approve: [entry: T];
    reject: [entry: T];
    reopen: [entry: T];
}>();

const { t } = useI18n();
</script>
