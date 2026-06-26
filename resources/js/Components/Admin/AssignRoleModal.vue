<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

import Modal from '@/Components/UI/Modal.vue'
import MultiSelect from '@/Components/MultiSelect.vue'

interface User {
    id: number
    name: string
    email: string
}

const props = defineProps<{
    show: boolean
    role: any | null

    users: User[]
}>()

const emit = defineEmits<{
    close: []
}>()

const search = ref('')

const form = useForm({
    users: [] as number[],
})

watch(
    () => props.role,
    (role) => {
        if (!role) {
            return
        }

        console.log('Role Users', [...role.users])

        // or
        console.log('Role Users', JSON.parse(JSON.stringify(role.users)))

        // or
        console.log('Role Users Length', role.users?.length)

        form.users =
            role.users?.map((user: User) => Number(user.id)) ?? []

        console.log('Selected Users', form.users)
    },
    {
        immediate: true,
    },
)

const filteredUsers = computed(() => {
    const users = props.users ?? []

    if (!search.value) {
        return users
    }

    return users.filter(user =>
        user.name
            .toLowerCase()
            .includes(search.value.toLowerCase()) ||
        user.email
            .toLowerCase()
            .includes(search.value.toLowerCase()),
    )
})

function toggleAll() {
    const ids = filteredUsers.value.map(
        user => user.id,
    )

    const allSelected = ids.every(
        id => form.users.includes(id),
    )

    if (allSelected) {
        form.users = form.users.filter(
            id => !ids.includes(id),
        )

        return
    }

    form.users = [
        ...new Set([
            ...form.users,
            ...ids,
        ]),
    ]
}

function submit() {
    if (!props.role) {
        return
    }

    form.post(
        route(
            'admin.roles.assign-users',
            props.role.id,
        ),
        {
            preserveScroll: true,

            onSuccess: () => {
                emit('close')
            },
        },
    )
}

const userOptions = computed(() =>
    props.users.map(user => ({
        code: user.id,
        name: `${user.name} (${user.email})`,
    })),
)
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
                <h2 class="text-lg font-bold">
                    Assign Users
                </h2>

                <p class="text-sm text-slate-500">
                    Role:
                    <span class="font-medium">
                        {{ role?.name }}
                    </span>
                </p>
            </div>

            <button
                type="button"
                class="rounded-md p-2 hover:bg-slate-200"
                @click="emit('close')"
            >
                <i class="fa-solid fa-xmark" />
            </button>
        </div>

        <!-- Body -->

        <div class="p-6">
            <div>
                <label class="form-label">
                    Assigned Users
                </label>

                <MultiSelect
                    v-model="form.users"
                    :options="userOptions"
                    placeholder="Select users"
                />
                <div
                    class="mt-2 text-xs text-slate-500"
                >
                    {{ form.users.length }}
                    user(s) selected
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

                Save Assignment
            </button>
        </div>
    </Modal>
</template>