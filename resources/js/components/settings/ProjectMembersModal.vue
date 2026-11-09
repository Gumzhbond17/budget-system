<template>
    <Modal full-screen-backdrop @close="$emit('close')">
        <template #body>
            <!-- flex-col + max-h: ກ້ອງຢູ່ນິ້ງ ບໍ່ສູງເກີນຈໍ ແລະ ມີແຕ່ລາຍການ
                 ສະມາຊິກທີ່ເລື່ອນ (ເບິ່ງ FormModal ສຳລັບເຫດຜົນເຕັມ) -->
            <div
                class="relative z-10 m-4 flex max-h-[calc(100dvh-2rem)] w-full max-w-3xl flex-col rounded-3xl bg-white p-6 lg:p-8 dark:bg-gray-900"
            >
                <button
                    type="button"
                    class="absolute top-5 right-5 flex h-9 w-9 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-white/5 dark:hover:text-white/80"
                    :aria-label="t('common.close')"
                    @click="$emit('close')"
                >
                    &times;
                </button>

                <h4
                    class="mb-1 pr-10 text-xl font-semibold text-gray-800 dark:text-white/90"
                >
                    {{ t('settings.members.title') }}
                </h4>
                <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">
                    {{ project.name }} · {{ project.code }}
                </p>

                <Alert
                    v-if="error && !error.errors.role && !error.errors.user_id"
                    variant="error"
                    :title="t('settings.saveFailed')"
                    :message="error.message"
                    class="mb-5"
                />

                <!-- ---------- ເພີ່ມສະມາຊິກໃໝ່ ---------- -->
                <div
                    class="mb-6 shrink-0 rounded-xl border border-gray-200 p-4 dark:border-gray-800"
                >
                    <p
                        class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        {{ t('settings.members.add') }}
                    </p>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                        <div class="flex-1">
                            <SearchableSelect
                                v-model="newMember.user_id"
                                :aria-label="t('settings.members.selectUser')"
                                :options="assignableOptions"
                                placeholder="settings.members.selectUser"
                                :disabled="assignable.length === 0"
                                :invalid="Boolean(error?.errors.user_id)"
                            />
                            <p
                                v-if="error?.errors.user_id"
                                class="mt-1.5 text-theme-xs text-error-500"
                            >
                                {{ error.errors.user_id[0] }}
                            </p>
                            <!--
                                ສອງກໍລະນີນີ້ຕ່າງກັນ ແລະ ຕ້ອງເວົ້າຕ່າງກັນ:
                                "ອົງກອນບໍ່ມີຜູ້ໃຊ້ເລີຍ" ແກ້ໄດ້ດ້ວຍການສ້າງບັນຊີ
                                ແຕ່ "ທຸກຄົນມີສິດແລ້ວ" ບໍ່ມີຫຍັງໃຫ້ເຮັດ. ຮ້ານທີ່
                                ຜູ້ດູແລລະບົບສ້າງໃຫ້ຈະຕົກຢູ່ກໍລະນີທຳອິດສະເໝີ —
                                ຜູ້ສ້າງມີ company_id = null ຈຶ່ງບໍ່ໄດ້ນັບເປັນ
                                ຜູ້ໃຊ້ຂອງຮ້ານ.
                            -->
                            <p
                                v-else-if="!loadingUsers && noUsersInCompany"
                                class="mt-1.5 text-theme-xs text-gray-500 dark:text-gray-400"
                            >
                                {{ t('settings.members.noUsersYet') }}

                                <RouterLink
                                    v-if="auth.isPlatformAdmin && companyUuid"
                                    :to="{
                                        name: 'settings.users',
                                        query: { company: companyUuid },
                                    }"
                                    class="font-medium text-brand-500 hover:underline"
                                >
                                    {{ t('settings.members.createUser') }}
                                </RouterLink>
                            </p>
                            <p
                                v-else-if="
                                    !loadingUsers && assignable.length === 0
                                "
                                class="mt-1.5 text-theme-xs text-gray-500 dark:text-gray-400"
                            >
                                {{ t('settings.members.noneAssignable') }}
                            </p>
                        </div>

                        <div class="sm:w-48">
                            <SearchableSelect
                                :model-value="newMember.role"
                                :aria-label="t('settings.members.role')"
                                :options="roleOptions"
                                :invalid="Boolean(error?.errors.role)"
                                @update:model-value="
                                    newMember.role = $event as ProjectRole
                                "
                            />
                            <p
                                v-if="error?.errors.role"
                                class="mt-1.5 text-theme-xs text-error-500"
                            >
                                {{ error.errors.role[0] }}
                            </p>
                        </div>

                        <button
                            type="button"
                            :disabled="!newMember.user_id || saving"
                            class="h-11 shrink-0 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="addMember"
                        >
                            {{ t('settings.members.assign') }}
                        </button>
                    </div>

                    <!-- ຄຳອະທິບາຍລະດັບສິດ — ຜູ້ດູແລບໍ່ຄວນຕ້ອງເດົາວ່າ "authorize" ແປວ່າຫຍັງ -->
                    <p
                        class="mt-3 text-theme-xs text-gray-500 dark:text-gray-400"
                    >
                        {{ t(`settings.roleHint.${newMember.role}`) }}
                    </p>
                </div>

                <!-- ---------- ສະມາຊິກປັດຈຸບັນ ---------- -->
                <LoadingState v-if="loading" size="md" />

                <!-- ກິນພື້ນທີ່ທີ່ເຫຼືອແທນຄວາມສູງຕາຍຕົວ — ຢູ່ຈໍໃຫຍ່ເຫັນສະມາຊິກ
                     ໄດ້ຫຼາຍຂຶ້ນ ແລະ ຢູ່ຈໍເຕ້ຍກໍບໍ່ດັນກ້ອງອອກນອກຈໍ -->
                <div
                    v-else
                    class="custom-scrollbar min-h-0 flex-1 overflow-y-auto"
                >
                    <DataGrid
                        :column-defs="columnDefs"
                        :row-data="members"
                        :get-row-id="getRowId"
                        :row-height="56"
                        :empty-label="t('settings.empty')"
                    />
                </div>
            </div>
        </template>
    </Modal>
