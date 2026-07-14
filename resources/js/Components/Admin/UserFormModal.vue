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
    business_unit: string
    date_of_joining: string
    nationality: string
}

const props = defineProps<{
    show: boolean
    title: string
    user?: User |null
    serverErrors: Record<string, string>
    businessUnitOptions: string[]
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
    role: 'employee',
    address: '',
    business_unit: '',
    date_of_joining: '',
    nationality_type:
        props.user?.nationality &&
        props.user.nationality.toLowerCase() !== 'indonesian'
            ? 'foreigner'
            : 'indonesian',

    nationality:
        props.user?.nationality &&
        props.user.nationality.toLowerCase() !== 'indonesian'
            ? props.user.nationality
            : '',

    citizen_number: props.user?.citizen_number ?? '',
})

const resettingPassword = ref(false)

const errors = reactive({
    name: '',
    email: '',
    citizen_number: '',
    address: '',
    business_unit: '',
    nationality_type: '',
    nationality: '',
    date_of_joining: '',
})

watch(
    () => props.user,
    (user) => {

        console.log(user);
        

        form.defaults({
            name: user?.name ?? '',
            email: user?.email ?? '',
            citizen_number: user?.citizen_number ?? '',
            address: user?.address ?? '',
            business_unit: user?.business_unit ?? '',
            date_of_joining: user?.date_of_joining ?? '',
            nationality_type: user?.nationality ? (user?.nationality.toLowerCase() === 'indonesian' ? 'indonesian' : 'foreigner') : 'indonesian',
            nationality: user?.nationality ?? '',
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
    errors.business_unit = ''
    errors.date_of_joining = ''
    errors.nationality_type = ''
    errors.nationality = ''

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

    if (!form.nationality_type) {

        errors.nationality_type =
            'Nationality Type is required.'

        valid = false

    }

    if (
        form.nationality_type === 'foreigner' &&
        !form.nationality.trim()
    ) {

        errors.nationality =
            'Nationality is required.'

        valid = false

    }

    if (!form.citizen_number.trim()) {

        errors.citizen_number =
            form.nationality_type === 'indonesian'
                ? 'Citizenship Number is required.'
                : 'Passport ID is required.'

        valid = false

    } else if (
        form.nationality_type === 'indonesian' &&
        !/^\d{15,16}$/.test(form.citizen_number)
    ) {

        errors.citizen_number =
            'Please enter a valid Indonesian National Identity Number (KTP).'

        valid = false

    } else if (
        form.nationality_type === 'foreigner' &&
        form.citizen_number.length > 10
    ) {

        errors.citizen_number =
            'Passport ID must not exceed 10 characters.'

        valid = false

    }

    if (!form.citizen_number.trim()) {

        errors.citizen_number =
            'Citizenship Number is required.'

        valid = false

    }

    if (!form.business_unit) {

        errors.business_unit =
            'Business Unit is required.'

        valid = false

    }

    if (!form.date_of_joining) {

        errors.date_of_joining =
            'Date of Joining is required.'

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

function onCitizenNumberInput(event: Event) {
    const input = event.target as HTMLInputElement

    if (form.nationality_type === 'indonesian') {
        input.value = input.value.replace(/\D/g, '').slice(0, 16)
    } else {
        input.value = input.value.slice(0, 10)
    }

    form.citizen_number = input.value
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

watch(() => form.business_unit, () => {
    errors.business_unit = ''
    form.clearErrors('business_unit')
})
watch(() => form.date_of_joining, () => {
    errors.date_of_joining = ''
    form.clearErrors('date_of_joining')
})

watch(() => form.address, () => {
    errors.address = ''
    form.clearErrors('address')
})

// watch(
//     () => form.nationality_type,
//     (value) => {
//         if (value === 'indonesian') {
//             form.nationality = ''
//         }
//     }
// )

watch(() => form.name, () => errors.name = '')
watch(() => form.email, () => errors.email = '')
watch(() => form.citizen_number, () => errors.citizen_number = '')
watch(() => form.address, () => errors.address = '')
watch(() => form.business_unit, () => errors.business_unit = '')
watch(() => form.date_of_joining, () => errors.date_of_joining = '')
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

                <!-- Nationality -->

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Nationality
                        <span class="text-red-500">*</span>
                    </label>

                    <select
                        v-model="form.nationality_type"
                        class="w-full rounded-md border border-border px-3 py-2"
                    >
                        <option value="indonesian">
                            Indonesian
                        </option>

                        <option value="foreigner">
                            Foreigner
                        </option>
                    </select>
                </div>

                <div v-if="form.nationality_type === 'foreigner'">
                    <input
                        v-model="form.nationality"
                        type="text"
                        :class="[
                            'w-full rounded-md border px-3 py-2',
                            hasError('nationality')
                                ? 'border-red-500 focus:border-red-500 focus:ring-1 focus:ring-red-500'
                                : 'border-border'
                        ]"
                    />

                    <p
                        v-if="getError('nationality')"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ getError('nationality') }}
                    </p>
                </div>

                <!-- Citizenship Number -->

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        {{
                            form.nationality_type  === 'indonesian'
                                ? 'Citizenship Number (KTP)'
                                : 'Passport ID'
                        }}
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        v-model="form.citizen_number"
                        type="text"
                        :maxlength="form.nationality_type === 'indonesian' ? 16 : 10"
                        @input="onCitizenNumberInput"
                        :class="[
                            'w-full rounded-md border px-3 py-2',
                            hasError('citizen_number')
                                ? 'border-red-500 focus:border-red-500 focus:ring-1 focus:ring-red-500'
                                : 'border-border'
                        ]"
                    />

                    <p
                        v-if="form.nationality_type === 'indonesian'"
                        class="mt-1 text-xs text-slate-500"
                    >
                        Must contain valid Indonesian National Identity Number (KTP).
                    </p>

                    <p
                        v-else
                        class="mt-1 text-xs text-slate-500"
                    >
                        Maximum 10 characters.
                    </p>

                    <p
                        v-if="getError('citizen_number')"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ getError('citizen_number') }}
                    </p>
                </div>
                

                <!-- Business Unit -->
                <div :class="[
                        'flex flex-col gap-2']">
                    <label class="text-sm font-medium text-slate-700">
                        Business Unit
                        <span class="text-red-500">*</span>
                    </label>

                    <select
                        v-model="form.business_unit"
                        class="rounded-md border border-border px-3 py-2 text-sm min-w-56"
                    >
                        <option value="">
                            All Business Unit
                        </option>

                        <option
                            v-for="item in businessUnitOptions"
                            :key="item"
                            :value="item"
                        >
                            {{ item }}
                        </option>
                    </select>

                    <p
                        v-if="getError('business_unit')"
                        class="text-xs text-red-500"
                    >
                        {{ getError('business_unit') }}
                    </p>
                </div>

                <!-- Date Of Join -->

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Date Of Join
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        v-model="form.date_of_joining"
                        type="date"
                        min="1900-01-01"
                        max="9999-12-31"
                        :class="[
                            'w-full rounded-md border px-3 py-2 text-sm',
                            hasError('date_of_joining')
                                ? 'border-red-500 bg-red-50'
                                : 'border-border'
                        ]"
                    />

                    <p
                        v-if="getError('date_of_joining')"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ getError('date_of_joining') }}
                    </p>
                </div>

                <!-- Address -->

                <div>
                    <label class="mb-1 block text-sm font-medium">
                        Permanent Address
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
                        class="text-xs text-red-500"
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