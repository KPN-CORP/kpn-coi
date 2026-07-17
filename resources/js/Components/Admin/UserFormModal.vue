<script setup lang="ts">
import { watch, reactive, ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Modal from '@/Components/UI/Modal.vue'
import SearchSelect from '@/Components/SearchSelect.vue'
import countries from '@/Config/countries.json'
import Swal from 'sweetalert2'

// Stored value is the demonym string ("Malaysian"), matching existing data.
// Deduplicated: some nationalities cover more than one country (Congolese,
// Dominican), and a repeated value would collide as a v-for key.
// Indonesia is excluded -- that is the other radio.
const nationalityOptions = [
    ...new Set(
        countries
            .filter(country => country.code !== 'ID')
            .map(country => country.country),
    ),
]
    .sort((a, b) => a.localeCompare(b))
    .map(nationality => ({
        value: nationality,
        label: nationality,
    }))

interface User {
    id?: number
    name: string
    email: string
    citizen_number: string
    address: string
    business_unit: string
    location_id: number | null
    date_of_joining: string
    nationality: string
}

interface LocationOption {
    id: number
    business_unit: string | null
    label: string
}

const props = defineProps<{
    show: boolean
    title: string
    user?: User |null
    serverErrors: Record<string, string>
    businessUnitOptions: string[]
    locationOptions: LocationOption[]
}>()

// Locations belong to a business unit, so the list only makes sense once one
// is picked. The server already translated company_name into the app's
// business unit naming, so this is a straight match.
const locationsForBusinessUnit = computed(() =>
    props.locationOptions
        .filter(location => location.business_unit === form.business_unit)
        .map(location => ({
            value: location.id,
            label: location.label,
        })),
)

const emit = defineEmits<{
    close: []
    save: [value: User]
    resetPassword: []
}>()

function isIndonesian(nationality?: string | null): boolean {
    const value = nationality?.trim().toLowerCase()

    // "Indonesian" is legacy data written before nationality became a country
    // name; still recognised so existing records open as Indonesian.
    return value === 'indonesia' || value === 'indonesian'
}

const form = useForm({
    employee_id: '',
    name: '',
    email: '',
    role: 'employee',
    address: '',
    business_unit: '',
    location_id: props.user?.location_id ?? null,
    date_of_joining: '',
    nationality_type: isIndonesian(props.user?.nationality)
        ? 'Indonesian'
        : 'foreigner',

    nationality: isIndonesian(props.user?.nationality)
        ? ''
        : (props.user?.nationality ?? ''),

    citizen_number: props.user?.citizen_number ?? '',
})

const resettingPassword = ref(false)

const errors = reactive({
    name: '',
    email: '',
    citizen_number: '',
    address: '',
    business_unit: '',
    location_id: '',
    nationality_type: '',
    nationality: '',
    date_of_joining: '',
})

// Per-type scratch values. Switching between Indonesian and Foreigner keeps
// what was typed for the other type, so toggling back and forth before saving
// does not silently discard it.
const stash = reactive({
    Indonesian: {
        citizen_number: '',
    },

    foreigner: {
        citizen_number: '',
        nationality: '',
    },
})

function resetFromUser(user?: User | null) {

    const isLocal =
        isIndonesian(user?.nationality) || !user?.nationality

    form.defaults({
        name: user?.name ?? '',
        email: user?.email ?? '',
        citizen_number: user?.citizen_number ?? '',
        address: user?.address ?? '',
        business_unit: user?.business_unit ?? '',
        location_id: user?.location_id ?? null,
        date_of_joining: user?.date_of_joining ?? '',
        nationality_type: isLocal ? 'Indonesian' : 'foreigner',
        nationality: isLocal ? '' : (user?.nationality ?? ''),
    })

    form.reset()

    form.clearErrors()

    // Seed the stash from the saved record so the first switch away and back
    // restores the stored values rather than blanks.
    stash.Indonesian.citizen_number = isLocal
        ? (user?.citizen_number ?? '')
        : ''

    stash.foreigner.citizen_number = isLocal
        ? ''
        : (user?.citizen_number ?? '')

    stash.foreigner.nationality = isLocal
        ? ''
        : (user?.nationality ?? '')

    resettingPassword.value = false

    Object.keys(errors).forEach(
        key => errors[key as keyof typeof errors] = ''
    )
}

// Reopening the modal for the same user does not change props.user, so
// discarded edits would survive into the next open. Reset on every open.
watch(
    () => [props.show, props.user],
    () => {
        if (props.show) {
            resetFromUser(props.user)
        }
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
        !form.nationality?.trim()
    ) {

        errors.nationality =
            'Nationality is required.'

        valid = false

    }

    if (!form.citizen_number.trim()) {

        errors.citizen_number =
            form.nationality_type === 'Indonesian'
                ? 'Citizenship Number is required.'
                : 'Passport ID is required.'

        valid = false

    } else if (
        form.nationality_type === 'Indonesian' &&
        !/^\d{15,16}$/.test(form.citizen_number)
    ) {

        errors.citizen_number =
            'Please input correct Citizenship Number.'

        valid = false

    } else if (
        form.nationality_type === 'foreigner' &&
        form.citizen_number.length > 10
    ) {

        errors.citizen_number =
            'Passport ID must not exceed 10 characters.'

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

    if (form.nationality_type === 'Indonesian') {
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

/**
 * Same reasoning as setNationalityType: driven from @change, not a watcher, so
 * that form.reset() does not wipe the location it just restored.
 */
function setBusinessUnit(value: string) {

    if (form.business_unit === value) {
        return
    }

    form.business_unit = value

    // A location from the previous unit would not be valid under the new one.
    form.location_id = null

    errors.business_unit = ''
    errors.location_id = ''

    form.clearErrors('business_unit', 'location_id')
}

/**
 * Driven from the radio's @change rather than a watcher: a watcher also fires
 * when form.reset() rewrites the type programmatically, which would stash the
 * freshly reset values under the wrong type.
 */
function setNationalityType(value: string) {

    const current = form.nationality_type

    if (current === value) {
        return
    }

    // Keep what is being left behind...
    if (current === 'Indonesian') {
        stash.Indonesian.citizen_number = form.citizen_number
    } else {
        stash.foreigner.citizen_number = form.citizen_number
        stash.foreigner.nationality = form.nationality
    }

    form.nationality_type = value

    // ...and bring back whatever that type had before.
    if (value === 'Indonesian') {
        form.citizen_number = stash.Indonesian.citizen_number
        form.nationality = ''
    } else {
        form.citizen_number = stash.foreigner.citizen_number
        form.nationality = stash.foreigner.nationality
    }

    errors.nationality_type = ''
    errors.nationality = ''
    errors.citizen_number = ''

    form.clearErrors(
        'nationality_type',
        'nationality',
        'citizen_number'
    )
}

watch(() => form.nationality, () => {
    errors.nationality = ''
    form.clearErrors('nationality')
})

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

                    <div class="grid grid-cols-2 gap-3">
                        <label
                            v-for="option in [
                                { value: 'Indonesian', label: 'Indonesian' },
                                { value: 'foreigner', label: 'Foreigner' },
                            ]"
                            :key="option.value"
                            class="flex cursor-pointer items-center gap-2 rounded-md border px-4 py-3 text-sm transition"
                            :class="form.nationality_type === option.value
                                ? 'border-primary bg-red-50 font-bold text-primary'
                                : 'border-border hover:bg-red-50 hover:text-primary'"
                        >
                            <input
                                type="radio"
                                name="nationality_type"
                                :value="option.value"
                                :checked="form.nationality_type === option.value"
                                class="h-4 w-4 border-border text-primary focus:ring-primary"
                                @change="setNationalityType(option.value)"
                            >
                            {{ option.label }}
                        </label>
                    </div>

                    <p
                        v-if="getError('nationality_type')"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ getError('nationality_type') }}
                    </p>
                </div>

                <!--
                    Foreigners need both country and passport, so they share a
                    row. Indonesians only have the KTP field, which keeps the
                    full width.
                -->
                <div
                    :class="form.nationality_type === 'foreigner'
                        ? 'grid gap-4 sm:grid-cols-2'
                        : ''"
                >
                    <div v-if="form.nationality_type === 'foreigner'">
                        <label class="mb-1 block text-sm font-medium">
                            Country of Nationality
                            <span class="text-red-500">*</span>
                        </label>

                        <SearchSelect
                            v-model="form.nationality"
                            :options="nationalityOptions"
                            placeholder="Select nationality..."
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
                                form.nationality_type === 'Indonesian'
                                    ? 'Citizenship Number (KTP)'
                                    : 'Passport ID'
                            }}
                            <span class="text-red-500">*</span>
                        </label>

                        <input
                            v-model="form.citizen_number"
                            type="text"
                            :maxlength="form.nationality_type === 'Indonesian' || form.nationality_type === 'indonesian' ? 16 : 10"
                            @input="onCitizenNumberInput"
                            :class="[
                                'w-full rounded-md border px-3 py-2',
                                hasError('citizen_number')
                                    ? 'border-red-500 focus:border-red-500 focus:ring-1 focus:ring-red-500'
                                    : 'border-border'
                            ]"
                        />

                        <p
                            v-if="form.nationality_type === 'Indonesian'"
                            class="mt-1 text-xs text-slate-500"
                        >
                            Must contain 16 digits.
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
                </div>

                <!-- Business Unit -->
                <div :class="[
                        'flex flex-col gap-2']">
                    <label class="text-sm font-medium text-slate-700">
                        Business Unit
                        <span class="text-red-500">*</span>
                    </label>

                    <select
                        :value="form.business_unit"
                        class="w-full rounded-md border border-border px-3 py-2 text-sm"
                        @change="setBusinessUnit(($event.target as HTMLSelectElement).value)"
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

                <!--
                    Location is scoped to the selected business unit: nothing to
                    show before one is picked, and some units (KPN Sugar) have no
                    locations at all, so the field is hidden rather than shown empty.
                -->
                <div v-if="locationsForBusinessUnit.length">
                    <label class="mb-1 block text-sm font-medium">
                        Location
                    </label>

                    <SearchSelect
                        v-model="form.location_id"
                        :options="locationsForBusinessUnit"
                        placeholder="Select location..."
                    />

                    <p
                        v-if="getError('location_id')"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ getError('location_id') }}
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