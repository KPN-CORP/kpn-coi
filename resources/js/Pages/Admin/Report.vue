<script setup lang="ts">
import PageHeader from '@/Components/UI/PageHeader.vue'
import Card from '@/Components/UI/Card.vue'
import StatusBadge from '@/Components/UI/StatusBadge.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useForm, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { ref } from 'vue'
import DeclarationViewModal from '@/Components/Declaration/DeclarationViewModal.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import { coiQuestions } from '@/Config/coiQuestions'


interface Declaration {
    id: number
    row_id: string
    period: number
    type: 'employee' | 'non_employee'

    name: string
    employee_id: string

    status: string
    submitted_at: string | null

    has_conflict: boolean

    declaration?: any | null
}

const props = defineProps<{
    declarations: {
        data: Declaration[]
        links: any[]
        current_page: number
        last_page: number
        total: number
    }

    periods: number[]

    filters: {
        period?: string
        status?: string
        type?: string
        search?: string
    }
}>()


console.log(props.filters)

const filter = useForm({
    period: props.filters.period ?? '',
    status: props.filters.status ?? '',
    type: props.filters.type ?? '',
    search: props.filters.search ?? '',
})

const showReviewModal = ref(false)

const selectedDeclaration = ref<any | null>(null)

function openReview(
    declaration: Declaration,
) {
    if (
        !declaration.declaration?.responses
    ) {
        return
    }

    selectedDeclaration.value = {
        ...declaration.declaration,

        questions:
            declaration.declaration.responses.map(
                (response: any) => ({
                    key: response.question_key,

                    answer:
                        response.response_value?.answer,

                    details:
                        response.response_value?.details ?? [],

                    title:
                        getQuestionTitle(
                            response.question_key,
                        ),
                }),
            ),
    }

    showReviewModal.value = true
}

function getQuestionTitle(key: string) {
    const question = coiQuestions.find(
        q => q.key === key,
    )

    return question?.title?.en ?? key
}

function applyFilter() {
    router.get(
        route('admin.report'),
        filter.data(),
        {
            preserveState: true,
            replace: true,
        },
    )
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
    <AdminLayout>
        <PageHeader
            title="Reports"
            description="Monitor and export declarations."
        >
            <template #actions>
                <button
                    class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white"
                >
                    Export Report
                </button>
            </template>
        </PageHeader>

        <!-- FILTERS -->

        <Card class="mb-6">
            <div class="grid gap-4 md:grid-cols-3">
                <select
                    v-model="filter.period"
                    class="rounded-md border border-border px-3 py-2"
                    @change="applyFilter"
                >
                    <option value="">
                        Select Periods
                    </option>

                    <option
                        v-for="period in periods"
                        :key="period"
                        :value="period"
                    >
                        {{ period }}
                    </option>
                </select>

                <select
                    v-model="filter.status"
                    class="rounded-md border border-border px-3 py-2"
                    @change="applyFilter"
                >
                    <option value="">
                        All Status
                    </option>

                    <option value="submitted">
                        Submitted
                    </option>

                    <option value="pending">
                        Pending
                    </option>

                    <option value="conflict">
                        Has Conflict
                    </option>
                </select>

                <select
                    v-model="filter.type"
                    class="rounded-md border border-border px-3 py-2"
                    @change="applyFilter"
                >
                    <option value="">
                        All Types
                    </option>

                    <option value="employee">
                        Employee
                    </option>

                    <option value="non_employee">
                        Non Employee
                    </option>
                </select>
                
                <input
                        v-model="filter.search"
                        type="text"
                        placeholder="Search user..."
                        class="rounded-md border border-border px-3 py-2"
                        @input="applyFilter"
                    />
            </div>
        </Card>

        <!-- TABLE -->

        <Card>
            <div class="table-container">
                <table class="table-custom">
                    <thead>
                        <tr
                            class="border-b border-border text-left text-xs uppercase text-slate-500"
                        >
                            <th class="py-3">Period</th>
                            <th class="py-3">Status</th>
                            <th class="py-3">Full Name</th>
                            <th class="py-3">Employee ID / Citizenship ID</th>
                            <th class="py-3">Declaration Status</th>
                            <th class="py-3">Conflict Indicator</th>
                            <th class="py-3">Submitted At</th>
                            <th class="py-3">Action</th>
                        </tr>
                    </thead>

                    <tbody v-if="declarations.data.length">
                        <tr
                            v-for="declaration in declarations.data"
                            :key="declaration.row_id"
                            class="border-b border-slate-100"
                        >
                            <td class="py-4">
                                {{ declaration.period }}
                            </td>
                            
                            <td class="py-4">
                                <div
                                    class="mt-1 text-xs text-slate-500 align-middle"
                                >
                                    {{
                                        declaration.type === 'employee'
                                            ? 'Employee'
                                            : 'Non Employee'
                                    }}
                                </div>
                            </td>
                            
                            <td class="py-4 font-medium">
                                {{ declaration.name }}
                            </td>


                            <td class="py-4">
                                {{ declaration.employee_id }}
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

                            <td class="py-4">
                                {{ formatDate(declaration.submitted_at) }}
                            </td>

                            <td class="py-4">
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
                            </td>
                        </tr>
                    </tbody>

                    <tbody v-else>
                        <tr>
                            <td
                                colspan="7"
                                class="py-10 text-center"
                            >
                                <div class="flex flex-col items-center">
                                    <i
                                        class="fa-solid fa-file-circle-xmark text-4xl text-slate-300"
                                    />

                                    <div
                                        class="mt-3 font-semibold text-slate-600"
                                    >
                                        No declarations found
                                    </div>

                                    <div
                                        class="text-sm text-slate-500"
                                    >
                                        No employee declarations available.
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <Pagination
                    :links="props.declarations.links"
                />
            </div>
        </Card>
    </AdminLayout>
    <DeclarationViewModal
        v-if="selectedDeclaration"
        :show="showReviewModal"
        :declaration="selectedDeclaration"
        @close="showReviewModal = false"
    />
</template>