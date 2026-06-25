<script setup lang="ts">
import { computed, watch, reactive } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Modal from '@/Components/UI/Modal.vue'

interface User {
    name: string
    email: string
    citizen_number: string
    address: string
    gender: string
}

const props = defineProps<{
    show: boolean
    title: string
    user?: User | null
}>()

function submit() {
    if (!validate()) {
        return
    }

    emit('save', form.data())
}

const emit = defineEmits<{
    close: []
    save: [value: User]
}>()

const form = useForm({
    employee_id: '',
    name: '',
    email: '',
    citizen_number: '',
    role: 'employee',
    address: '',
    gender: '',
})

watch(
    () => props.user,
    (user) => {
        form.defaults({
            name: user?.name ?? '',
            email: user?.email ?? '',
            citizen_number: user?.citizen_number ?? '',
            address: user?.address ?? '',
            gender: user?.gender ?? '',
        })

        form.reset()
    },
    { immediate: true }
)

const errors = reactive({
    name: '',
    email: '',
    citizen_number: '',
    address: '',
    gender: '',
})

function validate() {
    errors.name = ''
    errors.email = ''
    errors.citizen_number = ''
    errors.address = ''
    errors.gender = ''

    let valid = true

    if (!form.name) {
        errors.name = 'Full Name is required.'
        valid = false
    }

    if (!form.email) {
        errors.email = 'Email is required.'
        valid = false
    }

    if (
        !form.citizen_number
    ) {
        errors.citizen_number = 'Citizenship Number is required.'
        valid = false
    }
    if (
        !form.gender
    ) {
        errors.address = 'Gender is required.'
        valid = false
    }
    if (
        !form.address
    ) {
        errors.address = 'Address is required.'
        valid = false
    }

    return valid
}

watch(() => form.name, () => errors.name = '')
watch(() => form.email, () => errors.email = '')
watch(() => form.citizen_number, () => errors.citizen_number = '')
watch(() => form.address, () => errors.address = '')
watch(() => form.gender, () => errors.gender = '')
</script>

<template>
    <Modal
        :show="show"
        maxWidth="max-w-2xl"
        @close="emit('close')"
    >
        <!-- Header -->

        <div
            class="flex items-center justify-between border-b border-border px-6 py-4"
        >
            <h2 class="text-lg font-bold">
                {{ title }}
            </h2>

            <button
                type="button"
                class="rounded-md p-2 hover:bg-slate-100"
                @click="emit('close')"
            >
                <i class="fa-solid fa-xmark" />
            </button>
        </div>

        <!-- Body -->

        <div class="flex-1 overflow-y-auto p-6">
            <div class="grid gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Full Name <span class="text-red-500">*</span>
                    </label>

                    <input
                        v-model="form.name"
                        type="text"
                        :class="[
                            'w-full rounded-md border px-3 py-2',
                            errors.name
                                ? 'border-red-500'
                                : 'border-border',
                        ]"
                    />

                    <p
                        v-if="errors.name"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ errors.name }}
                    </p>
                </div>
                <div>
                    
                    <label class="mb-1 block text-sm font-medium">
                        Email <span class="text-red-500">*</span>
                    </label>
    
                    <input
                        v-model="form.email"
                        type="email"
                        :class="[
                            'w-full rounded-md border px-3 py-2',
                            errors.email
                                ? 'border-red-500'
                                : 'border-border',
                        ]"
                    />
    
                    <p
                        v-if="errors.email"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ errors.email }}
                    </p>
                </div>
                <div>

                    <label class="mb-1 block text-sm font-medium">
                        Citizenship Number
    
                        <span
                            class="text-red-500"
                        >*</span>
                    </label>
    
                    <input
                        v-model="form.citizen_number"
                        type="text"
                        :class="[
                            'w-full rounded-md border px-3 py-2',
                            errors.citizen_number
                                ? 'border-red-500'
                                : 'border-border',
                        ]"
                    />
    
                    <p
                        v-if="errors.citizen_number"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ errors.citizen_number }}
                    </p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Gender
                        <span class="text-red-500">*</span>
                    </label>

                    <select
                        v-model="form.gender"
                        :class="[
                            'w-full rounded-md border px-3 py-2',
                            errors.gender
                                ? 'border-red-500'
                                : 'border-border',
                        ]"
                    >
                        <option value="">
                            Select Gender
                        </option>

                        <option value="Male">
                            Male
                        </option>

                        <option value="Female">
                            Female
                        </option>
                    </select>

                    <p
                        v-if="errors.gender"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ errors.gender }}
                    </p>
                </div>
                <div>

                    <label class="mb-1 block text-sm font-medium">
                        Address
    
                        <span
                            class="text-red-500"
                        >*</span>
                    </label>
    
                    <textarea
                        v-model="form.address"
                        type="text"
                        :class="[
                            'w-full rounded-md border px-3 py-2',
                            errors.address
                                ? 'border-red-500'
                                : 'border-border',
                        ]"
                    />
    
                    <p
                        v-if="errors.address"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ errors.address }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Footer -->

        <div
            class="flex justify-end gap-2 border-t border-border px-6 py-4"
        >
            <button
                type="button"
                class="rounded-md border border-slate-300 px-4 py-2"
                @click="emit('close')"
            >
                Cancel
            </button>

            <button
                type="button"
                class="rounded-md bg-primary px-4 py-2 text-white"
                @click="submit"
            >
                Save
            </button>
        </div>
    </Modal>
</template>