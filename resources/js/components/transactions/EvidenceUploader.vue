<template>
    <div>
        <div class="mb-3 flex items-end justify-between gap-4">
            <div>
                <span
                    class="block text-sm font-medium text-gray-700 dark:text-gray-400"
                >
                    {{ t('tx.evidence') }}
                </span>
                <p class="text-theme-xs text-gray-500 dark:text-gray-400">
                    {{ t('tx.evidenceKindHint') }}
                </p>
            </div>

            <span
                v-if="total > 0"
                class="shrink-0 rounded-full bg-gray-100 px-2.5 py-0.5 text-theme-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300"
            >
                {{ total }}
            </span>
        </div>

        <!-- ---------- ປະເພດຫຼັກຖານ ---------- -->
        <!--
            ເປັນປຸ່ມແທນ <select>: ປະເພດຕ້ອງເລືອກ **ກ່ອນ** ວາງໄຟລ໌ ຍ້ອນມັນຖືກ
            ສົ່ງໄປພ້ອມກັບ upload. ຖ້າເຊື່ອງໄວ້ໃນ dropdown ຜູ້ໃຊ້ຈະລືມປ່ຽນ
            ແລ້ວໃບເກັບເງິນທັງໝົດຈະຖືກຕິດປ້າຍວ່າ "ໃບໂອນເງິນ".
        -->
        <div v-if="!disabled" class="mb-3 flex flex-wrap gap-2">
            <button
                v-for="k in KINDS"
                :key="k"
                type="button"
                class="rounded-full border px-3 py-1.5 text-theme-xs font-medium transition"
                :class="
                    kind === k
                        ? 'border-brand-500 bg-brand-500 text-white'
                        : 'border-gray-300 text-gray-600 hover:border-brand-300 hover:text-brand-500 dark:border-gray-700 dark:text-gray-400'
                "
                :aria-pressed="kind === k"
                @click="kind = k"
            >
                {{ t(`tx.evidenceKinds.${k}`) }}
            </button>
        </div>

        <!-- ---------- ບ່ອນວາງໄຟລ໌ ---------- -->
        <!--
            ທັງກອບຄື <label> ບໍ່ແມ່ນສະເພາະຄຳວ່າ "ເລືອກໄຟລ໌" — ກົດບ່ອນໃດກໍໄດ້
            ໃນກອບເສັ້ນປະແລ້ວເປີດຕົວເລືອກໄຟລ໌. ຄົນເລັງໃສ່ກອບໃຫຍ່ ບໍ່ແມ່ນລີ້ງນ້ອຍ.

            input ຢູ່**ນອກ** label: ຖ້າຢູ່ໃນ ບາງ browser ຈະນັບຄລິກສອງເທື່ອ
            (ຄລິກເທິງ label + ຄລິກທີ່ສົ່ງຕໍ່ໃຫ້ input) ແລ້ວເປີດໜ້າຕ່າງສອງຮອບ.

            `sr-only` ແທນ `hidden`: `display:none` ເຮັດໃຫ້ໂຟກັດດ້ວຍແປ້ນພິມບໍ່ໄດ້
            ຈົນຄົນທີ່ໃຊ້ແປ້ນພິມຢ່າງດຽວແນບຫຼັກຖານບໍ່ໄດ້ເລີຍ.
        -->
        <template v-if="!disabled">
            <input
                :id="inputId"
                ref="fileInput"
                type="file"
                multiple
                accept="image/jpeg,image/png,image/webp,application/pdf"
                class="sr-only"
                @change="onSelect"
            />

            <label
                :for="inputId"
                class="flex cursor-pointer flex-col items-center rounded-xl border-2 border-dashed p-6 text-center transition"
                :class="
                    dragging
                        ? 'border-brand-400 bg-brand-50 dark:bg-brand-500/10'
                        : 'border-gray-300 hover:border-brand-300 dark:border-gray-700 dark:hover:border-brand-500/50'
                "
                @dragover.prevent="dragging = true"
                @dragleave.prevent="dragging = false"
                @drop.prevent="onDrop"
            >
                <UploadIcon class="mb-2 h-8 w-8 text-gray-400" />

                <span class="text-sm font-medium text-brand-500">
                    {{ t('tx.chooseFiles') }}
                </span>
                <span
                    class="mt-1 text-theme-xs text-gray-500 dark:text-gray-400"
                >
                    {{ t('tx.dropHint') }}
                </span>
            </label>
        </template>

        <p
            v-else
            class="rounded-xl border border-gray-200 p-4 text-center text-theme-xs text-gray-500 dark:border-gray-800 dark:text-gray-400"
        >
            {{ t('tx.evidenceLocked') }}
        </p>

        <p
            v-if="uploading"
            class="mt-2 text-theme-xs text-gray-500 dark:text-gray-400"
        >
            {{ t('tx.uploading') }}
        </p>

        <p
            v-else-if="queued.length > 0"
            class="mt-2 text-theme-xs text-warning-600 dark:text-warning-400"
        >
            {{ t('tx.evidenceQueued', { count: queued.length }) }}
        </p>

        <p v-if="uploadError" class="mt-2 text-theme-xs text-error-500">
            {{ uploadError }}
        </p>

        <!-- ---------- ຫຼັກຖານ: ລໍຖ້າອັບ + ອັບແລ້ວ ---------- -->
        <ul v-if="total > 0" class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-4">
            <!-- ຍັງບໍ່ໄດ້ອັບ — ພຽງແຕ່ຖືໄວ້ໃນ browser -->
            <li
                v-for="item in queued"
                :key="item.id"
                class="group relative opacity-70"
            >
                <span
                    class="block overflow-hidden rounded-lg border border-dashed border-warning-400"
                >
                    <img
                        v-if="item.previewUrl"
                        :src="item.previewUrl"
                        :alt="item.file.name"
                        class="aspect-square w-full object-cover"
                    />
                    <span
                        v-else
                        class="flex aspect-square w-full items-center justify-center bg-gray-50 dark:bg-white/[0.03]"
                    >
                        <FileIcon class="h-8 w-8 text-gray-400" />
                    </span>
                </span>

                <p
                    class="mt-1 truncate text-theme-xs text-gray-500 dark:text-gray-400"
                    :title="item.file.name"
                >
                    {{ item.file.name }}
                </p>

                <button
                    type="button"
                    class="absolute top-1 right-1 flex h-6 w-6 items-center justify-center rounded-full bg-white/90 text-error-500 shadow-theme-xs dark:bg-gray-900/90"
                    :aria-label="
                        t('tx.removeEvidence', { name: item.file.name })
                    "
                    @click="dequeue(item)"
                >
                    &times;
                </button>
            </li>

            <!-- ອັບແລ້ວ — ມີຢູ່ຈິງຢູ່ private disk -->
            <li v-for="item in items" :key="item.uuid" class="group relative">
                <!--
                    ໃຊ້ thumb_url — ໂຫຼດຮູບເຕັມທຸກຮູບພ້ອມກັນຈະໜັກຫຼາຍ
                    (ໄຟລ໌ Excel ເດີມມີ 65 ຮູບ, ບາງຮູບ 2.5 MB).
                    ຮູບເຕັມໂຫຼດຕອນເປີດ lightbox ເທົ່ານັ້ນ.
                -->
                <button
                    v-if="item.is_image && item.thumb_url"
                    type="button"
                    class="block w-full overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800"
                    :aria-label="
                        t('tx.openImage', { name: item.original_name })
                    "
                    @click="lightbox.open(item)"
                >
                    <span class="relative block">
                        <img
                            :src="item.thumb_url"
                            :alt="item.original_name"
                            loading="lazy"
                            class="aspect-square w-full object-cover"
                        />
                        <span
                            class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition group-hover:opacity-100"
                        >
                            <ZoomIcon class="h-6 w-6 text-white" />
                        </span>
                    </span>
                </button>

                <!-- PDF — lightbox ສະແດງບໍ່ໄດ້ ຈຶ່ງເປີດແທັບໃໝ່ -->
                <a
                    v-else
                    :href="item.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="block overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800"
                >
                    <span
                        class="flex aspect-square w-full items-center justify-center bg-gray-50 dark:bg-white/[0.03]"
                    >
                        <FileIcon class="h-8 w-8 text-gray-400" />
                    </span>
                </a>

                <p
                    class="mt-1 truncate text-theme-xs text-gray-500 dark:text-gray-400"
                    :title="item.original_name"
                >
                    {{ item.original_name }}
                </p>
                <p class="truncate text-theme-xs text-gray-400">
                    {{ t(`tx.evidenceKinds.${item.kind}`) }}
                </p>

                <button
                    v-if="!disabled"
                    type="button"
                    class="absolute top-1 right-1 flex h-6 w-6 items-center justify-center rounded-full bg-white/90 text-error-500 opacity-0 shadow-theme-xs transition group-hover:opacity-100 focus:opacity-100 dark:bg-gray-900/90"
                    :aria-label="
                        t('tx.removeEvidence', { name: item.original_name })
                    "
                    @click="remove(item)"
                >
                    &times;
                </button>
            </li>
        </ul>

        <VueEasyLightbox
            :visible="lightbox.visible.value"
            :imgs="lightbox.sources.value"
            :index="lightbox.index.value"
            teleport="body"
            move-disabled
            @hide="lightbox.close"
        />
    </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, useId, watch } from 'vue';
