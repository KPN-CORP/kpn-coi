<script setup lang="ts">
import { watch, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'

import Modal from '@/Components/UI/Modal.vue'
import Card from '@/Components/UI/Card.vue'
import MultiSelect from '@/Components/MultiSelect.vue'

interface Role {
    id?: number
    name: string

    restrictions: {
        work_area_code: string[]
        group_company: string[]
        contribution_level_code: string[]
    }

    permissions: string[]
}

const props = defineProps<{
    show: boolean

    role?: Role | null

    permissions: {
        id: number
        name: string
    }[]

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

const emit = defineEmits<{
    close: []
}>()

const form = useForm({
    name: '',

    restrictions: {
        work_area_code: [] as string[],
        group_company: [] as string[],
        contribution_level_code: [] as string[],
    },

    permissions: [] as string[],
})

watch(
    () => props.show,
    (show) => {
        if (!show) {
            return
        }

        form.reset()

        form.name =
            props.role?.name ?? ''

        form.restrictions = {
            work_area_code:
                props.role?.restrictions?.work_area_code ?? [],

            group_company:
                props.role?.restrictions?.group_company ?? [],

            contribution_level_code:
                props.role?.restrictions?.contribution_level_code ?? [],
        }

        form.permissions =
            props.role?.permissions ?? []
    },
)

const permissionGroups = computed(() => {
    return props.permissions.reduce(
        (groups, permission) => {
            const [module] =
                permission.name.split('.')

            if (!groups[module]) {
                groups[module] = []
            }

            groups[module].push(
                permission.name,
            )

            return groups
        },
        {} as Record<string, string[]>,
    )
})

function formatPermission(
    permission: string,
) {
    const parts =
        permission.split('.')

    return (
        parts[1] ??
        permission
    )
        .replaceAll('_', ' ')
        .replace(/\b\w/g, l =>
            l.toUpperCase(),
        )
}

function formatGroup(
    group: string,
) {
    return group
        .replaceAll('_', ' ')
        .replace(/\b\w/g, l =>
            l.toUpperCase(),
        )
}

function submit() {
    if (props.role?.id) {
        form.put(
            route(
                'admin.roles.update',
                props.role.id,
            ),
            {
                preserveScroll: true,

                onSuccess: () =>
                    emit('close'),
            },
        )

        return
    }

    form.post(
        route(
            'admin.roles.store',
        ),
        {
            preserveScroll: true,

            onSuccess: () =>
                emit('close'),
        },
    )
}
</script>

<template>
    <Modal
        :show="show"
        maxWidth="max-w-4xl"
        @close="emit('close')"
    >
        <!-- Header -->

        <div
            class="flex items-center justify-between border-b border-border bg-slate-50 px-6 py-4"
        >
            <div>
                <h2 class="text-lg font-bold text-slate-900">
                    {{ role ? 'Edit Role' : 'Create Role' }}
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Configure role access and permissions.
                </p>
            </div>

            <button
                type="button"
                class="rounded-md p-2 transition hover:bg-slate-200"
                @click="emit('close')"
            >
                <i class="fa-solid fa-xmark" />
            </button>
        </div>

        <!-- Body -->

        <div class="max-h-[65vh] overflow-y-auto p-6">
            <div
                class="grid gap-4"
            >
                <!-- Role Name -->

                <div>
                    <label class="form-label">
                        Role Name
                        <span class="text-danger">*</span>
                    </label>

                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full rounded-md border border-border px-3 py-2"
                        placeholder="Enter role name"
                    >
                </div>

                <!-- Scope -->

                <Card class="card-custom">
                    <div
                        class="mb-4 border-b border-border pb-2"
                    >
                        <h3 class="font-semibold">
                            Data Restrictions
                        </h3>

                        <p
                            class="text-sm text-slate-500"
                        >
                            Restrict accessible employee data.
                        </p>
                    </div>

                    <div
                        class="grid gap-4 md:grid-cols-3"
                    >
                        <!-- Work Area -->

                        <div>
                            <label class="form-label">
                                Work Area
                            </label>

                            <MultiSelect
                                v-model="
                                    form.restrictions.work_area_code
                                "
                                :options="workAreas"
                                placeholder="Select work area"
                            />
                        </div>

                        <!-- Group Company -->

                        <div>
                            <label class="form-label">
                                Group Company
                            </label>

                            <MultiSelect
                                v-model="
                                    form.restrictions.group_company
                                "
                                :options="groupCompanies"
                                placeholder="Select company"
                            />
                        </div>

                        <!-- Contribution Level -->

                        <div>
                            <label class="form-label">
                                Contribution Level
                            </label>

                            <MultiSelect
                                v-model="
                                    form.restrictions.contribution_level_code
                                "
                                :options="contributionLevels"
                                placeholder="Select contribution level"
                            />
                        </div>
                    </div>
                </Card>

                <!-- Permissions -->

                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <label class="form-label mb-0">
                            Permissions
                        </label>

                        <span class="badge badge-info">
                            {{ form.permissions.length }}
                            Selected
                        </span>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <Card
                            v-for="(permissions, group) in permissionGroups"
                            :key="group"
                            class="card-custom"
                        >
                            <div
                                class="mb-3 border-b border-border pb-2 font-semibold text-slate-800"
                            >
                                {{ formatGroup(group) }}
                            </div>

                            <div class="space-y-3">
                                <label
                                    v-for="permission in permissions"
                                    :key="permission"
                                    class="flex cursor-pointer items-center gap-3 rounded-md p-2 transition hover:bg-slate-50"
                                >
                                    <input
                                        v-model="form.permissions"
                                        :value="permission"
                                        type="checkbox"
                                        class="h-4 w-4"
                                    >

                                    <span class="text-sm">
                                        {{ formatPermission(permission) }}
                                    </span>
                                </label>
                            </div>
                        </Card>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->

        <div
            class="flex justify-end gap-3 border-t border-border bg-slate-50 px-6 py-4"
        >
            <button
                type="button"
                class="btn-outline-custom"
                @click="emit('close')"
            >
                Cancel
            </button>

            <button
                type="button"
                class="btn-primary-custom"
                :disabled="form.processing"
                @click="submit"
            >
                <i
                    v-if="form.processing"
                    class="fa-solid fa-spinner fa-spin"
                />

                {{ role ? 'Update Role' : 'Create Role' }}
            </button>
        </div>
    </Modal>
</template>
