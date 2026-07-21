<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import Card from '@/Components/UI/Card.vue'
import EmployeeLayout from '@/Layouts/EmployeeLayout.vue'

import QuestionCard from '@/Components/Declaration/QuestionCard.vue'
import RepeaterSection from '@/Components/Declaration/RepeaterSection.vue'
import ConsentSection from '@/Components/Declaration/ConsentSection.vue'

import { Link, useForm, usePage, router } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import { route } from 'ziggy-js'
import Swal from 'sweetalert2'
import { useLocale } from '@/Composables/useLocale'

interface DeclarationData {
    name: string
    citizen_number: string
    address: string
}

interface CoiField {
    key: string
    type: string
    label: Record<'en' | 'id', string>
}

interface CoiQuestion {
    key: string
    title: Record<'en' | 'id', string>
    fields: CoiField[]
}

const props = defineProps<{
    locale: 'en' | 'id'
    draft?: any | null
    declaration: DeclarationData
    errors: Record<string, string>
    previousDeclaration: any | null
    businessUnits: { code: string; name: string }[]
    companies: { code: string; name: string; business_unit: string }[]
    departments: { code: string; name: string; business_unit: string }[]
}>()

// The whole form — chrome AND the COI questions/consent — follows the global
// EN/ID toggle. `locale` is the active dictionary; `currentLocale` is the
// 'en'|'id' string used to index the per-question title/label objects and to
// stamp the submitted declaration (drives the saved language + PDF).
const { t: locale, locale: currentLocale } = useLocale()

const submitted = ref(false)
const clientErrors = ref<Record<string, string>>({})


const page = usePage()

const coiQuestions = computed<CoiQuestion[]>(
    () => page.props.coiQuestions as CoiQuestion[]
)

const defaultResponses = Object.fromEntries(
    coiQuestions.value.map(question => [
        question.key,
        {
            answer: false,
            details: [],
        },
    ]),
)

const processingAction = ref<'draft' | 'submit' | null>(null)

const form = useForm({
    locale: props.locale,

    responses:
        props.draft?.data?.responses ??
        defaultResponses,

    consent: false,
})

const flash = computed(() => page.props.flash as {
    success?: string
    error?: string
})

const usePreviousData = async () => {
    if (!props.previousDeclaration?.responses?.length) {
        await Swal.fire({
            icon: 'info',
            title: locale.value.declaration.noPreviousTitle,
            text: locale.value.declaration.noPreviousText,
        })

        return
    }

    const result = await Swal.fire({
        title: locale.value.declaration.usePreviousTitle,
        text: locale.value.declaration.usePreviousText,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: locale.value.declaration.yesUseData,
        cancelButtonText: locale.value.common.cancel,
        confirmButtonColor: '#ab2f2b',
        reverseButtons: true,
    })

    if (!result.isConfirmed) {
        return
    }

    props.previousDeclaration.responses.forEach((response: any) => {
        Object.assign(
            form.responses[response.question_key],
            JSON.parse(JSON.stringify(response.response_value))
        )
    })

    await Swal.fire({
        icon: 'success',
        title: locale.value.declaration.dataLoadedTitle,
        text: locale.value.declaration.dataLoadedText,
        timer: 1500,
        showConfirmButton: false,
    })
}

const getOptions = (key: string) => {
    switch (key) {
        case 'business_unit':
            return props.businessUnits.map(item => ({
                value: item.code,
                label: item.name,
            }))

        case 'company':
            return props.companies.map(item => ({
                value: item.code,
                label: item.name,
            }))

        case 'department':
            return props.departments.map(item => ({
                value: item.code,
                label: item.name,
            }))

        default:
            return []
    }
}

watch(
    () => flash.value.error,
    (message) => {
        if (!message) return

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: message,
            showConfirmButton: false,
            timer: 3000,
        })
    },
    { immediate: true },
)

watch(
    () => Object.keys(form.responses).map(k => form.responses[k].answer),
    () => {
        // When answer flips to false, remove that question's errors
        for (const question of coiQuestions.value) {
            if (!form.responses[question.key].answer) {
                for (const key of Object.keys(clientErrors.value)) {
                    if (key.startsWith(`responses.${question.key}`)) {
                        delete clientErrors.value[key]
                    }
                }
            }
        }
    }
)

