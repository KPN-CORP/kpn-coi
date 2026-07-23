<script setup lang="ts">
import PageHeader from '@/Components/UI/PageHeader.vue'
import Card from '@/Components/UI/Card.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref, computed } from 'vue'
import UserFormModal from '@/Components/Admin/UserFormModal.vue'
import UploadUserModal from '@/Components/Admin/UploadUserModal.vue'
import DeleteUserModal from '@/Components/Admin/DeleteUserModal.vue'
import ConvertToEmployeeModal from '@/Components/Admin/ConvertToEmployeeModal.vue'
import { router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { formatDate } from '@/Utils/date'
import Pagination from '@/Components/UI/Pagination.vue'
import Swal from 'sweetalert2'
import { useLocale } from '@/Composables/useLocale'

const { t } = useLocale()

const showUserModal = ref(false)

const modalMode = ref<'add' | 'edit'>('add')

const modalTitle = computed(() =>
    modalMode.value === 'add'
        ? t.value.credentials.addUser
        : t.value.credentials.editUser,
)

const selectedUser = ref<User | null>(null)

const showUploadModal = ref(false)

const showDeleteModal = ref(false)

const showConvertModal = ref(false)

const serverErrors = ref<Record<string, string>>({})

function uploadUsers(file: File | null) {

    if (!file) {
        return
    }

    const form = new FormData()

    form.append(
        'file',
        file,
    )

    router.post(
        route('admin.credentials.import'),
        form,
        {
            forceFormData: true,

            preserveScroll: true,

            onSuccess: () => {

                showUploadModal.value = false

                Swal.fire({

                    icon: 'success',

                    title: t.value.credentials.importCompletedTitle,

                    text: t.value.credentials.importCompletedText,

                    confirmButtonColor: '#ab2f2b',

                })

            },

            onError: (errors) => {

                const message =
                    errors.file ??
                    Object.values(errors)[0]

                Swal.fire({

                    icon: 'error',

                    title: t.value.credentials.importFailedTitle,

                    html: `
                        <p>${message}</p>

                        <br>

                        <a
                            href="${route('admin.credentials.import.error')}"
                            class="text-primary underline"
                        >
                            ${t.value.credentials.downloadErrorReport}
                        </a>
                    `,

                    confirmButtonColor: '#ab2f2b',

                })

            },
        },
    )
}

function openDeleteModal(user: any) {
    selectedUser.value = user

    showDeleteModal.value = true
}

function openConvertModal(user: any) {
    selectedUser.value = user

    showConvertModal.value = true
}

interface User {
    id: number
    employee_id: string | null
    nik: string | null
    phone: string | null
    name: string
    email: string
    type: string
    citizen_number: string
    address: string
    business_unit: string
    office_area: string | null
    date_of_joining: string
}

function openAddModal() {
    modalMode.value = 'add'
    selectedUser.value = null
    showUserModal.value = true
}

function openEditModal(user: any) {
    modalMode.value = 'edit'

    selectedUser.value = user

    showUserModal.value = true
}

async function saveUser(payload: any) {

    const isEdit = !!selectedUser.value

    if (!isEdit) {

        const result = await Swal.fire({

            title: t.value.credentials.createUserTitle,

            text: t.value.credentials.createUserText,

            icon: 'question',

            showCancelButton: true,

            confirmButtonText: t.value.credentials.createUserConfirm,

            cancelButtonText: t.value.common.cancel,

            confirmButtonColor: '#ab2f2b',

            reverseButtons: true,

        })

        if (!result.isConfirmed) {
            return
        }

    }

    const options = {

        preserveScroll: true,

        onSuccess: () => {

            showUserModal.value = false

            selectedUser.value = null

            Swal.fire({

                icon: 'success',

                title: t.value.swal.success,

                text: isEdit
                    ? t.value.credentials.userUpdated
                    : t.value.credentials.userCreated,

                confirmButtonColor: '#ab2f2b',

            })

        },

        onError: (errors: Record<string, string>) => {

    serverErrors.value = errors

    Swal.fire({
        icon: 'error',
        title: t.value.credentials.validationFailed,
        text: Object.values(errors)[0],
        confirmButtonColor: '#ab2f2b',
    })
},

    }

    if (!isEdit) {

        router.post(
            route('admin.credentials.store'),
            payload,
            options,
        )

        return

    }

    router.put(
        route(
            'admin.credentials.update',
            selectedUser.value!.id,
        ),
        payload,
        options,
    )

}
async function resetPassword() {

    if (!selectedUser.value) {
        return
    }

    router.post(
        route(
            'admin.credentials.reset-password',
            selectedUser.value.id,
        ),
        {},
        {
            preserveScroll: true,

            onSuccess: () => {

                Swal.fire({

                    icon: 'success',

                    title: t.value.credentials.passwordResetTitle,

                    text: t.value.credentials.passwordResetText,

                    confirmButtonColor: '#ab2f2b',

                })

            },

        },
    )

}

function deleteUser() {

    if (!selectedUser.value) {
        return
    }

    router.delete(
        route(
            'admin.credentials.destroy',
            selectedUser.value.id,
        ),
        {
            preserveScroll: true,

            onSuccess: () => {

                showDeleteModal.value = false

                selectedUser.value = null

                Swal.fire({

                    icon: 'success',

                    title: t.value.swal.success,

                    text: t.value.credentials.userDeleted,

                    confirmButtonColor: '#ab2f2b',

                })

            },

        },
    )

}

const props = defineProps<{
    users: {
        data: User[]
        links: any[]
        per_page: number
        total: number
        from: number | null
        to: number | null
    }

    businessUnitOptions: string[]

    officeAreaOptions: {
        business_unit: string | null
        office_area: string
    }[]

    // Optional prop: only sent when the convert dialog asks for it by name.
    employeeOptions?: {
        employee_id: string
        name: string
        email: string | null
        business_unit: string | null
        designation: string | null
    }[]

    filters: {
        search?: string
        business_unit?: string
        sort?: string
        direction?: string
        per_page?: number
    }
}>()


const filter = useForm({
    search: props.filters.search ?? '',
    business_unit: props.filters.business_unit ?? '',
    sort: props.filters.sort ?? 'name',
    direction: props.filters.direction ?? 'asc',
    per_page: props.filters.per_page ?? 10,
})

const sortableColumns = computed(() => [
    { label: t.value.common.name, key: 'name' },
    { label: t.value.common.email, key: 'email' },
    { label: t.value.common.businessUnit, key: 'business_unit' },
    { label: t.value.credentials.columnDateOfJoin, key: 'date_of_joining' },
])

function toggleSort(column: string) {
    if (filter.sort === column) {
        filter.direction = filter.direction === 'asc' ? 'desc' : 'asc'
    } else {
        filter.sort = column
        filter.direction = 'asc'
    }

    applyFilter()
}

function sortIcon(column: string) {
    if (filter.sort !== column) {
        return 'fa-solid fa-sort text-slate-300'
    }

    return filter.direction === 'asc'
        ? 'fa-solid fa-sort-up text-slate-600'
        : 'fa-solid fa-sort-down text-slate-600'
}

function changePerPage(value: number) {
    filter.per_page = value

    applyFilter()
}

function applyFilter() {
    router.get(
        route('admin.credentials'),
        filter.data(),
        {
            preserveState: true,
            replace: true,
            only: ['users'],
        },
    )
}
</script>

<template>
    <AdminLayout>
        <PageHeader
            :title="t.credentials.title"
            :description="t.credentials.description"
        >
            <template #actions>
                <div class="flex flex-wrap gap-2">
                    <select
                        v-model="filter.business_unit"
                        class="min-w-40 rounded-md border border-border px-3 py-2 text-sm"
                        @change="applyFilter"
                    >
                        <option value="">
                            {{ t.teamHistory.allBusinessUnits }}
                        </option>

                        <option
                            v-for="item in businessUnitOptions"
                            :key="item"
                            :value="item"
                        >
                            {{ item }}
                        </option>
                    </select>

                    <input
                        v-model="filter.search"
                        type="text"
                        :placeholder="t.credentials.searchPlaceholder"
                        class="min-w-0 flex-1 rounded-md border border-border px-3 py-2 sm:flex-none"
                        @input="applyFilter"
                    />
                    <button
                        class="rounded-md border border-primary px-4 py-2 text-sm font-medium text-primary"
                        @click="showUploadModal = true"
                    >
                        {{ t.credentials.uploadUsers }}
                    </button>

                    <button
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white"
                        @click="openAddModal"
                    >
                        {{ t.credentials.addUser }}
                    </button>
                </div>
            </template>
        </PageHeader>

        <Card>
            <div class="table-container">
                <table class="table-custom">
                    <thead>
                        <tr
                            class="border-b border-border text-left text-xs uppercase text-slate-500"
                        >
                            <th class="py-3">
                                {{ t.credentials.columnNo }}
                            </th>

                            <th
                                v-for="col in sortableColumns"
                                :key="col.key"
                                class="py-3 cursor-pointer select-none whitespace-nowrap transition-colors hover:text-slate-700"
                                @click="toggleSort(col.key)"
                            >
                                <span class="inline-flex items-center gap-1.5">
                                    {{ col.label }}
                                    <i :class="sortIcon(col.key)" />
                                </span>
                            </th>

                            <th class="py-3">
                                {{ t.common.action }}
                            </th>
                        </tr>
                    </thead>

                    <tbody v-if="props.users.data.length">
                        <tr
                            v-for="(user, index) in props.users.data"
                            :key="user.id"
                            class="border-b border-slate-100"
                        >
                            <td class="py-4 font-medium">
                                {{ (props.users.from ?? 1) + index }}
                            </td>

                            <td class="py-4">
                               <div class="font-medium">
                                    {{ user.name }}
                                </div>

                                <div
                                    v-if="user.employee_id"
                                    class="text-xs text-slate-500"
                                >
                                    {{ user.employee_id }}
                                </div>
                            </td>

                            <td class="py-4">
                                {{ user.email }}
                            </td>

                            <td class="py-4">
                                {{ user.business_unit || '-' }}
                            </td>

                            <td class="py-4 whitespace-nowrap">
                                {{ formatDate(user.date_of_joining) }}
                            </td>

                            <td class="py-4">
                                <div
                                    v-if="user.type === 'non_employee'"
                                    class="flex gap-2"
                                >
                                    <button                                    
                                        type="button"
                                        class="btn btn-outline-secondary btn-sm"
                                        @click="openEditModal(user)"
                                    >
                                        
                                        <i class="fa-solid fa-pencil" /> {{ t.common.edit }}
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary btn-sm"
                                        @click="openConvertModal(user)"
                                    >
                                        <i class="fa-solid fa-user-check" /> {{ t.credentials.convert }}
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-outline-primary-custom btn-sm"
                                        @click="openDeleteModal(user)"
                                    >
                                        <i class="fa-solid fa-trash" /> {{ t.common.delete }}
                                    </button>
                                </div>

                                <span
                                    v-else
                                    class="text-xs text-slate-400"
                                >
                                    -
                                </span>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td
                                colspan="6"
                                class="py-10 text-center text-slate-500"
                            >
                                {{ t.credentials.noUsers }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination
                :links="props.users.links"
                :per-page="props.users.per_page"
                :total="props.users.total"
                :from="props.users.from"
                :to="props.users.to"
                @update:per-page="changePerPage"
            />
        </Card>
        <UserFormModal
            :show="showUserModal"
            :title="modalTitle"
            :user="selectedUser"
            :businessUnitOptions="businessUnitOptions"
            :officeAreaOptions="officeAreaOptions"
            @close="showUserModal = false"
            @save="saveUser"
            @reset-password="resetPassword"
            :server-errors="serverErrors"
        />
        <UploadUserModal
            :show="showUploadModal"
            @close="showUploadModal = false"
            @upload="uploadUsers"
        />
        <DeleteUserModal
            :show="showDeleteModal"
            :user-name="selectedUser?.name"
            @close="showDeleteModal = false"
            @delete="deleteUser"
        />

        <ConvertToEmployeeModal
            v-if="showConvertModal"
            :show="showConvertModal"
            :user="selectedUser"
            :employee-options="props.employeeOptions"
            @close="showConvertModal = false"
        />
    </AdminLayout>
</template>