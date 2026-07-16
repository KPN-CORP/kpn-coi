<script setup lang="ts">
import PageHeader from '@/Components/UI/PageHeader.vue'
import Card from '@/Components/UI/Card.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref } from 'vue'
import UserFormModal from '@/Components/Admin/UserFormModal.vue'
import UploadUserModal from '@/Components/Admin/UploadUserModal.vue'
import DeleteUserModal from '@/Components/Admin/DeleteUserModal.vue'
import { router, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Pagination from '@/Components/UI/Pagination.vue'
import Swal from 'sweetalert2'

const showUserModal = ref(false)

const modalTitle = ref('Add User')

const selectedUser = ref<User | null>(null)

const showUploadModal = ref(false)

const showDeleteModal = ref(false)

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

                    title: 'Import Completed',

                    text: 'Users imported successfully.',

                    confirmButtonColor: '#ab2f2b',

                })

            },

            onError: (errors) => {

                const message =
                    errors.file ??
                    Object.values(errors)[0]

                Swal.fire({

                    icon: 'error',

                    title: 'Import Failed',

                    html: `
                        <p>${message}</p>

                        <br>

                        <a
                            href="${route('admin.credentials.import.error')}"
                            class="text-primary underline"
                        >
                            Download Error Report
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

interface User {
    id: number
    employee_id: string | null
    name: string
    email: string
    type: string
    citizen_number: string
    address: string
    business_unit: string
    date_of_joining: string
}

function openAddModal() {
    modalTitle.value = 'Add User'
    selectedUser.value = null
    showUserModal.value = true
}

function openEditModal(user: any) {
    modalTitle.value = 'Edit User'

    selectedUser.value = user

    showUserModal.value = true
}

async function saveUser(payload: any) {

    const isEdit = !!selectedUser.value

    if (!isEdit) {

        const result = await Swal.fire({

            title: 'Create User?',

            text: 'A new account will be created and the login credentials will be sent to the user email.',

            icon: 'question',

            showCancelButton: true,

            confirmButtonText: 'Create User',

            cancelButtonText: 'Cancel',

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

                title: 'Success',

                text: isEdit
                    ? 'User updated successfully.'
                    : 'User created successfully. Login credentials have been sent to the user email.',

                confirmButtonColor: '#ab2f2b',

            })

        },

        onError: (errors: Record<string, string>) => {

    serverErrors.value = errors

    Swal.fire({
        icon: 'error',
        title: 'Validation Failed',
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

                    title: 'Password Reset',

                    text: 'A new password has been generated and sent to the user email.',

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

                    title: 'Success',

                    text: 'User deleted successfully.',

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
        meta: any
    }

    businessUnitOptions: string[]

    filters: {
        search?: string
    }
}>()


const filter = useForm({
    search: props.filters.search ?? '',
})

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
            title="Credentials Database"
            description="Manage non-HRIS users and imported accounts."
        >
            <template #actions>
                <div class="flex flex-wrap gap-2">
                    <input
                        v-model="filter.search"
                        type="text"
                        placeholder="Search user..."
                        class="min-w-0 flex-1 rounded-md border border-border px-3 py-2 sm:flex-none"
                        @input="applyFilter"
                    />
                    <button
                        class="rounded-md border border-primary px-4 py-2 text-sm font-medium text-primary"
                        @click="showUploadModal = true"
                    >
                        Upload Users
                    </button>

                    <button
                        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white"
                        @click="openAddModal"
                    >
                        Add User
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
                                No
                            </th>

                            <th class="py-3">
                                Name
                            </th>

                            <th class="py-3">
                                Email
                            </th>

                            <th class="py-3">
                                Action
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
                                {{ index + 1 }}
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
                                <div
                                    v-if="user.type === 'non_employee'"
                                    class="flex gap-2"
                                >
                                    <button
                                        class="rounded-md border border-slate-300 px-3 py-1 text-sm"
                                        @click="openEditModal(user)"
                                    >
                                        Edit
                                    </button>

                                    <button
                                        class="rounded-md border border-red-300 px-3 py-1 text-sm text-red-600"
                                        @click="openDeleteModal(user)"
                                    >
                                        Delete
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
                                colspan="5"
                                class="py-10 text-center text-slate-500"
                            >
                                No users found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination
                        :links="props.users.links"
                    />
        </Card>
        <UserFormModal
            :show="showUserModal"
            :title="modalTitle"
            :user="selectedUser"
            :businessUnitOptions="businessUnitOptions"
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
    </AdminLayout>
</template>