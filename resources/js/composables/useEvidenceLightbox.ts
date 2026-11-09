import { computed, ref } from 'vue';
import type { Ref } from 'vue';
import { api, toApiError } from '@/api/client';
import type { Attachment } from '@/types/transactions';

/**
 * ສະຖານະຂອງ lightbox ສຳລັບຮູບຫຼັກຖານ.
 *
 * ⚠️ ເຫດຜົນທີ່ບໍ່ໄດ້ເປັນພຽງ `visible = true` ງ່າຍໆ:
 * `AttachmentResource::FULL_TTL` = 300 ວິນາທີ. URL ຮູບເຕັມທີ່ຕິດມາກັບ
 * ຕາຕະລາງຈຶ່ງຕາຍພາຍໃນ 5 ນາທີ — ຖ້າຜູ້ໃຊ້ເປີດໜ້າຕ່າງຄ້າງໄວ້ແລ້ວຄ່ອຍກົດຮູບ
 * lightbox ຈະສະແດງຮູບແຕກ (403). ຈຶ່ງດຶງລາຍການໃໝ່ກ່ອນເປີດ ຖ້າ URL ເກົ່າເກີນໄປ.
 *
 * (thumbnail ມີ TTL 1800 ວິນາທີ ຈຶ່ງຍັງເຫັນຢູ່ໃນ grid — ນັ້ນຄືເຫດຜົນທີ່
 * ອາການນີ້ຈະເບິ່ງຄື "ຮູບນ້ອຍດີ ແຕ່ກົດແລ້ວແຕກ" ຊຶ່ງຫາສາເຫດຍາກ)
 */
const REFRESH_URLS_AFTER_MS = 240_000; // 4 ນາທີ — ເຜື່ອໄວ້ກ່ອນ TTL 5 ນາທີ

export function useEvidenceLightbox(
    attachments: Ref<Attachment[]>,
    refresh: () => Promise<void>,
) {
    const visible = ref(false);
    const index = ref(0);
    const loadedAt = ref(0);

    /** lightbox ສະແດງໄດ້ແຕ່ຮູບ — PDF ຕ້ອງເປີດແທັບໃໝ່ */
    const images = computed(() => attachments.value.filter((a) => a.is_image));

    const sources = computed(() =>
        images.value.map((a) => ({ src: a.url, title: a.original_name })),
    );

    /** ບອກວ່າ URL ຊຸດປັດຈຸບັນຖືກດຶງມາເມື່ອໃດ — ເອີ້ນຫຼັງ fetch ສຳເລັດ */
    function markLoaded(): void {
        loadedAt.value = Date.now();
    }

    async function open(file: Attachment): Promise<void> {
        if (Date.now() - loadedAt.value > REFRESH_URLS_AFTER_MS) {
            await refresh();
        }

        const position = images.value.findIndex((a) => a.uuid === file.uuid);

        // ດຶງໃໝ່ແລ້ວອາດບໍ່ພົບ (ຄົນອື່ນລຶບໄປ) — ຢ່າເປີດໜ້າຕ່າງເປົ່າ
        if (position === -1) {
            return;
        }

        index.value = position;
        visible.value = true;
    }

    function close(): void {
        visible.value = false;
    }

    return { visible, index, images, sources, open, close, markLoaded };
}

/**
 * ຮຸ່ນທີ່ດຶງຫຼັກຖານມາເອງ — ໃຊ້ໃນຕາຕະລາງ ບ່ອນທີ່ຍັງບໍ່ມີລາຍການຫຼັກຖານໃນມື.
 *
 * ກົດປ້າຍຈຳນວນຫຼັກຖານແລ້ວເຫັນ **ຮູບເລີຍ** ບໍ່ຜ່ານໜ້າຕ່າງລາຍລະອຽດ —
 * ລາຍລະອຽດມີປຸ່ມຮູບຕາຂອງມັນເອງຢູ່ແລ້ວ.
 */
export function useEvidenceViewer(resource: 'expenses' | 'revenues') {
    const attachments = ref<Attachment[]>([]);
    const currentUuid = ref<string | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);

    const lightbox = useEvidenceLightbox(attachments, async () => {
        if (currentUuid.value) {
            await fetchFor(currentUuid.value);
        }
    });

    async function fetchFor(uuid: string): Promise<void> {
        loading.value = true;
        error.value = null;

        try {
            const { data } = await api.get<{ data: Attachment[] }>(
                `/${resource}/${uuid}/attachments`,
            );
            attachments.value = data.data;
            lightbox.markLoaded();
        } catch (e) {
            error.value = toApiError(e).message;
            attachments.value = [];
        } finally {
            loading.value = false;
        }
    }

    async function openFor(uuid: string): Promise<void> {
        currentUuid.value = uuid;
        await fetchFor(uuid);

        const firstImage = attachments.value.find((a) => a.is_image);

        if (firstImage) {
            await lightbox.open(firstImage);

            return;
        }

        // ມີແຕ່ PDF — lightbox ສະແດງບໍ່ໄດ້ ຈຶ່ງເປີດແທັບໃໝ່ໃຫ້ແທນ
        const firstFile = attachments.value[0];

        if (firstFile) {
            window.open(firstFile.url, '_blank', 'noopener,noreferrer');
        }
    }

    return { ...lightbox, openFor, loading, error, attachments };
}
