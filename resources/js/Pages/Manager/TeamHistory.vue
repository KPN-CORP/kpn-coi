<script setup lang="ts">
import PageHeader from '@/Components/UI/PageHeader.vue'
import Card from '@/Components/UI/Card.vue'
import StatusBadge from '@/Components/UI/StatusBadge.vue'
import { router, usePage, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { ref, computed, watch } from 'vue'
import debounce from 'lodash/debounce'
import DeclarationReviewModal from '@/Components/Declaration/DeclarationReviewModal.vue'
import ManagerLayout from '@/Layouts/ManagerLayout.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import { useLocale } from '@/Composables/useLocale'

const { t, locale } = useLocale()

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
    row_id: string
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
    periods: number[]
    filters: {
        period?: string
        status?: string
        declaration_status?: string
        business_unit?: string
        search?: string
        latest_submission?: boolean
        per_page?: number
        sort?: string
        direction?: string
    }
    businessUnitOptions: string[]
}>()

const filter = useForm({

    period: props.filters.period ?? '',

    status: props.filters.status ?? '',

    declaration_status: props.filters.declaration_status ?? '',

    business_unit: props.filters.business_unit ?? '',

    search: props.filters.search ?? '',

    latest_submission: props.filters.latest_submission ?? true,

    per_page: props.filters.per_page ?? 10,

    sort: props.filters.sort ?? '',

    direction: props.filters.direction ?? 'asc',

})

const sortableColumns = computed(() => [
    { label: t.value.common.period, key: 'period' },
    { label: t.value.common.name, key: 'name' },
    { label: t.value.teamHistory.columnDesignation, key: 'designation' },
    { label: t.value.teamHistory.columnLevel, key: 'level' },
    { label: t.value.common.businessUnit, key: 'business_unit' },
    // Named as on the report: "form status" is whether anything was submitted,
    // "declaration status" is whether that submission declares a conflict.
    // Both are filters above the table, so the columns have to agree with them.
    { label: t.value.teamHistory.formStatus, key: 'status' },
    { label: t.value.teamHistory.columnDeclarationStatus, key: 'has_conflict' },
])

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

function changePerPage(value: number) {
    filter.per_page = value

    applyFilter()
}

function getQuestionTitle(key: string) {
    const question = coiQuestions.find(
        q => q.key === key,
    )

    return question?.title?.[locale.value] ?? key
}

function applyFilter() {

    // filter.data() carries the sort and page size too. `page` is deliberately
    // not in it: changing a filter or the sort has to land back on page one,
    // otherwise a shorter result set leaves the manager on an empty page.
    router.get(

        route('manager.team-history'),

        filter.data(),

        {

            preserveState: true,

            preserveScroll: true,

            replace: true,

        },

    )

}
// Typing filters the table on a pause rather than on every keystroke.
watch(
    () => filter.search,
    debounce(() => applyFilter(), 500),
)

function exportExcel() {

    // Every filter, so the workbook matches what is on screen. Sorting and
    // paging are deliberately left out -- the export is the whole result set.
    window.open(

        route(
            'manager.team-history.excel',
            {
                period: filter.period,
                status: filter.status,
                declaration_status: filter.declaration_status,
                business_unit: filter.business_unit,
                search: filter.search,
                latest_submission: filter.latest_submission,
            },
        ),

        '_blank',
    )
}
</script>

<template>
    <ManagerLayout>
        <PageHeader
            :title="t.teamHistory.title"
            :description="t.teamHistory.description"
        />

        <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex flex-wrap items-end gap-4">

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">
                        {{ t.teamHistory.declarationPeriod }}
                    </label>

                    <select
                        v-model="filter.period"
                        class="rounded-md border border-border px-3 py-2 text-sm min-w-40"
                        @change="applyFilter"
                    >
                        <option value="">
                            {{ t.teamHistory.allPeriods }}
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
                        {{ t.common.businessUnit }}
                    </label>

                    <select
                        v-model="filter.business_unit"
                        class="rounded-md border border-border px-3 py-2 text-sm min-w-56"
                        @change="applyFilter"
                    >
                        <option value="">
                            {{ t.teamHistory.allBusinessUnits }}
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
                        {{ t.teamHistory.formStatus }}
                    </label>

                    <select
                        v-model="filter.status"
                        class="rounded-md border border-border px-3 py-2 text-sm min-w-40"
                        @change="applyFilter"
                    >
                        <option value="">
                            {{ t.common.allStatus }}
                        </option>

                        <option value="submitted">
                            {{ t.common.submitted }}
                        </option>

                        <option value="pending">
                            {{ t.common.notSubmitted }}
                        </option>
                    </select>
                </div>

                <!-- Declaration Status -->
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">
                        {{ t.teamHistory.columnDeclarationStatus }}
                    </label>

                    <select
                        v-model="filter.declaration_status"
                        class="rounded-md border border-border px-3 py-2 text-sm min-w-40"
                        @change="applyFilter"
                    >
                        <option value="">
                            {{ t.common.allStatus }}
                        </option>

                        <option value="clear">
                            {{ t.common.clear }}
                        </option>

                        <option value="conflict">
                            {{ t.common.hasConflict }}
                        </option>
                    </select>
                </div>

                <!-- Search -->
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">
                        {{ t.common.search }}
                    </label>

                    <input
                        v-model="filter.search"
                        type="text"
                        :placeholder="t.report.searchPlaceholder"
                        class="rounded-md border border-border px-3 py-2 text-sm min-w-56"
                    >
                </div>

                <!-- Latest Submission -->
                <div class="flex flex-col gap-2">
                    <label class="hidden text-sm font-medium text-slate-700 sm:block">
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
                        {{ t.report.latestSubmission }}
                    </label>
                </div>

            </div>
            <div class="flex items-center gap-2">

                <button
                    class="btn-sm btn-secondary"
                    @click="exportExcel"
                >
                    <i class="fa-solid fa-file-excel mr-2" />
                    {{ t.teamHistory.exportExcel }}
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
                            <th
                                v-for="col in sortableColumns"
                                :key="col.key"
                                class="py-3 cursor-pointer select-none whitespace-nowrap transition-colors hover:text-slate-700"
                                @click="toggleSort(col.key)"
                            >
                                <span class="inline-flex items-center gap-1.5">
                                    {{ col.label }}
                                    <i :class="sortIcon(col.key)" />
                                </span>
                            </th>
                            <!-- <th class="py-3">Action</th> -->
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="declaration in props.declarations.data"
                            :key="declaration.row_id"
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
                                    {{ t.common.na }}
                                </span>

                                <span
                                    v-else-if="declaration.has_conflict"
                                    class="rounded-md border border-red-200 bg-red-50 px-2 py-1 text-xs font-semibold text-red-700"
                                >
                                    {{ t.common.hasConflict }}
                                </span>

                                <span
                                    v-else
                                    class="rounded-md border border-green-200 bg-green-50 px-2 py-1 text-xs font-semibold text-green-700"
                                >
                                    {{ t.common.clear }}
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

                        <tr v-if="!props.declarations.data.length">
                            <td
                                :colspan="sortableColumns.length"
                                class="py-10 text-center text-sm text-slate-500"
                            >
                                {{ t.teamHistory.noRecords }}
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
        <DeclarationReviewModal
            v-if="selectedDeclaration"
            :show="showReviewModal"
            :declaration="selectedDeclaration"
            @close="showReviewModal = false"
        />
    </ManagerLayout>
</template>
