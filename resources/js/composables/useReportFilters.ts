import { computed, ref } from 'vue';

export type StatusMode = 'approved' | 'all';

/**
 * ສະຖານະຕົວກັ່ນຕອງທີ່ໜ້າລາຍງານທັງສາມໃຊ້ຮ່ວມກັນ.
 *
 * `buildParams()` ໃຊ້ໂຫຼດຂໍ້ມູນລາຍງານ. **ບໍ່** ໃຊ້ປະກອບ URL ຂອງ PDF —
 * ອັນນັ້ນ server ເປັນຄົນເຊັນແລ້ວສົ່ງມາໃນ `pdf_url` ຂອງຄຳຕອບ (ເບິ່ງ ReportPdfLink):
 * ນອກຈາກຈຳເປັນທາງເຕັກນິກ ຍັງໝາຍວ່າໃບທີ່ພິມມາຈາກຕົວກັ່ນຕອງຊຸດດຽວກັນກັບ
 * ຕົວເລກທີ່ເຫັນແທ້ໆ ບໍ່ແມ່ນສອງຊຸດທີ່ຂຽນໃຫ້ຄືກັນໄວ້.
 *
 * `categoryId` ມີສະເພາະລາຍງານລາຍຈ່າຍ — ລາຍຮັບ ແລະ ບັນຊີແຍກປະເພດປະຫວ່າງໄວ້
 * ແລ້ວມັນຈະບໍ່ຖືກໃສ່ໃນ query string ເລີຍ.
 */
export function useReportFilters() {
    const search = ref('');
    const payerId = ref<number | ''>('');
    const categoryId = ref<number | ''>('');
    const dateFrom = ref('');
    const dateTo = ref('');
    const statusMode = ref<StatusMode>('approved');

    const hasFilters = computed(
        () =>
            search.value !== '' ||
            payerId.value !== '' ||
            categoryId.value !== '' ||
            dateFrom.value !== '' ||
            dateTo.value !== '' ||
            statusMode.value !== 'approved',
    );

    function buildParams(): Record<string, string> {
        const params: Record<string, string> = {};

        // ຄຳຄົ້ນຫາທີ່ມີແຕ່ຊ່ອງຫວ່າງບໍ່ຖືວ່າຄົ້ນຫາ — server ກໍ trim ຄືກັນ
        if (search.value.trim() !== '') {
            params.search = search.value.trim();
        }

        if (payerId.value !== '') {
            params.payer_id = String(payerId.value);
        }

        if (categoryId.value !== '') {
            params.category_id = String(categoryId.value);
        }

        if (dateFrom.value) {
            params.date_from = dateFrom.value;
        }

        if (dateTo.value) {
            params.date_to = dateTo.value;
        }

        if (statusMode.value === 'all') {
            params.statuses = 'all';
        }

        return params;
    }

    function reset(): void {
        search.value = '';
        payerId.value = '';
        categoryId.value = '';
        dateFrom.value = '';
        dateTo.value = '';
        statusMode.value = 'approved';
    }

    return {
        search,
        payerId,
        categoryId,
        dateFrom,
        dateTo,
        statusMode,
        hasFilters,
        buildParams,
        reset,
    };
}
