<script setup lang="ts">
import PageHeader from '@/Components/UI/PageHeader.vue'
import Card from '@/Components/UI/Card.vue'
import StatusBadge from '@/Components/UI/StatusBadge.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { ref, computed, watch } from 'vue'
import DeclarationViewModal from '@/Components/Declaration/DeclarationViewModal.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import debounce from 'lodash/debounce'


const page = usePage()

const coiQuestions = computed(
    () => page.props.coiQuestions
)

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
    business_unit?: string | null
}

const props = defineProps<{
    declarations: {
        data: Declaration[]
        links: any[]
        current_page: number
        last_page: number
        total: number
        per_page: number
        from: number | null
        to: number | null
    }

    businessUnitOptions: string[]
    periods: number[]

    filters: {
        period?: string
        status?: string
        type?: string
        search?: string
        business_unit?: string
        latest_submission?: boolean
        per_page?: number
        sort?: string
        direction?: string
    }
}>()


console.log(props.filters)

const filter = useForm({
    period: props.filters.period ?? new Date().getFullYear(),
    status: props.filters.status ?? '',
    type: props.filters.type ?? '',
    search: props.filters.search ?? '',
    business_unit: props.filters.business_unit ?? '',
    latest_submission: props.filters.latest_submission ?? true,
    per_page: props.filters.per_page ?? 20,
    sort: props.filters.sort ?? '',
    direction: props.filters.direction ?? 'asc'
})

const sortableColumns = [
    { label: 'Period', key: 'period', class: 'w-20' },
    { label: 'Type', key: 'type', class: 'w-32' },
    { label: 'Full Name', key: 'name', class: 'min-w-[200px]' },
    { label: 'Employee / Citizen ID', key: 'employee_id', class: 'min-w-[150px]' },
    { label: 'Form Status', key: 'status', class: 'w-32' },
    { label: 'Conflict', key: 'has_conflict', class: 'w-32' },
    { label: 'Submitted At', key: 'submitted_at', class: 'min-w-[170px]' },
]

function toggleSort(column: string) {
    if (filter.sort === column) {
        filter.direction = filter.direction === 'asc' ? 'desc' : 'asc'
    } else {
        filter.sort = column
        filter.direction = 'asc'
    }

    applyFilter()
}

function sortIcon(column: string) {
    if (filter.sort !== column) {
        return 'fa-solid fa-sort text-slate-300'
    }

    return filter.direction === 'asc'
        ? 'fa-solid fa-sort-up text-slate-600'
        : 'fa-solid fa-sort-down text-slate-600'
}

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
    const question = coiQuestions.value.find(
        (q: any) => q.key === key,
    )

    return question?.title?.en ?? key
}

function downloadPdf(
    declarationId: number,
    locale: 'id' | 'en',
) {
    window.open(
        route('admin.report.declaration.pdf', {
            declaration: declarationId,
            locale,
        }),
        '_blank',
    )
}

// Per-question mark for the report table (mirrors the Excel export):
// true  -> answered "Yes" for that question
// false -> answered "No"
// null  -> no submission for this row
function questionAnswered(
    declaration: Declaration,
    key: string,
): boolean | null {
    const responses = declaration.declaration?.responses

    if (!responses) {
        return null
    }

    const response = responses.find(
        (r: any) => r.question_key === key,
    )

    return response?.response_value?.answer === true
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
function changePerPage(value: number) {
    filter.per_page = value

    applyFilter()
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

function onTypeChanged() {

    filter.business_unit = ''

    applyFilter()

}

watch(

    () => filter.search,

    debounce(() => {

        applyFilter()

    }, 500)

)

const exportState = ref<'idle' | 'generating'>('idle')

function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    )
}

async function exportExcel() {
    if (exportState.value === 'generating') {
        return
    }

    exportState.value = 'generating'

    try {
        const res = await fetch(
            route('admin.report.export'),
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(filter.data()),
            },
        )

        if (!res.ok) {
            throw new Error('Failed to start export')
        }

        const data = await res.json()

        pollExport(data.id)
    } catch (e) {
        exportState.value = 'idle'
        alert('Could not start the export. Please try again.')
    }
}

