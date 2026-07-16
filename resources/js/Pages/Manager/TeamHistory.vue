<script setup lang="ts">
import PageHeader from '@/Components/UI/PageHeader.vue'
import Card from '@/Components/UI/Card.vue'
import StatusBadge from '@/Components/UI/StatusBadge.vue'
import { router, usePage, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import DeclarationReviewModal from '@/Components/Declaration/DeclarationReviewModal.vue'
import ManagerLayout from '@/Layouts/ManagerLayout.vue'
import Pagination from '@/Components/UI/Pagination.vue'


const page = usePage()

const coiQuestions = computed(
    () => page.props.coiQuestions
)

const showReviewModal = ref(false)

const selectedDeclaration = ref(null)

function openReview(declaration: Declaration) {
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

    showReviewModal.value = true
}

interface Declaration {
    id: number
    period: number
    status: string
    submitted_at: string | null
    has_conflict: boolean

    name: string
    employee_id: string
    designation: string
    level: string
    business_unit: string
    ktp: string

    declaration: {
        id: number
        fullname: string
        employee_id: string
        period: number
        status: string
        submitted_at: string | null
        responses: any[]
    } | null
}

const props = defineProps<{
    declarations: Declaration[]
    periods: number[]
    filters: {
        period?: string
        status: string[]
        business_unit?: string
    }
    businessUnitOptions: string[]
}>()

const filter = useForm({

    period: props.filters.period ?? '',

    status: props.filters.status ?? '',

    business_unit: props.filters.business_unit ?? '',

})

function getQuestionTitle(key: string) {
    const question = coiQuestions.find(
        q => q.key === key,
    )

    return question?.title?.en ?? key
}

function applyFilter() {

    router.get(

        route('manager.team-history'),
        

        {

            period: filter.period,

            status: filter.status,

            business_unit: filter.business_unit,

        },

        {

            preserveState: true,

            preserveScroll: true,

            replace: true,

        },

    )

}
function exportExcel() {

    window.open(

        route(
            'manager.team-history.excel',
            {
                period: filter.period,
                status: filter.status,
                business_unit: filter.business_unit,
            },
        ),

        '_blank',
    )
}
</script>

<template>
    <ManagerLayout>
        <PageHeader
            title="Team History"
            description="Direct reportees conflict of interest declarations tracking."
        />

        <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex flex-wrap items-end gap-4">

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">
                        Declaration Period
                    </label>

                    <select
                        v-model="filter.period"
                        class="rounded-md border border-border px-3 py-2 text-sm min-w-40"
                        @change="applyFilter"
                    >
                        <option value="">
                            All Period
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

                <!-- Business Unit -->
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">
                        Business Unit
                    </label>

                    <select
                        v-model="filter.business_unit"
                        class="rounded-md border border-border px-3 py-2 text-sm min-w-56"
                        @change="applyFilter"
                    >
                        <option value="">
                            All Business Unit
                        </option>

                        <option
                            v-for="unit in businessUnitOptions"
                            :key="unit"
                            :value="unit"
                        >
                            {{ unit }}
                        </option>
                    </select>
                </div>

                <!-- Form Status -->
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">
                        Form Status
                    </label>

                    <select
                        v-model="filter.status"
                        class="rounded-md border border-border px-3 py-2 text-sm min-w-40"
                        @change="applyFilter"
                    >
                        <option value="">
                            All Status
                        </option>

                        <option value="pending">
                            Not Submitted
                        </option>

                        <option value="submitted">
                            Submitted
                        </option>

                        <option value="conflict">
                            Has Conflict
                        </option>
                    </select>
                </div>

            </div>
            <div class="flex items-center gap-2">

                <button
                    class="btn-sm btn-secondary"
                    @click="exportExcel"
                >
                    <i class="fa-solid fa-file-excel mr-2" />
                    Export Excel
                </button>

            </div>
        </div>

        <Card>
            <div class="table-container">
                <table class="table-custom">
                    <thead>
                        <tr
                            class="border-b border-border text-left text-xs uppercase text-slate-500"
                        >
                            <th class="py-3">Period</th>
                            <th class="py-3">Name</th>
                            <th class="py-3">Designation</th>
                            <th class="py-3">Level</th>
                            <th class="py-3">Business Unit</th>
                            <th class="py-3">Declaration Status</th>
                            <th class="py-3">Conflict Indicator</th>
                            <!-- <th class="py-3">Action</th> -->
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                           v-for="declaration in props.declarations"
                            :key="declaration.id"
                            class="border-b border-slate-100"
                        >
                            <td class="py-4">
                                {{ declaration.period }}
                            </td>

                            <td class="py-4 font-semibold">
                                {{ declaration.name }}
                            </td>

                            <td class="py-4">
                                {{ declaration.designation }}
                            </td>

                            <td class="py-4">
                                {{ declaration.level }}
                            </td>

                            <td class="py-4">
                                {{ declaration.business_unit }}
                            </td>

                            <td class="py-4">
                                <StatusBadge
                                    :status="declaration.status"
                                />
                            </td>

                            <td class="py-4">
                                <span
                                    v-if="declaration.status === 'pending'"
                                    class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-semibold text-slate-600"
                                >
                                    N/A
                                </span>

                                <span
                                    v-else-if="declaration.has_conflict"
                                    class="rounded-md border border-red-200 bg-red-50 px-2 py-1 text-xs font-semibold text-red-700"
                                >
                                    Has Conflict
                                </span>

                                <span
                                    v-else
                                    class="rounded-md border border-green-200 bg-green-50 px-2 py-1 text-xs font-semibold text-green-700"
                                >
                                    Clear
                                </span>
                            </td>

                            <!-- <td class="py-4">
                                <button
                                    v-if="declaration.status !== 'pending'"
                                    class="btn btn-outline-secondary btn-sm"
                                    @click="openReview(declaration)"
                                >
                                    <i class="fa-solid fa-eye" />
                                    View
                                </button>

                                <span
                                    v-else
                                    class="text-xs text-slate-400"
                                >
                                    No Submission
                                </span>
                            </td> -->
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
        <DeclarationReviewModal
            v-if="selectedDeclaration"
            :show="showReviewModal"
            :declaration="selectedDeclaration"
            @close="showReviewModal = false"
        />
    </ManagerLayout>
</template>
