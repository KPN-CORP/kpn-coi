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

import { coiQuestions } from '@/Config/coiQuestions'

interface DeclarationData {
    name: string
    citizen_number: string
    address: string
}

const props = defineProps<{
    locale: 'en' | 'id'
    draft?: any | null
    declaration: DeclarationData
    errors: Record<string, string>

}>()


const submitted = ref(false)
const clientErrors = ref<Record<string, string>>({})

const defaultResponses = {
    business_affiliation: {
    answer: false,
    details: [],
    },

    professional_relationship: {
        answer: false,
        details: [],
    },

    equity_ownership: {
        answer: false,
        details: [],
    },

    gifts_benefits: {
        answer: false,
        details: [],
    },

    family_relationship: {
        answer: false,
        details: [],
    },

    external_activities: {
        answer: false,
        details: [],
    },

}

const processingAction = ref<'draft' | 'submit' | null>(null)

const form = useForm({
    locale: props.locale,

    responses:
        props.draft?.data?.responses ??
        defaultResponses,

    consent: false,
})

const currentLocale = ref(
    props.locale === 'id' ? 'id' : 'en'
)

const page = usePage()

const flash = computed(() => page.props.flash as {
    success?: string
    error?: string
})

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
        for (const question of coiQuestions) {
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

function hasEmptyFields() {
    for (const question of coiQuestions) {
        const response = form.responses[question.key]

        if (!response.answer) {
            continue
        }

        for (const detail of response.details) {
            for (const field of question.fields) {
                if (!detail[field.key]?.trim()) {
                    return true
                }
            }
        }
    }

    return false
}

function validateForm(): boolean {
    let valid = true

    for (const question of coiQuestions) {
        const response = form.responses[question.key]

        if (!response.answer) {
            continue
        }

        response.details.forEach((detail, index) => {
            question.fields.forEach(field => {
                const key = `responses.${question.key}.details.${index}.${field.key}`

                if (!detail[field.key]?.trim()) {
                    clientErrors.value[key] = 'This field is required.'
                    valid = false
                }
            })
        })
    }

    return valid
}

function saveDraft() {
    processingAction.value = 'draft'

    form.post(route('employee.declarations.draft'), {
        preserveScroll: true,

        onSuccess: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Draft saved successfully.',
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

function submit() {
    submitted.value = true
    clientErrors.value = {}
    if (hasEmptyFields()) {
        Swal.fire({
            icon: 'warning',
            title: 'Please complete all required fields.',
        })

        return
    }

    if (!validateForm()) {
        return
    }
    
    form.post(route('employee.declarations.submit'), {
        onSuccess: () => {
            // Your success flow
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
            title="Create Declaration"
            description="Complete the potential conflicts of interest objectively."
        />

        <!-- Darwinbox Notice -->
        <Card class="mb-6 bg-slate-50 card-custom">

            <div class="alert alert-warning mb-6">
                <i
                    class="fa-solid fa-circle-info text-amber-600"
                />

                <span class="text-sm font-bold">
                    Please update your personal data profile via Darwinbox if there are any discrepancies below.
                </span>
            </div>

        <!-- Employee Information -->

            <div class="grid gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-semibold">
                        Full Name
                    </label>

                    <input
                        :value="props.declaration.name"
                        disabled
                        class="w-full rounded-md border border-border bg-slate-100 px-3 py-2"
                    >
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold">
                        National ID (KTP)
                    </label>

                    <input
                        :value="props.declaration.citizen_number"
                        disabled
                        class="w-full rounded-md border border-border bg-slate-100 px-3 py-2"
                    >
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold">
                        Registered Address
                    </label>

                    <input
                        :value="props.declaration.address"
                        disabled
                        class="w-full rounded-md border border-border bg-slate-100 px-3 py-2"
                    >
                </div>
            </div>
        </Card>

        <!-- Prefill -->

        <div class="alert alert-info mb-3 shadow-sm">
            <div class="flex w-full items-center justify-between">
                <div class="flex items-center gap-2">
                    <p class="font-bold">
                        Pre-fill Data:
                    </p>

                    <p class="text-slate-500">
                        Use previous declaration data.
                    </p>
                </div>

                <button
                    type="button"
                    class="btn-primary text-sm"
                >
                    Use Data
                </button>
            </div>
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
                    <span>No</span>
                </label>

                <label class="flex items-center gap-2">
                    <input
                        v-model="form.responses[question.key].answer"
                        :value="true"
                        type="radio"
                        @change="onAnswerChanged(question.key)"
                    >
                    <span>Yes</span>
                </label>
            </div>

            <RepeaterSection
                v-if="form.responses[question.key].answer"
                v-model="form.responses[question.key].details"
                :fields="
                    question.fields.map(field => ({
                        ...field,
                        label: field.label[currentLocale],
                    }))
                "
                :question-key="question.key"
                :errors="{ ...form.errors, ...clientErrors }"
                @clear-error="(key) => delete clientErrors[key]"            />
        </QuestionCard>

        <!-- Consent -->

        <Card class="mb-6 bg-slate-50 card-custom">
            <ConsentSection
                v-model="form.consent"
            />
    
            <div
                class="mt-6 flex justify-end gap-3"
            >
                <Link
                    :href="route('employee.history')"
                    class="rounded-md border border-border bg-white px-4 py-2 text-sm font-medium text-text hover:bg-slate-50"
                >
                    Cancel
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
                    {{ processingAction === 'draft' ? 'Saving...' : 'Save Draft' }}
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
                    {{ processingAction === 'submit' ? 'Submitting...' : 'Submit' }}
                </button>
            </div>
        </Card>

    </EmployeeLayout>
</template>