function isEmpty(value: unknown): boolean {
    if (Array.isArray(value)) {
        return value.length === 0
    }

    return value === null || value === undefined || value === ''
}

function hasEmptyFields() {

    for (const question of coiQuestions.value) {

        const response = form.responses[question.key]

        if (!response.answer) {
            continue
        }

        for (const detail of response.details) {

            for (const field of question.fields) {

                if (field.type === 'date_range') {

                    if (
                        isEmpty(detail[`${field.key}_from`]) ||
                        (
                            !detail[`${field.key}_current`] &&
                            isEmpty(detail[`${field.key}_to`])
                        )
                    ) {
                        return true
                    }

                    continue
                }

                if (field.type === 'year') {

                    if (isEmpty(detail[field.key])) {
                        return true
                    }

                    continue
                }

                if (isEmpty(detail[field.key])) {
                    return true
                }

                if (field.type === 'select' && field.options) {

                    const selected = field.options.find(
                        option => option.value === detail[field.key]
                    )

                    if (!selected?.requires) {
                        continue
                    }

                    for (const requiredField of selected.requires) {

                        if (requiredField.type === 'date_range') {

                            if (
                                isEmpty(detail[`${requiredField.key}_from`]) ||
                                (
                                    !detail[`${requiredField.key}_current`] &&
                                    isEmpty(detail[`${requiredField.key}_to`])
                                )
                            ) {
                                return true
                            }

                            continue
                        }

                        if (requiredField.type === 'year') {

                            if (isEmpty(detail[requiredField.key])) {
                                return true
                            }

                            continue
                        }

                        if (isEmpty(detail[requiredField.key])) {
                            return true
                        }
                    }
                }
            }
        }
    }

    return false
}

function validateForm(): boolean {

    let valid = true

    for (const question of coiQuestions.value) {

        const response = form.responses[question.key]

        if (!response.answer) {
            continue
        }

        response.details.forEach((detail, index) => {

            question.fields.forEach(field => {

                if (field.type === 'date_range') {

                    const fromKey =
                        `responses.${question.key}.details.${index}.${field.key}_from`

                    const toKey =
                        `responses.${question.key}.details.${index}.${field.key}_to`

                    if (!detail[`${field.key}_from`]) {

                        clientErrors.value[fromKey] =
                            locale.value.validation.required

                        valid = false
                    }

                    if (
                        !detail[`${field.key}_current`] &&
                        !detail[`${field.key}_to`]
                    ) {

                        clientErrors.value[toKey] =
                            locale.value.validation.required

                        valid = false
                    }

                    if (
                        !detail[`${field.key}_current`] &&
                        detail[`${field.key}_from`] &&
                        detail[`${field.key}_to`] &&
                        detail[`${field.key}_to`] < detail[`${field.key}_from`]
                    ) {

                        clientErrors.value[toKey] =
                            locale.value.validation.dateRange

                        valid = false
                    }

                    return
                }

                if (field.type === 'year') {

                    const key =
                        `responses.${question.key}.details.${index}.${field.key}`

                    if (!detail[field.key]) {

                        clientErrors.value[key] =
                            locale.value.validation.required

                        valid = false
                    }

                    return
                }

                const key =
                    `responses.${question.key}.details.${index}.${field.key}`

                const value = detail[field.key]

                if (isEmpty(value)) {

                    clientErrors.value[key] =
                        locale.value.validation.required

                    valid = false
                }

                if (
                    field.type === 'select' &&
                    field.options
                ) {

                    const selected = field.options.find(
                        option => option.value === value
                    )

                    if (selected?.requires) {

                        selected.requires.forEach(requiredField => {

                            const requiredKey =
                                `responses.${question.key}.details.${index}.${requiredField.key}`

                            const requiredValue =
                                detail[requiredField.key]

                            if (
                                requiredValue === null ||
                                requiredValue === undefined ||
                                requiredValue === ''
                            ) {

                                clientErrors.value[requiredKey] =
                                    locale.value.validation.required

                                valid = false
                            }

                        })

                    }

                }

            })

        })

    }

    return valid
}

