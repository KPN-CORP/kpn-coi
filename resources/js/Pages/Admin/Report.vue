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
import { formatDateTime } from '@/Utils/date'
import debounce from 'lodash/debounce'
import { useLocale } from '@/Composables/useLocale'

const { t, locale } = useLocale()

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
    declaration_status: string
    submitted_at: string | null

    has_conflict: boolean
    has_attachment: boolean

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
        declaration_status?: string
        type?: string
        search?: string
        business_unit?: string
        latest_submission?: boolean
        per_page?: number
        sort?: string
        direction?: string
    }
}>()


const filter = useForm({
    period: props.filters.period ?? new Date().getFullYear(),
    status: props.filters.status ?? '',
    declaration_status: props.filters.declaration_status ?? '',
    type: props.filters.type ?? '',
    search: props.filters.search ?? '',
    business_unit: props.filters.business_unit ?? '',
    latest_submission: props.filters.latest_submission ?? true,
    per_page: props.filters.per_page ?? 20,
    sort: props.filters.sort ?? '',
    direction: props.filters.direction ?? 'asc'
})

const sortableColumns = computed(() => [
    { label: t.value.common.fullName, key: 'name', class: 'sticky left-0 z-20 bg-slate-50 shadow-[1px_0_0_0_#e2e8f0]' },
    { label: t.value.common.period, key: 'period', class: '' },
    { label: t.value.report.columnType, key: 'type', class: '' },
    { label: t.value.report.columnFormStatus, key: 'status', class: '' },
    { label: t.value.report.columnDeclarationStatus, key: 'has_conflict', class: 'min-w-64' },
    { label: t.value.report.columnSubmittedAt, key: 'submitted_at', class: '' },
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

// 2025 is a historical import with no per-question breakdown: the Q1..Qn
// columns collapse into a single conflict mark, and the row's document is
// opened directly instead of the review modal.
const isLegacyReport = computed(() => Number(filter.period) === 2025)

function openAttachment(declaration: Declaration) {
    if (!declaration.declaration?.id) {
        return
    }

    window.open(
        route('admin.report.declaration.attachment', declaration.declaration.id),
        '_blank',
    )
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

    return question?.title?.[locale.value] ?? key
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
        alert(t.value.report.exportStartFailed)
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
            alert(data.error ?? t.value.report.exportFailed)
            exportState.value = 'idle'
            return
        }

        setTimeout(() => pollExport(id), 3000)
    } catch (e) {
        exportState.value = 'idle'
        alert(t.value.report.exportConnectionLost)
    }
}
</script>

