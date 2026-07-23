<script setup lang="ts">
import { computed, watch, watchEffect } from 'vue'
import SearchSelect from '@/Components/SearchSelect.vue'
import MultiSelect from '@/Components/MultiSelect.vue'
import { locales } from '@/Config/locales'

interface FieldOption {
    value: string
    label: string
    requires?: Field[]
}

interface Field {
    key: string
    label: string

    // Guidance rendered under the input, already resolved to the active
    // locale by the parent. Not part of the declaration's exported answer.
    hint?: string

    type?: 'text' | 'select' | 'number' | 'date' | 'date_range' | 'year'
    min?: number
    max?: number
    multiple?: boolean
    disabled?: boolean
    options?: FieldOption[]
}

const model = defineModel<Record<string, any>[]>({
    default: () => [],
})

const props = defineProps<{
    fields: Field[]
    questionKey: string
    errors: Record<string, string>

    // Follows the declaration form's chosen language.
    locale?: 'en' | 'id'

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

const t = computed(() => locales[props.locale ?? 'en'])

/**
 * The holding company. No entry in `companies` is labelled with it -- every
 * company_name carries one of the operating units instead -- so filtering on
 * it the normal way leaves the list empty. Its people can declare an interest
 * in any entity in the group, so they get the full list.
 */
const GROUP_WIDE_BUSINESS_UNIT = 'KPN Corporation'

/**
 * Companies selectable for a business unit. One company can belong to several,
 * hence the comma-separated `business_unit`.
 */
function companiesFor(businessUnit: string) {
    if (businessUnit === GROUP_WIDE_BUSINESS_UNIT) {
        return props.companies
    }

    return props.companies.filter(company =>
        company.business_unit
            .split(',')
            .map(value => value.trim())
            .includes(businessUnit),
    )
}

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

            const options = companiesFor(row.business_unit)
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

// Options for a multi-select field (currently only "company"),
// shaped for the MultiSelect component ({ code, name }).
function getMultiOptions(
    field: Field,
    row: Record<string, any>,
) {
    if (field.key !== 'company' || !row.business_unit) {
        return []
    }

    return companiesFor(row.business_unit)
        .map(company => ({
            code: company.code,
            name: company.name,
        }))
}

function onSelectChange(
    row: Record<string, any>,
    field: Field,
    index: number,
) {
    if (field.key === 'business_unit') {
        // Reset the dependent company selection when the unit changes.
        row.company = []
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

// "from" error: required / server error only. The start date may be any
// date (including the future); it just has to be a valid value.
function fromDateError(
    index: number,
    row: Record<string, any>,
    field: Field,
): string | undefined {
    return getError(index, `${field.key}_from`)
}

// Combined "to" error: required / server error, or the ordering check.
// The end date may be in the future — it only has to be >= the start date.
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
        return t.value.validation.dateRange
    }

    return undefined
}
function onInput(index: number, fieldKey: string) {
    const key = `responses.${props.questionKey}.details.${index}.${fieldKey}`
    emit('clearError', key)  // ← tell parent to clear this error
}

// Chrome's native date input lets you type a 6-digit year straight into the
// year segment (it caps at 275760), which then reaches the server as a date
// MySQL cannot store. Bound the field to a real 4-digit year.
const MIN_DATE = '1900-01-01'
const MAX_DATE = '9999-12-31'

function onDateInput(
    row: Record<string, any>,
    fieldKey: string,
    index: number,
) {
    const value = row[fieldKey]

    if (typeof value === 'string' && value !== '') {
        const [year, month = '01', day = '01'] = value.split('-')

        // Keep the month/day the user already picked; only the year overflows.
        if (year && year.length > 4) {
            row[fieldKey] = `9999-${month}-${day}`
        }
    }

    onInput(index, fieldKey)
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

    // Accept both "0,2" and "0.2": normalise comma to dot and keep only
    // digits and a single decimal point.
    let cleaned = String(raw)
        .replace(',', '.')
        .replace(/[^\d.]/g, '')

    const firstDot = cleaned.indexOf('.')

    if (firstDot !== -1) {
        cleaned =
            cleaned.slice(0, firstDot + 1)
            + cleaned.slice(firstDot + 1).replace(/\./g, '')
    }

    // Clamp to min/max when it is a complete number.
    const value = Number(cleaned)

    if (! Number.isNaN(value) && cleaned !== '' && cleaned !== '.') {
        if (field.max !== undefined && value > field.max) {
            cleaned = String(field.max)
        } else if (field.min !== undefined && value < field.min) {
            cleaned = String(field.min)
        }
    }

    row[field.key] = cleaned

    onInput(index, field.key)
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

                    <!-- Multi Select -->

                    <MultiSelect
                        v-if="field.type === 'select' && field.multiple"
                        :model-value="Array.isArray(row[field.key]) ? row[field.key] : []"
                        :options="getMultiOptions(field, row)"
                        :placeholder="t.common.selectPlaceholder"
                        @update:modelValue="(val) => {
                            row[field.key] = val
                            onInput(index, field.key)
                        }"
                    />

                    <!-- Select -->

                    <SearchSelect
                        v-else-if="field.type === 'select'"
                        v-model="row[field.key]"
                        :options="getOptions(field, row)"
                        :placeholder="t.common.selectPlaceholder"
                        :disabled="field.disabled"
                        @update:modelValue="
                            onSelectChange(row, field, index)
                        "
                    >

                        <option value="" selected disabled>
                            {{ t.common.selectPlaceholder }}
                        </option>

                        <option
                            v-for="option in getOptions(field, row)"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>

                    </SearchSelect>

                    <!-- Date Range -->

                    <div
                        v-else-if="field.type === 'date_range'"
                        class="grid grid-cols-[1fr_auto_1fr] gap-2 items-end"
                    >

                        <div>

                            <input
                                v-model="row[`${field.key}_from`]"
                                type="date"
                                :min="MIN_DATE"
                                :max="MAX_DATE"
                                :class="[
                                    'w-full rounded-md border px-3 py-2 text-sm',
                                    fromDateError(index, row, field)
                                        ? 'border-red-500 bg-red-50'
                                        : 'border-border'
                                ]"
                                @input="onDateInput(row, `${field.key}_from`, index)"
                            >

                        </div>

                        <div class="pb-2 text-sm text-slate-500">
                            to
                        </div>

                        <div>

                            <input
                                v-model="row[`${field.key}_to`]"
                                type="date"
                                :min="row[`${field.key}_from`] || MIN_DATE"
                                :max="MAX_DATE"
                                :disabled="row[`${field.key}_current`]"
                                :class="[
                                    'w-full rounded-md border px-3 py-2 text-sm',
                                    row[`${field.key}_current`] && 'bg-slate-100',
                                    toDateError(index, row, field)
                                        ? 'border-red-500 bg-red-50'
                                        : 'border-border'
                                ]"
                                @input="onDateInput(row, `${field.key}_to`, index)"
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
                                {{ t.common.current }}
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
                            @change="onSelectChange(row, field, index)"
                        >
                            <option value="" disabled>
                                {{ t.repeater.selectYear }}
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
                        type="text"
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

                    <!-- Guidance under the input. Applies to every field type,
                         so it sits outside the type-specific branches above. -->
                    <p
                        v-if="field.hint"
                        class="mt-1 text-xs text-slate-500"
                    >
                        {{ field.hint }}
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
                {{ t.repeater.remove }}
            </button>
        </div>

        <button
            type="button"
            class="rounded-md border border-primary px-4 py-2 text-sm font-medium text-primary hover:bg-primary/5"
            @click="addRow"
        >
            {{ t.repeater.addMore }}
        </button>
    </div>
</template>