function saveDraft() {
    processingAction.value = 'draft'

    form.locale = currentLocale.value

    form.post(route('employee.declarations.draft'), {
        preserveScroll: true,

        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: locale.value.declaration.draftSaved,
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true,
            }).then(() => {
                router.visit(route('employee.history'))
            })
        },

        onError: () => {
            processingAction.value = null
        },
    })
}

async function submit() {
    submitted.value = true

    processingAction.value = 'submit'

    clientErrors.value = {}

    if (hasEmptyFields()) {
        Swal.fire({
            icon: 'warning',
            title: locale.value.declaration.completeRequired,
        })

        processingAction.value = null
        return
    }

    if (!validateForm()) {
        processingAction.value = null
        return
    }

    const fullName = props.declaration.name

    const { isConfirmed } = await Swal.fire({
        icon: 'warning',
        title: locale.value.declaration.finalConfirmTitle,

        html: `
            <p class="mb-3">
                ${locale.value.declaration.finalConfirmCannotEdit}
            </p>

            <p class="mb-2">
                ${locale.value.declaration.finalConfirmType}
            </p>

            <p style="font-weight:600;margin-bottom:8px;">
                ${fullName}
            </p>

            <p style="font-size:12px;color:#6b7280;">
                ${locale.value.declaration.finalConfirmCase}
            </p>
        `,

        input: 'text',

        inputPlaceholder: locale.value.declaration.typeFullName,

        showCancelButton: true,

        confirmButtonText: locale.value.common.submit,

        cancelButtonText: locale.value.common.cancel,

        buttonsStyling: false,

        customClass: {
            confirmButton: 'btn-primary-custom ml-2',
            cancelButton: 'btn-secondary',
        },
        reverseButtons: true,

        didOpen: () => {
            const input = Swal.getInput()

            if (!input) {
                return
            }

            // Force the confirmation name to be typed by hand — no
            // copy / paste / cut / drag-drop or right-click paste.
            const block = (event: Event) => event.preventDefault()

            input.addEventListener('paste', block)
            input.addEventListener('copy', block)
            input.addEventListener('cut', block)
            input.addEventListener('drop', block)
            input.addEventListener('contextmenu', block)
        },

        preConfirm: (value) => {

            if (value?.trim() !== fullName) {

                Swal.showValidationMessage(
                    locale.value.declaration.nameMismatch
                )

                return false
            }

            return true
        },
    })

    if (!isConfirmed) {
        processingAction.value = null
        return
    }

    form.locale = currentLocale.value

    form.post(route('employee.declarations.submit'), {
        onFinish: () => {
            processingAction.value = null
        },

        onError: () => {
            processingAction.value = null
        },
    })
}

function onAnswerChanged(questionKey: string) {
    const response = form.responses[questionKey]

    if (response.answer) {
        if (response.details.length === 0) {
            response.details.push({})
        }
    } else {
        response.details = []
    }
}
</script>

