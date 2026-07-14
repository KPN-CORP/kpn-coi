<script setup lang="ts">
import { ref } from 'vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'

import Card from '@/Components/UI/Card.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import Pagination from '@/Components/UI/Pagination.vue'

import RoleFormModal from '@/Components/Admin/RoleFormModal.vue'

import { router } from '@inertiajs/vue3'
import DeleteRoleModal from '@/Components/Admin/DeleteRoleModal.vue'
import AssignRoleModal from '@/Components/Admin/AssignRoleModal.vue'

const showDeleteModal = ref(false)

function openDelete(role: any) {
    selectedRole.value = role
    showDeleteModal.value = true
}

function confirmDelete(id: number) {
    router.delete(
        route('admin.roles.destroy', id),
        {
            preserveScroll: true,

            onSuccess: () => {
                showDeleteModal.value = false
                selectedRole.value = null
            },
        },
    )
}

interface Role {
    id: number
    name: string
    restrictions: string
    permissions: string[]
}

const showAssignModal = ref(false)

const selectedRole = ref<any | null>(null)

const props = defineProps<{
    users: {
        id: number
        name: string
        email: string
    }[]
    roles: any[]
    availablePermissions: any[]

    workAreas: {
        code: string
        name: string
    }[]

    groupCompanies: {
        code: string
        name: string
    }[]

    contributionLevels: {
        code: string
        name: string
    }[]
}>()

const showModal = ref(false)

function createRole() {
    selectedRole.value = null
    showModal.value = true
}

function openEdit(role: Role) {
    selectedRole.value = role
    showModal.value = true
}


function openAssign(role: any) {
    selectedRole.value = role
    showAssignModal.value = true
}

function deleteRole(role: Role) {
    if (
        !confirm(
            `Delete role "${role.name}"?`,
        )
    ) {
        return
    }

    router.delete(
        route(
            'admin.roles.destroy',
            role.id,
        ),
    )
}
</script>

<template>
    <AdminLayout>
        <PageHeader
            title="Role Management"
            description="Manage roles, permissions and data scope."
        >
            <template #actions>
                <button
                    class="btn-primary-custom"
                    @click="createRole"
                >
                    <i
                        class="fa-solid fa-plus"
                    />

                    Create Role
                </button>
            </template>
        </PageHeader>

        <Card>
            <div
                class="table-container"
            >
                <table
                    class="table-custom"
                >
                    <thead>
                        <tr>
                            <th>
                                Role
                            </th>

                            <th>
                                Permissions
                            </th>

                            <th>
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr class="text-center"
                            v-for="role in roles.data"
                            :key="role.id"
                        >
                            <td>
                                {{ role.name }}
                            </td>

                            <td>
                                {{
                                    role.permissions.length
                                }}
                                Permissions
                            </td>

                            <td>
                                <div
                                    class="flex gap-2 justify-center"
                                >
                                    <button
                                        class="btn btn-outline-secondary btn-sm"
                                        @click="openEdit(role)"
                                    >
                                        Edit
                                    </button>

                                    <button
                                        class="btn bg-white border text-red-600 border-red-600 hover:bg-red-600 hover:text-white btn-sm"
                                        @click="openDelete(role)"
                                    >
                                        Delete
                                    </button>
                                    <button
                                        class="btn border border-black hover:bg-black hover:text-white btn-sm"
                                        @click="openAssign(role)"
                                    >
                                        Assign
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr
                            v-if="
                                !roles.data
                                    .length
                            "
                        >
                            <td
                                colspan="4"
                                class="py-8 text-center"
                            >
                                No roles found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination
                :links="roles.meta.links"
            />
        </Card>

        <RoleFormModal
            v-if="showModal"
            :show="showModal"
            :role="selectedRole"
            :permissions="availablePermissions"
            :work-areas="workAreas"
            :group-companies="groupCompanies"
            :contribution-levels="contributionLevels"
            @close="showModal = false"
        />

        <DeleteRoleModal
            v-if="showDeleteModal"
            :show="showDeleteModal"
            :role="selectedRole"
            @close="showDeleteModal = false"
            @confirm="confirmDelete"
        />

        <AssignRoleModal
            v-if="showAssignModal"
            :show="showAssignModal"
            :role="selectedRole"
            :users="props.users"
            @close="showAssignModal = false"
        />
    </AdminLayout>
</template>