import VueEasyLightbox from 'vue-easy-lightbox';
import { useI18n } from 'vue-i18n';
import { api, toApiError } from '@/api/client';
import { useEvidenceLightbox } from '@/composables/useEvidenceLightbox';
import type { Attachment, EvidenceKind } from '@/types/transactions';
import UploadIcon from '~icons/tabler/cloud-upload';
import FileIcon from '~icons/tabler/file-text';
import ZoomIcon from '~icons/tabler/zoom-in';

interface Props {
    /** `expenses` ຫຼື `revenues` */
    resource: 'expenses' | 'revenues';
    /**
     * uuid ຂອງລາຍການ — null = ຍັງບໍ່ໄດ້ບັນທຶກ.
     * ໄຟລ໌ທີ່ວາງຕອນນີ້ຈະຖືກຖືໄວ້ໃນ browser ແລ້ວອັບໃຫ້ອັດຕະໂນມັດ
     * ທັນທີທີ່ລາຍການຖືກບັນທຶກ ແລະ uuid ປາກົດ.
     */
    uuid: string | null;
    /** ອະນຸມັດແລ້ວ = ຫຼັກຖານ freeze */
    disabled?: boolean;
}

/** ໄຟລ໌ທີ່ລໍຖ້າ uuid — ຍັງບໍ່ໄດ້ແຕະ server ເລີຍ */
interface QueuedFile {
    id: string;
    file: File;
    kind: EvidenceKind;
    /** object URL ສຳລັບ preview — ຕ້ອງ revoke ເອງ ບໍ່ດັ່ງນັ້ນຮົ່ວໜ່ວຍຄວາມຈຳ */
    previewUrl: string | null;
}