</template>

<script setup lang="ts">
import type { ColDef, ICellRendererParams } from 'ag-grid-community';
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { RouterLink } from 'vue-router';
import { api, toApiError } from '@/api/client';
import type { ApiError } from '@/api/client';
import DataGrid from '@/components/common/DataGrid.vue';
import SearchableSelect from '@/components/common/SearchableSelect.vue';
import ActionsCell from '@/components/grid/ActionsCell.vue';
import RoleSelectCell from '@/components/grid/RoleSelectCell.vue';
import TwoLineCell from '@/components/grid/TwoLineCell.vue';
import Alert from '@/components/ui/Alert.vue';
import LoadingState from '@/components/ui/LoadingState.vue';
import Modal from '@/components/ui/Modal.vue';
import { actionColDef } from '@/lib/ag-grid';
import { useAuthStore } from '@/stores/auth';
import { PROJECT_ROLES } from '@/types/settings';
import type {
    AppUser,
    Project,
    ProjectMember,
    ProjectRole,
} from '@/types/settings';
import RemoveMemberIcon from '~icons/tabler/user-minus';

const props = defineProps<{ project: Project }>();
defineEmits<{ close: [] }>();

const { t } = useI18n();
const auth = useAuthStore();

const members = ref<ProjectMember[]>([]);
const candidates = ref<AppUser[]>([]);
const loading = ref(true);
const loadingUsers = ref(true);
const saving = ref(false);
const error = ref<ApiError | null>(null);

const newMember = reactive<{ user_id: string; role: ProjectRole }>({
    user_id: '',
    role: 'read_only',
});

const base = computed(() => `/projects/${props.project.uuid}/members`);

/**
 * ຄົນທີ່ຍັງເພີ່ມໄດ້ = ຜູ້ໃຊ້ຂອງອົງກອນເຈົ້າຂອງໂຄງການ ທີ່ຍັງບໍ່ເປັນສະມາຊິກ.
 * server ກໍປະຕິເສດຄົນນອກອົງກອນຢູ່ແລ້ວ — ຢູ່ນີ້ພຽງແຕ່ບໍ່ສະແດງໃຫ້ເລືອກ
 * ເພື່ອບໍ່ໃຫ້ຜູ້ດູແລກົດແລ້ວຄ່ອຍໄດ້ຮັບ error.
 */
const assignable = computed(() => {
    const taken = new Set(members.value.map((member) => member.user?.id));

    return candidates.value.filter((user) => !taken.has(user.id));
});

const companyUuid = computed(() => props.project.company?.uuid ?? null);