async function pollExport(id: number) {
    try {
        const res = await fetch(
            route('admin.report.export.status', id),
            {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            },
        )

        const data = await res.json()

        if (data.status === 'completed') {
            window.location.href = data.download_url
            exportState.value = 'idle'
            return
        }

        if (data.status === 'failed') {
            alert(data.error ?? 'The export failed.')
            exportState.value = 'idle'
            return
        }

        setTimeout(() => pollExport(id), 3000)
    } catch (e) {
        exportState.value = 'idle'
        alert('Lost connection while generating the export.')
    }
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
                    class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="exportState === 'generating'"
                    @click="exportExcel"
                >
                    <i
                        v-if="exportState === 'generating'"
                        class="fa-solid fa-circle-notch fa-spin"
                    />
                    {{
                        exportState === 'generating'
                            ? 'Generating…'
                            : 'Export Report'
                    }}
                </button>
            </template>
        </PageHeader>

        <!-- FILTERS -->

        <Card class="mb-6">

            <div class="flex flex-wrap items-end justify-between gap-4">

                <div class="flex flex-wrap items-end gap-4">

                    <!-- Reporting Period -->
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-700">
                            Reporting Period
                        </label>

                        <select
                            v-model="filter.period"
                            class="rounded-md border border-border px-3 py-2 text-sm min-w-40"
                            @change="applyFilter"
                        >
                            <option
                                v-for="period in periods"
                                :key="period"
                                :value="period"
                            >
                                {{ period }}
                            </option>
                        </select>
                    </div>

                    <!-- Declaration Type -->
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-700">
                            Declaration Type
                        </label>

                        <select
                            v-model="filter.type"
                            class="rounded-md border border-border px-3 py-2 text-sm min-w-44"
                            @change="onTypeChanged"
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
                    </div>

                    <!-- Business Unit -->
                    <div
                        v-if="filter.type === 'employee'"
                        class="flex flex-col gap-2"
                    >
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
                                v-for="item in businessUnitOptions"
                                :key="item"
                                :value="item"
                            >
                                {{ item }}
                            </option>
                        </select>
                    </div>

                    <!-- Status -->
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

                            <option value="submitted">
                                Submitted
                            </option>

                            <option value="pending">
                                Not Submitted
                            </option>

                            <option value="conflict">
                                Has Conflict
                            </option>
                        </select>
                    </div>

                    <!-- Search -->
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-700">
                            Search
                        </label>

                        <input
                            v-model="filter.search"
                            type="text"
                            placeholder="Employee Name / Employee ID"
                            class="rounded-md border border-border px-3 py-2 text-sm min-w-64"
                        >
                    </div>

                    <!-- Latest Submission -->
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-700">
                            &nbsp;
                        </label>

                        <label
                            class="flex items-center gap-2 rounded-md border border-border px-3 py-2 text-sm cursor-pointer select-none"
                        >
                            <input
                                v-model="filter.latest_submission"
                                type="checkbox"
                                class="h-4 w-4 rounded border-border"
                                @change="applyFilter"
                            >
                            Latest Submission
                        </label>
                    </div>

                </div>

            </div>

        </Card>

        <!-- TABLE -->

        <Card>
            <div class="table-container">
                <table class="table-custom">
                    <thead>
                        <tr
                            class="border-b border-border text-left text-xs uppercase tracking-wide text-slate-500"
                        >
                            <th
                                v-for="col in sortableColumns"
                                :key="col.key"
                                :class="[
                                    'py-3 cursor-pointer select-none whitespace-nowrap transition-colors hover:text-slate-700',
                                    col.class,
                                ]"
                                @click="toggleSort(col.key)"
                            >
                                <span class="inline-flex items-center gap-1.5">
                                    {{ col.label }}
                                    <i :class="sortIcon(col.key)" />
                                </span>
                            </th>
                            <th
                                v-for="(question, i) in coiQuestions"
                                :key="question.key"
                                class="w-12 py-3 text-center"
                                :title="question.title.en"
                            >
                                Q{{ i + 1 }}
                            </th>
                            <th
                                class="py-3 text-right sticky right-0 z-10 bg-slate-50 border-l border-border shadow-[-6px_0_6px_-6px_rgba(0,0,0,0.12)]"
                            >
                                Action
                            </th>
                        </tr>
                    </thead>

                    <tbody v-if="declarations.data.length">
                        <tr
                            v-for="declaration in declarations.data"
                            :key="declaration.row_id"
                            class="group border-b border-slate-100"
                        >
                            <td class="py-4 whitespace-nowrap">
                                {{ declaration.period }}
                            </td>

                            <td class="py-4">
                                <span
                                    class="inline-block rounded-md border px-2 py-1 text-xs font-medium"
                                    :class="declaration.type === 'employee'
                                        ? 'border-blue-200 bg-blue-50 text-blue-700'
                                        : 'border-amber-200 bg-amber-50 text-amber-700'"
                                >
                                    {{
                                        declaration.type === 'employee'
                                            ? 'Employee'
                                            : 'Non Employee'
                                    }}
                                </span>
                            </td>

                            <td class="py-4 font-medium text-slate-800 whitespace-nowrap">
                                {{ declaration.name }}
                            </td>

                            <td class="py-4 whitespace-nowrap text-slate-600">
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

                            <td class="py-4 whitespace-nowrap text-slate-600">
                                {{ formatDate(declaration.submitted_at) }}
                            </td>

                            <td
                                v-for="question in coiQuestions"
                                :key="question.key"
                                class="py-4 text-center"
                            >
                                <span
                                    v-if="questionAnswered(declaration, question.key)"
                                    class="font-bold text-green-600"
                                >
                                    ✓
                                </span>

                                <span
                                    v-else
                                    class="text-slate-300"
                                >
                                    -
                                </span>
                            </td>

                            <td
                                class="py-4 text-right sticky right-0 z-10 bg-white group-hover:bg-[#fafafa] border-l border-border whitespace-nowrap shadow-[-6px_0_6px_-6px_rgba(0,0,0,0.12)]"
                            >
                                <div
                                    v-if="declaration.status !== 'pending' && declaration.declaration"
                                    class="flex items-center justify-center gap-2"
                                >
                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary btn-sm"
                                        @click="openReview(declaration)"
                                    >
                                        <i class="fa-solid fa-eye" />
                                        View
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-outline-primary-custom btn-sm"
                                        @click="downloadPdf(declaration.declaration.id, 'id')"
                                    >
                                        <i class="fa-solid fa-file-pdf" />
                                        ID
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-outline-primary-custom btn-sm"
                                        @click="downloadPdf(declaration.declaration.id, 'en')"
                                    >
                                        <i class="fa-solid fa-file-pdf" />
                                        EN
                                    </button>
                                </div>

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
                                :colspan="7 + coiQuestions.length"
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
            </div>
            <Pagination
                :links="props.declarations.links"
                :per-page="props.declarations.per_page"
                :total="props.declarations.total"
                :from="props.declarations.from"
                :to="props.declarations.to"
                @update:per-page="changePerPage"
            />
        </Card>
    </AdminLayout>
    <DeclarationViewModal
        v-if="selectedDeclaration"
        :show="showReviewModal"
        :declaration="selectedDeclaration"
        @close="showReviewModal = false"
    />
</template>