<template>
    <EmployeeLayout>
        <PageHeader
            :title="locale.declaration.title"
            :description="locale.declaration.description"
        />

        <!-- Darwinbox Notice -->
        <Card class="mb-6 bg-slate-50 card-custom">

            <div class="alert alert-warning mb-6">
                <i
                    class="fa-solid fa-circle-info text-amber-600"
                />

                <span class="text-sm font-bold">
                    {{ locale.declaration.warning }}
                </span>
            </div>

        <!-- Employee Information -->

            <div class="grid gap-4 md:grid-cols-2 mb-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold">
                        {{ locale.declaration.fullname }}
                    </label>

                    <input
                        :value="props.declaration.name"
                        disabled
                        class="w-full rounded-md border border-border bg-slate-100 px-3 py-2"
                    >
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold">
                        {{ locale.declaration.ktp }}
                    </label>

                    <input
                        :value="props.declaration.citizen_number"
                        disabled
                        class="w-full rounded-md border border-border bg-slate-100 px-3 py-2"
                    >
                </div>
            </div>    
            <div class="grid gap-4 md:grid-cols-1">           

                <div>
                    <label class="mb-1 block text-xs font-semibold">
                        {{ locale.declaration.address }}
                    </label>

                    <textarea
                        :value="props.declaration.address"
                        disabled
                        rows="3"
                        class="w-full rounded-md border border-border bg-slate-100 px-3 py-2 resize-none"
                    />
                </div>
            </div>
        </Card>

        <!-- Prefill -->

        <div class="alert alert-info mb-3 shadow-sm">
            <div class="flex w-full items-center justify-between">
                <div class="flex items-center gap-2">
                    <p class="font-bold">
                        {{ locale.declaration.prefillTitle }}:
                    </p>

                    <p class="text-slate-500">
                        {{ locale.declaration.prefillDescription }}
                    </p>
                </div>

                <button
                    type="button"
                    class="btn-primary text-sm"
                    @click="usePreviousData"
                >
                    {{ locale.declaration.useData }}
                </button>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 mb-6 text-xs text-slate-600">
            <p class="font-semibold text-slate-800 mb-2">
                {{ locale.declaration.notesTitle }}
            </p>

            <ol class="list-decimal list-inside space-y-1">
                <li>
                    {{ locale.declaration.note1 }}
                </li>

                <li>
                    {{ locale.declaration.note2 }}
                </li>

                <li>
                    {{ locale.declaration.note3 }}
                </li>
            </ol>
        </div>

        <!-- Dynamic Questions -->

        <QuestionCard
            v-for="question in coiQuestions"
            :key="question.key"
            :title="question.title[currentLocale]"
        >
            <div class="flex gap-6">
                <label class="flex items-center gap-2">
                    <input
                        v-model="form.responses[question.key].answer"
                        :value="false"
                        type="radio"
                        @change="onAnswerChanged(question.key)"
                    >
                    <span>{{ locale.common.no }}</span>
                </label>

                <label class="flex items-center gap-2">
                    <input
                        v-model="form.responses[question.key].answer"
                        :value="true"
                        type="radio"
                        @change="onAnswerChanged(question.key)"
                    >
                    <span>{{ locale.common.yes }}</span>
                </label>
            </div>

            <RepeaterSection
                v-if="form.responses[question.key].answer"
                v-model="form.responses[question.key].details"
                :fields="
                    question.fields.map(field => ({
                        ...field,
                        label: field.label[currentLocale],
                        options: field.options
                            ? field.options.map(option => ({
                                ...option,
                                label: option.label[currentLocale],
                                requires: option.requires?.map(required => ({
                                    ...required,
                                    label: required.label[currentLocale],
                                })),
                            }))
                            : getOptions(field.key),
                    }))
                "
                :question-key="question.key"
                :locale="currentLocale"
                :business-units="props.businessUnits"
                :companies="props.companies"
                :departments="props.departments"
                :errors="{ ...form.errors, ...clientErrors }"
                @clear-error="(key) => delete clientErrors[key]"            
            />
        </QuestionCard>

        <!-- Consent -->

        <Card class="mb-6 bg-slate-50 card-custom">
            <ConsentSection
                v-model="form.consent"
                :locale="currentLocale"
            />
    
            <div
                class="mt-6 flex justify-end gap-3"
            >
                <Link
                    :href="route('employee.history')"
                    class="rounded-md border border-border bg-white px-4 py-2 text-sm font-medium text-text hover:bg-slate-50"
                >
                    {{ locale.common.cancel }}
                </Link>
                <button
                    type="button"
                    class="btn-outline-custom disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="processingAction !== null"
                    @click="saveDraft"
                >
                    <span
                        v-if="processingAction === 'draft'"
                        class="loading loading-spinner loading-xs"
                    />
                    {{ processingAction === 'draft' ? locale.common.saving : locale.declaration.saveDraft }}
                </button>

                <button
                    type="button"
                    class="btn-primary-custom disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!form.consent || processingAction !== null"
                    @click="submit"
                >
                    <span
                        v-if="processingAction === 'submit'"
                        class="loading loading-spinner loading-xs"
                    />
                    {{ processingAction === 'submit' ? locale.common.submitting : locale.common.submit }}
                </button>
            </div>
        </Card>

    </EmployeeLayout>
</template>