<script setup lang="ts">
import { watch } from 'vue'

interface FieldOption {
    value: string
    label: string
    requires?: Field[]
}

interface Field {
    key: string
    label: string
    type?: 'text' | 'select' | 'number' | 'date' | 'date_range' | 'year'
    min?: number
    max?: number
    options?: FieldOption[]
}

const model = defineModel<Record<string, any>[]>({
    default: () => [],
})

const props = defineProps<{
    fields: Field[]
    questionKey: string
    errors: Record<string, string>
}>()

const emit = defineEmits<{
    clearError: [key: string]
}>()

// Watch model changes and clear the matching error
watch(
    model,
    (newVal) => {

        newVal.forEach((row, index) => {

            props.fields.forEach(field => {

                const value = row[field.key]

                if (
                    value !== null &&
                    value !== undefined &&
                    value !== ''
                ) {

                    emit(
                        'clearError',
                        `responses.${props.questionKey}.details.${index}.${field.key}`
                    )

                }

            })

        })

    },
    {
        deep: true,
    },
)

function isVisible(
    row: Record<string, any>,
    field: Field,
) {
    return true
}

function getRequiredFields(
    field: Field,
    value: string,
) {
    return field.options
        ?.find(
            option => option.value === value
        )
        ?.requires ?? []
}

function addRow() { model.value.push({}) }
function removeRow(index: number) { model.value.splice(index, 1) }
function getError(index: number, field: string): string | undefined {
    return props.errors[`responses.${props.questionKey}.details.${index}.${field}`]
}

// True when a date_range's "to" is set and earlier than "from" (and not "current").
function isDateRangeReversed(
    row: Record<string, any>,
    field: Field,
): boolean {
    return (
        !row[`${field.key}_current`] &&
        !!row[`${field.key}_from`] &&
        !!row[`${field.key}_to`] &&
        row[`${field.key}_to`] < row[`${field.key}_from`]
    )
}

function isFuture(value?: string): boolean {
    return !!value && value > today
}

// "from" error: required / server error, or a future date.
function fromDateError(
    index: number,
    row: Record<string, any>,
    field: Field,
): string | undefined {
    const existing = getError(index, `${field.key}_from`)

    if (existing) {
        return existing
    }

    if (isFuture(row[`${field.key}_from`])) {
        return 'Start date cannot be in the future.'
    }

    return undefined
}

// Combined "to" error: required / server error, ordering, or future date.
function toDateError(
    index: number,
    row: Record<string, any>,
    field: Field,
): string | undefined {
    const existing = getError(index, `${field.key}_to`)

    if (existing) {
        return existing
    }

    if (isDateRangeReversed(row, field)) {
        return 'End date cannot be earlier than start date.'
    }

    if (
        !row[`${field.key}_current`]
        && isFuture(row[`${field.key}_to`])
    ) {
        return 'End date cannot be in the future.'
    }

    return undefined
}
function onInput(index: number, fieldKey: string) {
    const key = `responses.${props.questionKey}.details.${index}.${fieldKey}`
    emit('clearError', key)  // ← tell parent to clear this error
}

function onNumberInput(
    row: Record<string, any>,
    field: Field,
    index: number,
) {
    const raw = row[field.key]

    if (raw === '' || raw === null || raw === undefined) {
        row[field.key] = ''
        onInput(index, field.key)
        return
    }

    let value = Number(raw)

    if (Number.isNaN(value)) {
        row[field.key] = ''
        onInput(index, field.key)
        return
    }

    if (field.max !== undefined && value > field.max) {
        value = field.max
    }

    if (field.min !== undefined && value < field.min) {
        value = field.min
    }

    row[field.key] = value

    onInput(index, field.key)
}

const currentYear = new Date().getFullYear()

const years = Array.from(
    { length: currentYear - 1980 + 1 },
    (_, index) => currentYear - index
)

// Local "today" as YYYY-MM-DD for date input max attributes.
const today = (() => {
    const d = new Date()
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')

    return `${d.getFullYear()}-${month}-${day}`
})()
</script>

