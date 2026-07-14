<script setup lang="ts">
import { watch, watchEffect } from 'vue'

interface FieldOption {
    value: string
    label: string
    requires?: Field[]
}

interface Field {
    key: string
    label: string
    type?: 'text' | 'select' | 'number' | 'date' | 'date_range' | 'year'
    options?: FieldOption[]
}

const model = defineModel<Record<string, any>[]>({
    default: () => [],
})

const props = defineProps<{
    fields: Field[]
    questionKey: string
    errors: Record<string, string>

    businessUnits: {
        code: string
        name: string
    }[]

    companies: {
        code: string
        name: string
        business_unit: string
    }[]

    departments: {
        code: string
        name: string
        business_unit: string
    }[]
}>()

function getOptions(
    field: Field,
    row: Record<string, any>,
) {


    switch (field.key) {

        case 'business_unit':
            return props.businessUnits.map(item => ({
                value: item.code,
                label: item.name,
            }))

        case 'company': {

            if (!row.business_unit) {
                return []
            }

            const options = props.companies
                .filter(company =>
                    company.business_unit
                        .split(',')
                        .map(value => value.trim())
                        .includes(row.business_unit)
                )
                .map(company => ({
                    value: company.code,
                    label: company.name,
                }))

            if (
                row.company &&
                !options.some(option => option.value === row.company)
            ) {
                row.company = ''
            }

            return options
        }

        case 'department': {

            if (!row.business_unit) {
                return []
            }

            const options = props.departments
                .filter(department =>
                    department.business_unit === row.business_unit
                )
                .map(department => ({
                    value: department.code,
                    label: department.name,
                }))

            if (
                row.department &&
                !options.some(option => option.value === row.department)
            ) {
                row.department = ''
            }

            return options
        }
        

        default:
            return field.options ?? []
    }
}

function onSelectChange(
    row: Record<string, any>,
    field: Field,
    index: number,
) {

    switch (field.key) {

        case 'business_unit':

            row.company = ''
            row.department = ''

            break

        case 'company':

            break
    }

    onInput(index, field.key)
}

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
function onInput(index: number, fieldKey: string) {
    const key = `responses.${props.questionKey}.details.${index}.${fieldKey}`
    emit('clearError', key)  // ← tell parent to clear this error
}

const currentYear = new Date().getFullYear()

const years = Array.from(
    { length: currentYear - 1980 + 1 },
    (_, index) => currentYear - index
)

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
                        :key="
                            field.key === 'company'
                                ? `company-${row.business_unit}`
                                : field.key === 'department'
                                    ? `department-${row.business_unit}`
                                    : field.key
                        "
                        v-model="row[field.key]"
                        :class="[
                            'w-full rounded-md border px-3 py-2 text-sm',
                            getError(index, field.key)
                                ? 'border-red-500 bg-red-50'
                                : 'border-border'
                        ]"
                        @change="onSelectChange(row, field, index)"
                    >

                        <option value="" selected disabled>
                            Select...
                        </option>

                        <option
                            v-for="option in getOptions(field, row)"
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
                                :class="[
                                    'w-full rounded-md border px-3 py-2 text-sm',
                                    getError(index, `${field.key}_from`)
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
                                :disabled="row[`${field.key}_current`]"
                                :class="[
                                    'w-full rounded-md border px-3 py-2 text-sm',
                                    row[`${field.key}_current`] && 'bg-slate-100',
                                    getError(index, `${field.key}_to`)
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
                            @change="onSelectChange(row, field, index)"
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