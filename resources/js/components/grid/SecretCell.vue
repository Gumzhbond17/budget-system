<template>
    <div class="flex items-center gap-1.5">
        <!--
            `select-all` ສະເພາະຕອນເປີດເຜີຍ: ກົດເທື່ອດຽວເລືອກທັງລະຫັດ ສຳລັບ
            ຕອນທີ່ clipboard ໃຊ້ບໍ່ໄດ້ (http ບໍ່ແມ່ນ https — ເບິ່ງ `copy()`).
            ຕົວອັກສອນເປັນ mono ເພື່ອບໍ່ໃຫ້ສັບສົນ 0/O ແລະ l/1/I.
        -->
        <span
            v-if="secret !== null"
            class="truncate font-mono text-sm break-all text-gray-800 select-all dark:text-white/90"
        >
            {{ secret }}
        </span>
        <span v-else class="font-mono text-sm tracking-widest text-gray-400">
            {{ params.data?.password_masked ?? '••••••••' }}
        </span>

        <!-- ປຸ່ມຢູ່ທ້າຍສຸດສະເໝີ ບໍ່ຂະຫຍັບຕາມຄວາມຍາວຂອງລະຫັດ -->
        <span class="ml-auto flex shrink-0 items-center gap-0.5">
            <button
                type="button"
                class="settings-action px-1.5"
                :title="
                    secret === null
                        ? t('settings.credentials.reveal')
                        : t('settings.credentials.hide')
                "
                :aria-label="
                    secret === null
                        ? t('settings.credentials.reveal')
                        : t('settings.credentials.hide')
                "
                @click="toggle"
            >
                <EyeOffIcon v-if="secret !== null" class="h-5 w-5" />
                <EyeIcon v-else class="h-5 w-5" />
            </button>

            <button
                v-if="secret !== null"
                type="button"
                class="settings-action px-1.5"
                :title="t('settings.credentials.copy')"
                :aria-label="t('settings.credentials.copy')"
                @click="copy"
            >
                <CheckIcon v-if="copied" class="h-5 w-5 text-success-500" />
                <CopyIcon v-else class="h-5 w-5" />
            </button>
        </span>
    </div>
</template>

<script setup lang="ts">
import type { ICellRendererParams } from 'ag-grid-community';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Credential } from '@/types/settings';
import { toastSuccess } from '@/utils/toast';
import CheckIcon from '~icons/tabler/check';
import CopyIcon from '~icons/tabler/copy';
import EyeIcon from '~icons/tabler/eye';
import EyeOffIcon from '~icons/tabler/eye-off';

/**
 * ຊ່ອງລະຫັດຜ່ານໃນຕາຕະລາງ — ປົກກະຕິເປັນຈຸດ, ກົດຕາຈຶ່ງຂໍເປີດເບິ່ງ.
 *
 * ⚠️ Component ນີ້ **ບໍ່ໄດ້ຖືລະຫັດຂອງຕົນເອງ** ແລະ ບໍ່ໄດ້ເອີ້ນ API ເອງ:
 * ມັນຖາມຄ່າຜ່ານ `params.secretOf(row)` ທຸກເທື່ອທີ່ render. ເຫດຜົນ —
 * AG Grid ສ້າງ/ທຳລາຍ cell ຕາມການເລື່ອນຈໍ (row virtualisation) ຖ້າລະຫັດ
 * ຢູ່ໃນ cell ມັນຈະຫາຍໄປເມື່ອເລື່ອນອອກນອກສາຍຕາ ແລ້ວກັບມາເປັນຈຸດຄືນ.
 * ເຈົ້າຂອງຂໍ້ມູນຄື Credentials.vue ບ່ອນດຽວ.
 */
interface SecretCellParams extends ICellRendererParams<Credential> {
    /** ລະຫັດທີ່ເປີດເຜີຍຢູ່ຂອງແຖວນີ້ — null = ຍັງບໍ່ໄດ້ເປີດ/ໝົດເວລາແລ້ວ */
    secretOf: (credential: Credential) => string | null;
    onReveal: (credential: Credential) => void;
    onHide: (credential: Credential) => void;
}

const props = defineProps<{ params: SecretCellParams }>();

const { t } = useI18n();

const copied = ref(false);

// `secretOf` ອ່ານຄ່າຈາກ reactive state ຂອງໜ້າແມ່ — computed ນີ້ຈຶ່ງ track
// ມັນໃຫ້ ແລະ cell ຈະ render ຄືນເອງເມື່ອເປີດ/ປິດ ໂດຍບໍ່ຕ້ອງ refreshCells()
const secret = computed(() => {
    const row = props.params.data;

    return row ? props.params.secretOf(row) : null;
});

function toggle(): void {
    const row = props.params.data;

    if (!row) {
        return;
    }

    if (secret.value === null) {
        props.params.onReveal(row);
    } else {
        props.params.onHide(row);
    }
}

/**
 * ສຳເນົາລະຫັດ.
 *
 * ⚠️ `navigator.clipboard` ມີສະເພາະໃນ secure context (https ຫຼື localhost) —
 * ລະບົບນີ້ແລ່ນຢູ່ http://solar-expense-api.test ຈຶ່ງ **ບໍ່ມີ** ຢູ່ເຄື່ອງຈິງ.
 * ບໍ່ໄດ້ລົບປຸ່ມຖິ້ມ ແຕ່ຖອຍໄປໃຊ້ execCommand ທີ່ເກົ່າແຕ່ຍັງໃຊ້ໄດ້ທຸກບ່ອນ.
 */
async function copy(): Promise<void> {
    const value = secret.value;

    if (value === null) {
        return;
    }

    const ok = (await copyViaClipboardApi(value)) || copyViaExecCommand(value);

    if (!ok) {
        return;
    }

    copied.value = true;
    toastSuccess(t('settings.credentials.copied'));

    window.setTimeout(() => {
        copied.value = false;
    }, 1500);
}

async function copyViaClipboardApi(value: string): Promise<boolean> {
    if (!navigator.clipboard) {
        return false;
    }

    try {
        await navigator.clipboard.writeText(value);

        return true;
    } catch {
        // ຜູ້ໃຊ້ປະຕິເສດສິດ ຫຼື ໜ້າບໍ່ໄດ້ໂຟກັດ — ລອງທາງເກົ່າຕໍ່
        return false;
    }
}

function copyViaExecCommand(value: string): boolean {
    const field = document.createElement('textarea');

    field.value = value;
    // ນອກສາຍຕາແຕ່ຍັງໂຟກັດໄດ້ — `display:none` ຈະເຮັດໃຫ້ເລືອກຂໍ້ຄວາມບໍ່ໄດ້
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.top = '-1000px';
    document.body.appendChild(field);

    try {
        field.select();

        return document.execCommand('copy');
    } catch {
        return false;
    } finally {
        field.remove();
    }
}
</script>