const props = withDefaults(defineProps<Props>(), { disabled: false });

const emit = defineEmits<{ changed: [count: number] }>();

const { t } = useI18n();

const KINDS: EvidenceKind[] = [
    'transfer_slip',
    'receipt',
    'invoice',
    'quotation',
    'contract',
    'photo',
    'other',
];

const inputId = useId();
const fileInput = ref<HTMLInputElement | null>(null);
const items = ref<Attachment[]>([]);
const queued = ref<QueuedFile[]>([]);
const kind = ref<EvidenceKind>('transfer_slip');
const dragging = ref(false);
const uploading = ref(false);
const uploadError = ref<string | null>(null);

const total = computed(() => items.value.length + queued.value.length);

// ດຶງ URL ໃໝ່ໃຫ້ເອງເມື່ອ signed URL ໃກ້ໝົດອາຍຸ (ເບິ່ງ composable)
const lightbox = useEvidenceLightbox(items, () => load());

async function load(): Promise<void> {
    if (!props.uuid) {
        items.value = [];

        return;
    }

    try {
        const { data } = await api.get<{ data: Attachment[] }>(
            `/${props.resource}/${props.uuid}/attachments`,
        );
        items.value = data.data;
        lightbox.markLoaded();
    } catch (e) {
        uploadError.value = toApiError(e).message;
    }
}

function onSelect(event: Event): void {
    const input = event.target as HTMLInputElement;

    if (input.files) {
        void accept(Array.from(input.files));
    }

    // ລ້າງຄ່າ — ບໍ່ດັ່ງນັ້ນເລືອກໄຟລ໌ດຽວກັນຊ້ຳຈະບໍ່ຍິງ change event ອີກ
    input.value = '';
}

