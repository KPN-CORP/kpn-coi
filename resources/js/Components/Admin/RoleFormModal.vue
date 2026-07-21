<script setup lang="ts">
import { watch, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'

import Modal from '@/Components/UI/Modal.vue'
import Card from '@/Components/UI/Card.vue'
import MultiSelect from '@/Components/MultiSelect.vue'
import { useLocale } from '@/Composables/useLocale'

const { t } = useLocale()

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

// The parent mounts this modal with `v-if="showModal"`, so `show` is already
// true at mount. `immediate: true` makes the prefill run on that first mount —
// without it the watcher never fires and edits open with an empty form.
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
    { immediate: true },
)

// Permissions are simplified to one toggle per menu/module. Enabling a module
// grants every underlying permission for it (e.g. all `credential.*`), i.e.
// full access to that menu. Modules are shown in this order; any permission
// prefix not listed here (e.g. `reminder`) is intentionally not surfaced.
const MODULE_ORDER = [
    'dashboard',
    'report',
    'credential',
    'role',
    'declaration',
] as const

function moduleLabel(module: string): string {
    switch (module) {
        case 'dashboard':
            return t.value.roleForm.moduleDashboard

        case 'report':
            return t.value.roleForm.moduleReport

        case 'credential':
            return t.value.roleForm.moduleCredentials

        case 'role':
            return t.value.roleForm.moduleRole

        case 'declaration':
            return t.value.roleForm.moduleDeclarations

        default:
            return module
    }
}

const moduleGroups = computed(() => {
    const byModule = props.permissions.reduce(
        (groups, permission) => {
            const [module] = permission.name.split('.')

            ;(groups[module] ??= []).push(permission.name)

            return groups
        },
        {} as Record<string, string[]>,
    )

    return MODULE_ORDER
        .filter(module => byModule[module]?.length)
        .map(module => ({
            key: module,
            label: moduleLabel(module),
            permissions: byModule[module],
        }))
})

type ModuleGroup = {
    key: string
    label: string
    permissions: string[]
}

function isModuleEnabled(module: ModuleGroup): boolean {
    return module.permissions.every(
        permission => form.permissions.includes(permission),
    )
}

function toggleModule(module: ModuleGroup, enabled: boolean) {
    if (enabled) {
        form.permissions = [
            ...new Set([...form.permissions, ...module.permissions]),
        ]

        return
    }

    form.permissions = form.permissions.filter(
        permission => !module.permissions.includes(permission),
    )
}

const enabledModulesCount = computed(
    () => moduleGroups.value.filter(isModuleEnabled).length,
)

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
                    {{ role ? t.roleForm.editRole : t.roleForm.createRole }}
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    {{ t.roleForm.subtitle }}
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
                        {{ t.roleForm.roleName }}
                        <span class="text-danger">*</span>
                    </label>

                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full rounded-md border px-3 py-2"
                        :class="form.errors.name ? 'border-red-500' : 'border-border'"
                        :placeholder="t.roleForm.enterRoleName"
                    >

                    <p
                        v-if="form.errors.name"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <!-- Scope -->

                <Card class="card-custom">
                    <div
                        class="mb-4 border-b border-border pb-2"
                    >
                        <h3 class="font-semibold">
                            {{ t.roleForm.dataRestrictions }}
                        </h3>

                        <p
                            class="text-sm text-slate-500"
                        >
                            {{ t.roleForm.dataRestrictionsHint }}
                        </p>
                    </div>

                    <div
                        class="grid gap-4 md:grid-cols-3"
                    >
                        <!-- Work Area -->

                        <div>
                            <label class="form-label">
                                {{ t.roleForm.workArea }}
                            </label>

                            <MultiSelect
                                v-model="
                                    form.restrictions.work_area_code
                                "
                                :options="workAreas"
                                :placeholder="t.roleForm.selectWorkArea"
                            />
                        </div>

                        <!-- Group Company -->

                        <div>
                            <label class="form-label">
                                {{ t.roleForm.groupCompany }}
                            </label>

                            <MultiSelect
                                v-model="
                                    form.restrictions.group_company
                                "
                                :options="groupCompanies"
                                :placeholder="t.roleForm.selectCompany"
                            />
                        </div>

                        <!-- Contribution Level -->

                        <div>
                            <label class="form-label">
                                {{ t.roleForm.contributionLevel }}
                            </label>

                            <MultiSelect
                                v-model="
                                    form.restrictions.contribution_level_code
                                "
                                :options="contributionLevels"
                                :placeholder="t.roleForm.selectContributionLevel"
                            />
                        </div>
                    </div>
                </Card>

                <!-- Menu Access -->

                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <label class="form-label mb-0">
                            {{ t.roleForm.menuAccess }}
                        </label>

                        <span class="badge badge-info">
                            {{ enabledModulesCount }}
                            {{ t.roleForm.selected }}
                        </span>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <label
                            v-for="module in moduleGroups"
                            :key="module.key"
                            class="flex cursor-pointer items-center gap-3 rounded-md border border-border p-3 transition hover:bg-slate-50"
                        >
                            <input
                                type="checkbox"
                                class="h-4 w-4"
                                :checked="isModuleEnabled(module)"
                                @change="toggleModule(module, ($event.target as HTMLInputElement).checked)"
                            >

                            <div>
                                <div class="text-sm font-semibold text-slate-800">
                                    {{ module.label }}
                                </div>

                                <div class="text-xs text-slate-500">
                                    {{ t.roleForm.menuAccessHint }}
                                </div>
                            </div>
                        </label>
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
                {{ t.common.cancel }}
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

                {{ role ? t.roleForm.updateRole : t.roleForm.createRole }}
            </button>
        </div>
    </Modal>
</template>
