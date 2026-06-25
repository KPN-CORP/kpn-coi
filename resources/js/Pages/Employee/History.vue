<script setup lang="ts">

import PageHeader from '@/Components/UI/PageHeader.vue'
import Card from '@/Components/UI/Card.vue'
import StatusBadge from '@/Components/UI/StatusBadge.vue'
import DeclarationViewModal from '@/Components/Declaration/DeclarationViewModal.vue'
import FlashMessage from '@/Components/UI/FlashMessage.vue'
import EmployeeLayout from '@/Layouts/EmployeeLayout.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import { coiQuestions } from '@/Config/coiQuestions'

import { Link, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import type { Flash } from '@/Config/inertia'

const showViewModal = ref(false)

const selectedDeclaration = ref<any | null>(null)

const page = usePage<{
    flash: Flash
}>()

const currentYear = new Date().getFullYear()

const hasCurrentYearDeclaration = computed(() =>
    props.declarations.data.some(
        declaration =>
            Number(declaration.period) === currentYear,
    ),
)

interface Declaration {
    id: number
    period: number
    status: string
    submitted_at: string | null
    reviewed_at: string | null
    responses_count: number
}

const props = defineProps<{
    declarations: {
        data: Declaration[]
        links: any[]
        meta: {
            links: any[]
        }
    }
    periods: number[]
    filters: {
        period?: string
    }
}>()

function viewDeclaration(declaration: any) {
    
    selectedDeclaration.value = {
        ...declaration,

        questions: declaration.questions.map(
            (question: any) => ({
                ...question,

                title:
                    getQuestionTitle(
                        question.key,
                    ),
            }),
        ),
    }

    showViewModal.value = true
}

function getQuestionTitle(key: string) {
    const question = coiQuestions.find(
        q => q.key === key,
    )

    return question?.title?.en ?? key
}

function continueDraft(declaration: any) {
    router.visit(
        route('employee.declarations.create', {
            locale: declaration.locale ?? 'en',
        }),
    )
}

function getActions(status: string) {
    switch (status) {
        case 'draft':
        case 'revision_required':
            return ['continue']

        case 'submitted':
        case 'approved':
        case 'rejected':
            return ['view']

        default:
            return []
    }
}

function formatDate(date: string | null) {
    if (!date) return '-'

    const d = new Date(date)

    const day = String(d.getDate()).padStart(2, '0')
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const year = d.getFullYear()

    const hours = String(d.getHours()).padStart(2, '0')
    const minutes = String(d.getMinutes()).padStart(2, '0')
    const seconds = String(d.getSeconds()).padStart(2, '0')

    return `${day}-${month}-${year}, ${hours}:${minutes}:${seconds}`
}
</script>

<template>
    <EmployeeLayout>
        <PageHeader
            title="History"
            description="Your conflict of interest declaration records."
        >
            <FlashMessage
                type="success"
                :message="page.props.flash?.success"
            />

            <FlashMessage
                type="error"
                :message="page.props.flash?.error"
            />
            <template #actions>
                <Link
                    :href="route('employee.language')"
                    class="btn-primary-custom"
                >
                    <i class="fa-solid fa-plus" />
                    Create Declaration
                </Link>
            </template>
        </PageHeader>
        <FlashMessage
            type="success"
            :message="page.props.flash?.success"
        />

        <FlashMessage
            type="error"
            :message="page.props.flash?.error"
        />
        <div
            v-if="!hasCurrentYearDeclaration"
            class="mb-6 flex items-center gap-2 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700"
        >
            <i class="fa-solid fa-triangle-exclamation" />

            <span>
                You have not submitted the conflict of interest declaration,
                please submit immediately.
            </span>
        </div>
        <div class="mb-6 flex items-center gap-3">
            <select
                class="form-select"
                :value="filters.period"
                @change="
                    router.get(
                        route('employee.history'),
                        {
                            period: $event.target.value,
                        },
                        {
                            preserveState: true,
                            replace: true,
                        },
                    )
                "
            >
                <option value="">
                    All Periods
                </option>

                <option
                    v-for="period in periods"
                    :key="period"
                    :value="period"
                >
                    {{ period }}
                </option>
            </select>
        </div>

        <Card>
            <div class="table-container">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th class="py-3">Period</th>
                            <th class="py-3">Form Status</th>
                            <th class="py-3">Submit Date</th>
                            <th class="py-3">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="declaration in props.declarations.data"
                            :key="declaration.period"
                            class="border-b border-slate-100 text-center"
                        >
                            <td class="py-4 font-semibold">
                                {{ declaration.period }}
                            </td>

                            <td class="py-4">
                                <StatusBadge
                                    :status="declaration.status"
                                />
                            </td>

                            <td class="py-4">
                                {{ formatDate(declaration.submitted_at) }}
                            </td>

                            <td class="py-4">
                                <div class="flex justify-center gap-2">
                                    <button
                                        v-if="getActions(declaration.status).includes('continue')"
                                        class="btn bg-yellow-400 hover:bg-yellow-500 hover:font-bold text-white btn-sm"
                                        @click="continueDraft(declaration)"
                                    >
                                        Continue
                                    </button>
                                    <button
                                        v-if="getActions(declaration.status).includes('view')"
                                        class="btn btn-outline-secondary btn-sm"
                                        @click="viewDeclaration(declaration)"
                                    >
                                        <i class="fa-solid fa-eye" />
                                        View
                                    </button>

                                    <button
                                        v-if="declaration.status === 'approved'"
                                        class="btn btn-primary btn-sm"
                                    >
                                        PDF
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!props.declarations.data.length">
                            <td
                                colspan="4"
                                class="py-8 text-center text-slate-500"
                            >
                                No declaration records found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination
                :links="props.declarations.meta.links"
            />
        </Card>
        <DeclarationViewModal
            v-if="selectedDeclaration"
            :show="showViewModal"
            :declaration="selectedDeclaration"
            @close="showViewModal = false"
        />
    </EmployeeLayout>
</template>