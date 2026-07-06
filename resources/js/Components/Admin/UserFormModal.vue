<script setup lang="ts">
import { watch, reactive, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Modal from '@/Components/UI/Modal.vue'
import Swal from 'sweetalert2'

interface User {
    id?: number
    name: string
    email: string
    citizen_number: string
    address: string
    gender: string
}

const props = defineProps<{
    show: boolean
    title: string
    user?: User |null
    serverErrors: Record<string, string>
}>()

const emit = defineEmits<{
    close: []
    save: [value: User]
    resetPassword: []
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

const resettingPassword = ref(false)

const errors = reactive({
    name: '',
    email: '',
    citizen_number: '',
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

        form.clearErrors()

        resettingPassword.value = false

        Object.keys(errors).forEach(
            key => errors[key as keyof typeof errors] = ''
        )

    },
    {
        immediate: true,
    },
)

function validate() {

    form.clearErrors()

    errors.name = ''
    errors.email = ''
    errors.citizen_number = ''
    errors.address = ''
    errors.gender = ''

    let valid = true

    if (!form.name.trim()) {

        errors.name = 'Full Name is required.'

        valid = false

    }

    const email = form.email.trim()

    if (!email) {

        errors.email = 'Email is required.'

        valid = false

    } else {

        const emailRegex =
            /^[^\s@]+@[^\s@]+\.[^\s@]+$/

        if (!emailRegex.test(email)) {

            errors.email =
                'Please enter a valid email address.'

            valid = false

        }

    }

    if (!form.citizen_number.trim()) {

        errors.citizen_number =
            'Citizenship Number is required.'

        valid = false

    }

    if (!form.gender) {

        errors.gender =
            'Gender is required.'

        valid = false

    }

    if (!form.address.trim()) {

        errors.address =
            'Address is required.'

        valid = false

    }

    return valid

}

function submit() {

    if (!validate()) {
        return
    }

    emit(
        'save',
        form.data()
    )

}

async function resetPassword() {

    if (resettingPassword.value) {
        return
    }

    const result = await Swal.fire({

        title: 'Reset Password?',

        text: 'A new password will be generated and sent to the user email.',

        icon: 'warning',

        showCancelButton: true,

        confirmButtonText: 'Reset Password',

        cancelButtonText: 'Cancel',

        confirmButtonColor: '#ab2f2b',

        reverseButtons: true,

    })

    if (!result.isConfirmed) {
        return
    }

    resettingPassword.value = true

    emit('resetPassword')

}

function hasError(field: string) {
    return !!errors[field] || !!props.serverErrors[field]
}

function getError(field: string) {
    return errors[field] || props.serverErrors[field]
}

watch(() => form.name, () => {
    errors.name = ''
    form.clearErrors('name')
})

watch(() => form.email, () => {
    errors.email = ''
    form.clearErrors('email')
})

watch(() => form.citizen_number, () => {
    errors.citizen_number = ''
    form.clearErrors('citizen_number')
})

watch(() => form.gender, () => {
    errors.gender = ''
    form.clearErrors('gender')
})

watch(() => form.address, () => {
    errors.address = ''
    form.clearErrors('address')
})

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
                class="rounded-md p-2 transition hover:bg-slate-100"
                @click="emit('close')"
            >
                <i class="fa-solid fa-xmark" />
            </button>
        </div>

        <!-- Body -->

        <div class="flex-1 overflow-y-auto p-6">
            <div class="grid gap-4">

                <!-- Full Name -->

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Full Name
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        v-model="form.name"
                        type="text"
                        :class="[
                            'w-full rounded-md border px-3 py-2',
                            hasError('name')
                            ? 'border-red-500 focus:border-red-500 focus:ring-1 focus:ring-red-500'
                            : 'border-border'
                        ]"
                    >

                    <p
                        v-if="getError('name')"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ getError('name') }}
                    </p>
                </div>

                <!-- Email -->

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Email
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        v-model="form.email"
                        type="email"
                        :class="[
                            'w-full rounded-md border px-3 py-2',
                            hasError('email')
                                ? 'border-red-500 focus:border-red-500 focus:ring-1 focus:ring-red-500'
                                : 'border-border'
                        ]"
                    />

                    <p
                        v-if="getError('email')"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ getError('email') }}
                    </p>
                </div>

                <!-- Citizenship Number -->

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Citizenship Number
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        v-model="form.citizen_number"
                        type="text"
                        :class="[
                            'w-full rounded-md border px-3 py-2',
                            hasError('citizen_number')
                                ? 'border-red-500 focus:border-red-500 focus:ring-1 focus:ring-red-500'
                                : 'border-border'
                        ]"
                    />

                    <p
                        v-if="getError('citizen_number')"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ getError('citizen_number') }}
                    </p>
                </div>

                <!-- Gender -->

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Gender
                        <span class="text-red-500">*</span>
                    </label>

                   <select
                        v-model="form.gender"
                        :class="[
                            'w-full rounded-md border px-3 py-2',
                            hasError('gender')
                                ? 'border-red-500 focus:border-red-500 focus:ring-1 focus:ring-red-500'
                                : 'border-border'
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
                        v-if="getError('gender')"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ getError('gender') }}
                    </p>
                </div>

                <!-- Address -->

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Address
                        <span class="text-red-500">*</span>
                    </label>

                    <textarea
                        v-model="form.address"
                        :class="[
                            'w-full rounded-md border px-3 py-2',
                            hasError('address')
                                ? 'border-red-500 focus:border-red-500 focus:ring-1 focus:ring-red-500'
                                : 'border-border'
                        ]"
                    />

                    <p
                        v-if="getError('address')"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ getError('address') }}
                    </p>
                </div>

            </div>
        </div>

        <!-- Footer -->

        <div
            class="flex items-center justify-between border-t border-border px-6 py-4"
        >
            <div>
                <button
                    v-if="props.user?.id"
                    type="button"
                    class="rounded-md border border-slate-200 bg-slate-100 px-4 py-2 text-sm font-medium transition hover:bg-slate-200 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="resettingPassword"
                    @click="resetPassword"
                >
                    <i class="fa-solid fa-key mr-2" />

                    {{
                        resettingPassword
                            ? 'Resetting...'
                            : 'Reset Password'
                    }}
                </button>
            </div>

            <div class="flex gap-2">
                <button
                    type="button"
                    class="rounded-md border border-slate-300 px-4 py-2 transition hover:bg-slate-50"
                    :disabled="form.processing"
                    @click="emit('close')"
                >
                    Cancel
                </button>

                <button
                    type="button"
                    class="rounded-md bg-primary px-4 py-2 text-white transition disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="form.processing"
                    @click="submit"
                >
                    <i
                        v-if="form.processing"
                        class="fa-solid fa-spinner fa-spin mr-2"
                    />

                    {{
                        form.processing
                            ? 'Saving...'
                            : 'Save'
                    }}
                </button>
            </div>
        </div>
    </Modal>
</template>