/**
 * ຫວ່າງເພາະ **ອົງກອນບໍ່ມີຜູ້ໃຊ້ເລີຍ** — ບໍ່ແມ່ນເພາະທຸກຄົນມີສິດຢູ່ແລ້ວ.
 * ແຍກສອງກໍລະນີນີ້ ເພາະທາງແກ້ຕ່າງກັນຄົນລະຢ່າງ.
 */
const noUsersInCompany = computed(() => candidates.value.length === 0);

const assignableOptions = computed(() =>
    assignable.value.map((user) => ({
        value: String(user.id),
        label: user.name,
        secondary: user.email,
        translate: false,
    })),
);

const roleOptions = computed(() =>
    PROJECT_ROLES.map((role) => ({
        value: role,
        label: `settings.role.${role}`,
    })),
);

async function loadMembers(): Promise<void> {
    loading.value = true;

    try {
        const { data } = await api.get<{ data: ProjectMember[] }>(base.value);
        members.value = data.data;
    } catch (e) {
        error.value = toApiError(e);
    } finally {
        loading.value = false;
    }
}

async function loadCandidates(): Promise<void> {
    loadingUsers.value = true;

    try {
        const { data } = await api.get<{ data: AppUser[] }>('/users', {
            params: props.project.company
                ? { company_uuid: props.project.company.uuid }
                : {},
        });
        candidates.value = data.data;
    } catch (e) {
        error.value = toApiError(e);
    } finally {
        loadingUsers.value = false;
    }
}

async function addMember(): Promise<void> {
    saving.value = true;
    error.value = null;

    try {
        const { data } = await api.post<{ data: ProjectMember }>(base.value, {
            user_id: Number(newMember.user_id),
            role: newMember.role,
        });

        members.value.push(data.data);
        newMember.user_id = '';
    } catch (e) {
        error.value = toApiError(e);
    } finally {
        saving.value = false;
    }
}

async function changeRole(
    member: ProjectMember,
    role: ProjectRole,
): Promise<void> {
    error.value = null;

    try {
        const { data } = await api.patch<{ data: ProjectMember }>(
            `${base.value}/${member.user?.id}`,
            { role },
        );
        Object.assign(member, data.data);
    } catch (e) {
        error.value = toApiError(e);
        // server ປະຕິເສດ (ເຊັ່ນ ຫຼຸດສິດ full_access ຄົນສຸດທ້າຍ) → ດຶງຄ່າຈິງຄືນ
        // ບໍ່ດັ່ງນັ້ນ dropdown ຈະຄ້າງສະແດງຄ່າທີ່ບໍ່ໄດ້ຖືກບັນທຶກ
        await loadMembers();
    }
}

async function removeMember(member: ProjectMember): Promise<void> {
    error.value = null;

    try {
        await api.delete(`${base.value}/${member.user?.id}`);
        members.value = members.value.filter(
            (row) => row.user?.id !== member.user?.id,
        );
    } catch (e) {
        error.value = toApiError(e);
    }
}

// ---------- ຕາຕະລາງ ----------

const getRowId = (params: { data: ProjectMember }) =>
    String(params.data.user?.id ?? '');

const columnDefs = computed<ColDef[]>(() => [
    {
        colId: 'user',
        headerName: t('settings.members.user'),
        flex: 2,
        minWidth: 200,
        cellRenderer: TwoLineCell,
        cellRendererParams: {
            secondary: (params: ICellRendererParams<ProjectMember>) =>
                params.data?.user?.email ?? null,
        },
        valueGetter: (params) => params.data?.user?.name ?? '',
    },
    {
        field: 'role',
        headerName: t('settings.members.role'),
        width: 180,
        cellRenderer: RoleSelectCell,
        cellRendererParams: {
            roles: PROJECT_ROLES,
            onChange: changeRole,
        },
    },
    {
        ...actionColDef,
        colId: 'actions',
        headerName: t('common.actions'),
        width: 110,
        cellRenderer: ActionsCell,
        cellRendererParams: {
            actions: (params: ICellRendererParams<ProjectMember>) =>
                params.data
                    ? [
                          {
                              label: t('settings.members.remove'),
                              icon: RemoveMemberIcon,
                              danger: true,
                              onClick: () =>
                                  removeMember(params.data as ProjectMember),
                          },
                      ]
                    : [],
        },
    },
]);

onMounted(() => {
    void loadMembers();
    void loadCandidates();
});
</script>