<template>
    <AdminLayout>
        <PageHeader
            :title="t.report.title"
            :description="t.report.description"
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
                            ? t.report.generating
                            : t.report.exportReport
                    }}
                </button>
            </template>
        </PageHeader>

        <!-- FILTERS -->

        <Card class="mb-6">

            <div class="grid items-end gap-4 [grid-template-columns:repeat(auto-fit,minmax(180px,1fr))]">

                    <!-- Reporting Period -->
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-700">
                            {{ t.dashboard.reportingPeriod }}
                        </label>

                        <select
                            v-model="filter.period"
                            class="w-full rounded-md border border-border px-3 py-2 text-sm"
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
                            {{ t.dashboard.declarationType }}
                        </label>

                        <select
                            v-model="filter.type"
                            class="w-full rounded-md border border-border px-3 py-2 text-sm"
                            @change="applyFilter"
                        >
                            <option value="">
                                {{ t.report.allTypes }}
                            </option>
                            <option value="employee">
                                {{ t.common.employee }}
                            </option>

                            <option value="non_employee">
                                {{ t.common.nonEmployee }}
                            </option>
                        </select>
                    </div>

                    <!-- Business Unit -->
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-medium text-slate-700">
                            {{ t.report.columnBusinessUnit }}
                        </label>

                        <select
                            v-model="filter.business_unit"
                            class="w-full rounded-md border border-border px-3 py-2 text-sm"
                            @change="applyFilter"
                        >
                            <option value="">
                                {{ t.teamHistory.allBusinessUnits }}
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
                            {{ t.report.columnFormStatus }}
                        </label>

                        <select
                            v-model="filter.status"
                            class="w-full rounded-md border border-border px-3 py-2 text-sm"
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
                            {{ t.report.columnDeclarationStatus }}
                        </label>

                        <select
                            v-model="filter.declaration_status"
                            class="w-full rounded-md border border-border px-3 py-2 text-sm"
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
                            class="w-full rounded-md border border-border px-3 py-2 text-sm"
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

        </Card>

        <!-- TABLE -->

        <Card>
            <div class="table-container">
                <table
                    class="table-custom"
                    :style="`table-layout: fixed; width: ${1340 + (isLegacyReport ? 120 : 56 * coiQuestions.length)}px`"
                >
                    <colgroup>
                        <col style="width: 260px">
                        <col style="width: 110px">
                        <col style="width: 150px">
                        <col style="width: 180px">
                        <col style="width: 200px">
                        <col style="width: 190px">
                        <template v-if="!isLegacyReport">
                            <col
                                v-for="question in coiQuestions"
                                :key="question.key"
                                style="width: 56px"
                            >
                        </template>
                        <col v-else style="width: 120px">
                        <col style="width: 250px">
                    </colgroup>
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
                            <template v-if="!isLegacyReport">
                                <th
                                    v-for="(question, i) in coiQuestions"
                                    :key="question.key"
                                    class="group/question relative w-12 py-3 text-center"
                                >
                                    <span
                                        class="cursor-help underline decoration-dotted decoration-slate-300 underline-offset-4"
                                    >
                                        Q{{ i + 1 }}
                                    </span>
                                    <div
                                        class="pointer-events-none invisible absolute left-1/2 top-full z-30 mt-1 w-64 -translate-x-1/2 rounded-lg bg-slate-800 px-3 py-2 text-left text-xs font-normal normal-case tracking-normal text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover/question:visible group-hover/question:opacity-100"
                                    >
                                        {{ question.title[locale] }}
                                    </div>
                                </th>
                            </template>
                            <th
                                v-else
                                class="py-3 text-center"
                                :title="t.report.coiTooltip"
                            >
                                COI
                            </th>
                            <th
                                class="py-3 text-center sticky right-0 z-10 bg-slate-50 shadow-[-1px_0_0_0_#e2e8f0]"
                            >
                                {{ t.common.action }}
                            </th>
                        </tr>
                    </thead>

                    <tbody v-if="declarations.data.length">
                        <tr
                            v-for="declaration in declarations.data"
                            :key="declaration.row_id"
                            class="group border-b border-slate-100"
                        >
                            <td class="py-4 sticky left-0 z-10 bg-white group-hover:bg-[#fafafa] shadow-[1px_0_0_0_#e2e8f0]">
                                <div
                                    class="truncate font-medium text-slate-800"
                                    :title="declaration.name"
                                >
                                    {{ declaration.name }}
                                </div>
                                <div class="mt-0.5 truncate text-xs text-slate-400">
                                    {{ declaration.employee_id }}
                                </div>
                            </td>

                            <td class="py-4 whitespace-nowrap text-slate-600">
                                {{ declaration.period }}
                            </td>

                            <td class="py-4">
                                <span
                                    class="inline-block whitespace-nowrap rounded-md border px-2 py-1 text-xs font-medium"
                                    :class="declaration.type === 'employee'
                                        ? 'border-blue-200 bg-blue-50 text-blue-700'
                                        : 'border-amber-200 bg-amber-50 text-amber-700'"
                                >
                                    {{
                                        declaration.type === 'employee'
                                            ? t.common.employee
                                            : t.common.nonEmployee
                                    }}
                                </span>
                            </td>

                            <td class="py-4">
                                <StatusBadge
                                    :status="declaration.status"
                                    :label="declaration.status === 'pending'
                                        ? t.common.notSubmitted
                                        : null"
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

                            <td class="py-4 whitespace-nowrap text-slate-600">
                                {{ formatDateTime(declaration.submitted_at) }}
                            </td>

                            <template v-if="!isLegacyReport">
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
                            </template>

                            <!-- 2025: single merged conflict mark -->
                            <td
                                v-else
                                class="py-4 text-center"
                            >
                                <span
                                    v-if="declaration.has_conflict"
                                    class="font-bold text-red-600"
                                    :title="t.common.hasConflict"
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
                                class="py-4 text-center sticky right-0 z-10 bg-white group-hover:bg-[#fafafa] whitespace-nowrap shadow-[-1px_0_0_0_#e2e8f0]"
                            >
                                <!-- 2025: open the uploaded document directly -->
                                <template v-if="isLegacyReport">
                                    <button
                                        v-if="declaration.has_attachment"
                                        type="button"
                                        class="btn btn-outline-secondary btn-sm"
                                        @click="openAttachment(declaration)"
                                    >
                                        <i class="fa-solid fa-file-arrow-down" />
                                        {{ t.common.view }}
                                    </button>

                                    <span
                                        v-else
                                        class="text-xs text-slate-400"
                                    >
                                        {{ t.report.noAttachment }}
                                    </span>
                                </template>

                                <template v-else>
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
                                            {{ t.common.view }}
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
                                        {{ t.common.noSubmission }}
                                    </span>
                                </template>
                            </td>
                        </tr>
                    </tbody>

                    <tbody v-else>
                        <tr>
                            <td
                                :colspan="7 + (isLegacyReport ? 1 : coiQuestions.length)"
                                class="py-10 text-center"
                            >
                                <div class="flex flex-col items-center">
                                    <i
                                        class="fa-solid fa-file-circle-xmark text-4xl text-slate-300"
                                    />

                                    <div
                                        class="mt-3 font-semibold text-slate-600"
                                    >
                                        {{ t.report.noDeclarationsFound }}
                                    </div>

                                    <div
                                        class="text-sm text-slate-500"
                                    >
                                        {{ t.report.noEmployeeDeclarations }}
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