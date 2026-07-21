<script setup lang="ts">
import { ref, watch } from 'vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'

import Card from '@/Components/UI/Card.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import Pagination from '@/Components/UI/Pagination.vue'

import RoleFormModal from '@/Components/Admin/RoleFormModal.vue'

import { router, useForm } from '@inertiajs/vue3'
import DeleteRoleModal from '@/Components/Admin/DeleteRoleModal.vue'
import AssignRoleModal from '@/Components/Admin/AssignRoleModal.vue'
import { useLocale } from '@/Composables/useLocale'
import debounce from 'lodash/debounce'

const { t } = useLocale()

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

    filters: {
        search?: string
        sort?: string
        direction?: string
        per_page?: number
    }
}>()

const filter = useForm({
    search: props.filters?.search ?? '',
    sort: props.filters?.sort ?? 'name',
    direction: props.filters?.direction ?? 'asc',
    per_page: props.filters?.per_page ?? 10,
})

function applyFilter() {
    router.get(
        route('admin.roles'),
        filter.data(),
        {
            preserveState: true,
            replace: true,
        },
    )
}

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

watch(
    () => filter.search,
    debounce(() => applyFilter(), 400),
)

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

// Permissions are managed per menu, so count the distinct menus a role can
// reach rather than every underlying permission (e.g. all `credential.*`
// counts as one). Only the menu modules are counted.
const MENU_MODULES = [
    'dashboard',
    'report',
    'credential',
    'role',
    'declaration',
]

function menuCount(role: any): number {
    const modules = new Set(
        (role.permissions ?? []).map(
            (permission: string) => permission.split('.')[0],
        ),
    )

    return MENU_MODULES.filter(module => modules.has(module)).length
}
</script>

<template>
    <AdminLayout>
        <PageHeader
            :title="t.roles.title"
            :description="t.roles.description"
        >
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <input
                        v-model="filter.search"
                        type="text"
                        :placeholder="t.roles.searchPlaceholder"
                        class="min-w-56 rounded-md border border-border px-3 py-2 text-sm"
                    >

                    <button
                        class="btn-primary-custom"
                        @click="createRole"
                    >
                        <i
                            class="fa-solid fa-plus"
                        />

                        {{ t.roles.createRole }}
                    </button>
                </div>
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
                            <th
                                class="cursor-pointer select-none whitespace-nowrap transition-colors hover:text-slate-700"
                                @click="toggleSort('name')"
                            >
                                <span class="inline-flex items-center gap-1.5">
                                    {{ t.roles.columnRole }}
                                    <i :class="sortIcon('name')" />
                                </span>
                            </th>

                            <th>
                                {{ t.roles.columnPermissions }}
                            </th>

                            <th
                                class="cursor-pointer select-none whitespace-nowrap transition-colors hover:text-slate-700"
                                @click="toggleSort('users_count')"
                            >
                                <span class="inline-flex items-center gap-1.5">
                                    {{ t.roles.columnTotalUsers }}
                                    <i :class="sortIcon('users_count')" />
                                </span>
                            </th>

                            <th>
                                {{ t.common.actions }}
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
                                {{ menuCount(role) }}
                                {{ t.roles.menusSuffix }}
                            </td>

                            <td>
                                {{ role.users_count ?? 0 }}
                            </td>

                            <td>
                                <div
                                    class="flex gap-2 justify-center"
                                >
                                    <button
                                        class="btn btn-outline-secondary btn-sm"
                                        @click="openEdit(role)"
                                    >
                                        {{ t.common.edit }}
                                    </button>

                                    <button
                                        class="btn bg-white border text-red-600 border-red-600 hover:bg-red-600 hover:text-white btn-sm"
                                        @click="openDelete(role)"
                                    >
                                        {{ t.common.delete }}
                                    </button>
                                    <button
                                        class="btn border border-black hover:bg-black hover:text-white btn-sm"
                                        @click="openAssign(role)"
                                    >
                                        {{ t.common.assign }}
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
                                {{ t.roles.noRoles }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination
                :links="roles.meta.links"
                :per-page="roles.meta.per_page"
                :total="roles.meta.total"
                :from="roles.meta.from"
                :to="roles.meta.to"
                @update:per-page="changePerPage"
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