function onDrop(event: DragEvent): void {
    dragging.value = false;

    if (event.dataTransfer?.files) {
        void accept(Array.from(event.dataTransfer.files));
    }
}

/** ມີ uuid ແລ້ວ → ອັບເລີຍ; ຍັງບໍ່ມີ → ເຂົ້າຄິວລໍຖ້າ */
async function accept(files: File[]): Promise<void> {
    if (files.length === 0) {
        return;
    }

    if (props.uuid) {
        await upload(files, kind.value);

        return;
    }

    uploadError.value = null;
    queued.value.push(
        ...files.map((file) => ({
            id: `${file.name}:${file.size}:${file.lastModified}`,
            file,
            kind: kind.value,
            previewUrl: file.type.startsWith('image/')
                ? URL.createObjectURL(file)
                : null,
        })),
    );
}

function dequeue(item: QueuedFile): void {
    releasePreview(item);
    queued.value = queued.value.filter((q) => q.id !== item.id);
}

function releasePreview(item: QueuedFile): void {
    if (item.previewUrl) {
        URL.revokeObjectURL(item.previewUrl);
    }
}

async function upload(files: File[], fileKind: EvidenceKind): Promise<void> {
    if (!props.uuid || files.length === 0) {
        return;
    }

    uploading.value = true;
    uploadError.value = null;

    const form = new FormData();
    form.append('kind', fileKind);
    files.forEach((file) => form.append('files[]', file));

    try {
        const { data } = await api.post<{ data: Attachment[] }>(
            `/${props.resource}/${props.uuid}/attachments`,
            form,
        );

        // server dedup ໄຟລ໌ຊ້ຳ — ກັນບໍ່ໃຫ້ແຖວດຽວກັນປາກົດສອງເທື່ອຢູ່ໜ້າຈໍ
        const existing = new Set(items.value.map((i) => i.uuid));
        items.value.push(...data.data.filter((a) => !existing.has(a.uuid)));

        lightbox.markLoaded();
        emit('changed', items.value.length);
    } catch (e) {
        const error = toApiError(e);
        uploadError.value = error.errors['files.0']?.[0] ?? error.message;

        throw e;
    } finally {
        uploading.value = false;
    }
}

/**
 * ສົ່ງໄຟລ໌ໃນຄິວຂຶ້ນ server ຫຼັງລາຍການຖືກບັນທຶກ.
 *
 * ແຍກສົ່ງຕາມປະເພດ — request ໜຶ່ງມີ `kind` ດຽວ ແລະ ຜູ້ໃຊ້ອາດວາງ
 * ໃບໂອນເງິນກັບໃບເກັບເງິນປົນກັນກ່ອນກົດບັນທຶກ.
 */
async function flushQueue(): Promise<void> {
    const pending = queued.value;

    if (pending.length === 0) {
        return;
    }

    const byKind = new Map<EvidenceKind, QueuedFile[]>();

    pending.forEach((item) => {
        byKind.set(item.kind, [...(byKind.get(item.kind) ?? []), item]);
    });

    for (const [fileKind, group] of byKind) {
        try {
            await upload(
                group.map((q) => q.file),
                fileKind,
            );
        } catch {
            // ຮັກສາໄຟລ໌ທີ່ລົ້ມໄວ້ໃນຄິວ ເພື່ອໃຫ້ຜູ້ໃຊ້ລອງໃໝ່ໄດ້ —
            // ຂໍ້ຄວາມຜິດພາດຖືກຕັ້ງໄວ້ໃນ upload() ແລ້ວ
            return;
        }

        group.forEach(releasePreview);
        queued.value = queued.value.filter((q) => !group.includes(q));
    }
}

async function remove(item: Attachment): Promise<void> {
    uploadError.value = null;

    try {
        await api.delete(`/attachments/${item.uuid}`);
        items.value = items.value.filter((i) => i.uuid !== item.uuid);
        emit('changed', items.value.length);
    } catch (e) {
        uploadError.value = toApiError(e).message;
    }
}

watch(
    () => props.uuid,
    async (uuid) => {
        await load();

        if (uuid) {
            await flushQueue();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => queued.value.forEach(releasePreview));
</script>