<template>
    <div class="mt-4 rounded-md border border-dashed border-slate-300 bg-slate-50 p-4">
        <div
            v-for="(row, index) in model"
            :key="index"
            class="mb-4 border-b border-slate-200 pb-4 last:mb-0 last:border-0"
        >
            <div class="grid gap-4 md:grid-cols-2">
                <div
                    v-for="field in fields"
                    :key="field.key"
                >

                    <label class="mb-1 block text-xs font-semibold">
                        {{ field.label }}
                    </label>

                    <!-- Select -->

                    <select
                        v-if="field.type === 'select'"
                        v-model="row[field.key]"
                        :class="[
                            'w-full rounded-md border px-3 py-2 text-sm',
                            getError(index, field.key)
                                ? 'border-red-500 bg-red-50'
                                : 'border-border'
                        ]"
                        @change="onInput(index, field.key)"
                    >

                        <option value="" selected disabled>
                            Select...
                        </option>

                        <option
                            v-for="option in field.options"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>

                    </select>

                    <!-- Date Range -->

                    <div
                        v-else-if="field.type === 'date_range'"
                        class="grid grid-cols-[1fr_auto_1fr] gap-2 items-end"
                    >

                        <div>

                            <input
                                v-model="row[`${field.key}_from`]"
                                type="date"
                                :max="today"
                                :class="[
                                    'w-full rounded-md border px-3 py-2 text-sm',
                                    fromDateError(index, row, field)
                                        ? 'border-red-500 bg-red-50'
                                        : 'border-border'
                                ]"
                                @input="onInput(index, `${field.key}_from`)"
                            >

                        </div>

                        <div class="pb-2 text-sm text-slate-500">
                            to
                        </div>

                        <div>

                            <input
                                v-model="row[`${field.key}_to`]"
                                type="date"
                                :min="row[`${field.key}_from`] || undefined"
                                :max="today"
                                :disabled="row[`${field.key}_current`]"
                                :class="[
                                    'w-full rounded-md border px-3 py-2 text-sm',
                                    row[`${field.key}_current`] && 'bg-slate-100',
                                    toDateError(index, row, field)
                                        ? 'border-red-500 bg-red-50'
                                        : 'border-border'
                                ]"
                                @input="onInput(index, `${field.key}_to`)"
                            >

                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <input
                                :id="`${field.key}_current_${index}`"
                                v-model="row[`${field.key}_current`]"
                                type="checkbox"
                                @change="onInput(index, `${field.key}_current`)"
                            >

                            <label
                                :for="`${field.key}_current_${index}`"
                                class="text-sm"
                            >
                                Current
                            </label>
                        </div>

                        <p
                            v-if="fromDateError(index, row, field) || toDateError(index, row, field)"
                            class="col-span-3 mt-1 text-xs text-red-500"
                        >
                            {{ fromDateError(index, row, field) || toDateError(index, row, field) }}
                        </p>

                    </div>

                    <!-- Year -->

                    <div v-else-if="field.type === 'year'">
                        <select
                            v-model="row[field.key]"
                            :class="[
                                'w-full rounded-md border px-3 py-2 text-sm',
                                getError(index, field.key)
                                    ? 'border-red-500 bg-red-50'
                                    : 'border-border'
                            ]"
                            @change="onInput(index, field.key)"
                        >
                            <option value="" disabled>
                                Select year...
                            </option>

                            <option
                                v-for="year in years"
                                :key="year"
                                :value="year"
                            >
                                {{ year }}
                            </option>
                        </select>

                        <p
                            v-if="getError(index, field.key)"
                            class="mt-1 text-xs text-red-500"
                        >
                            {{ getError(index, field.key) }}
                        </p>
                    </div>

                    <!-- Number -->

                    <input
                        v-else-if="field.type === 'number'"
                        v-model="row[field.key]"
                        type="number"
                        :min="field.min"
                        :max="field.max"
                        step="any"
                        inputmode="decimal"
                        :class="[
                            'w-full rounded-md border px-3 py-2 text-sm',
                            getError(index, field.key)
                                ? 'border-red-500 bg-red-50'
                                : 'border-border'
                        ]"
                        @input="onNumberInput(row, field, index)"
                    >

                    <!-- Text -->

                    <input
                        v-else
                        v-model="row[field.key]"
                        type="text"
                        :class="[
                            'w-full rounded-md border px-3 py-2 text-sm',
                            getError(index, field.key)
                                ? 'border-red-500 bg-red-50'
                                : 'border-border'
                        ]"
                        @input="onInput(index, field.key)"
                    >

                    <p
                        v-if="getError(index, field.key)"
                        class="mt-1 text-xs text-red-500"
                    >
                        {{ getError(index, field.key) }}
                    </p>

                    <!-- Dynamic Requires -->

                    <div
                        v-for="requiredField in getRequiredFields(field, row[field.key])"
                        :key="requiredField.key"
                        class="mt-3"
                    >

                        <label class="mb-1 block text-xs font-semibold">
                            {{ requiredField.label }}
                        </label>

                        <input
                            v-model="row[requiredField.key]"
                            type="text"
                            class="w-full rounded-md border border-border px-3 py-2 text-sm"
                            @input="onInput(index, requiredField.key)"
                        >

                    </div>

                </div>
            </div>

            <button
                v-if="index > 0"
                type="button"
                class="mt-3 rounded-md bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700"
                @click="removeRow(index)"
            >
                Remove
            </button>
        </div>

        <button
            type="button"
            class="rounded-md border border-primary px-4 py-2 text-sm font-medium text-primary hover:bg-primary/5"
            @click="addRow"
        >
            + Add More
        </button>
    </div>
</template>