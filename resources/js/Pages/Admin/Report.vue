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
import SearchableSelect from '@/Components/UI/SearchableSelect.vue'
import { formatDateTime } from '@/Utils/date'
import debounce from 'lodash/debounce'
import { useLocale } from '@/Composables/useLocale'
import { usePermission } from '@/Composables/usePermission'
import FormStatusBadge from '@/Components/Forms/FormStatusBadge.vue'
import type { FormRow } from '@/Components/Forms/types'

const { t, locale } = useLocale()
const { can } = usePermission()

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
    contribution_level?: string | null

    status: string
    declaration_status: string
    submitted_at: string | null

    has_conflict: boolean
    has_attachment: boolean

    declaration?: any | null
    group_company?: string | null
}

const props = defineProps<{
    // COI rows, or FormRow for a form type (formKind === 'form').
    declarations: {
        data: any[]
        links: any[]
        current_page: number
        last_page: number
        total: number
        per_page: number
        from: number | null
        to: number | null
    }

    businessUnitOptions: string[]
    contributionLevelOptions: { code: string; name: string; business_unit: string }[]
    periods: number[]

    filters: {
        period?: string
        status?: string
        declaration_status?: string
        type?: string
        search?: string
        business_unit?: string
        contribution_level?: string
        declaration_question?: string
        latest_submission?: boolean
        per_page?: number
        sort?: string
        direction?: string
    }

    // Which declaration the page shows: 'coi' or a form type code.
    form: string
    formKind: 'coi' | 'form'
    formTypeId: number | null
    formName: { en: string, id: string }
    formOptions: { value: string, label: { en: string, id: string } }[]
}>()

const isForm = computed(() => props.formKind === 'form')

const formSelectOptions = computed(() => props.formOptions.map(option => ({
    value: option.value,
    label: option.label[locale.value],
})))


const filter = useForm({
    form: props.form,
    period: props.filters.period ?? new Date().getFullYear(),
    status: props.filters.status ?? '',
    declaration_status: props.filters.declaration_status ?? '',
    type: props.filters.type ?? '',
    search: props.filters.search ?? '',
    business_unit: props.filters.business_unit ?? '',
    contribution_level: props.filters.contribution_level ?? '',
    declaration_question: props.filters.declaration_question ?? '',
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
const isLegacyReport = computed(() => !isForm.value && Number(filter.period) === 2025)

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

// Options for the export-only "Question Detail" filter: pick a question to
// expand its per-answer details as extra columns in the Excel export. Titles
// are long, so prefix with the appendix number and trim to keep the dropdown
// readable.
const questionDetailOptions = computed(() => [
    { value: '', label: t.value.report.noQuestionDetail },
    ...coiQuestions.value.map((q: any) => {
        const title = String(q.title?.[locale.value] ?? q.key)
            .replace(/\s+/g, ' ')
            .trim()

        return {
            value: q.key,
            label: `${q.appendix}. ${
                title.length > 70 ? `${title.slice(0, 70)}…` : title
            }`,
        }
    }),
])

function onQuestionDetailSelected(value: string | number | null) {
    filter.declaration_question = value == null ? '' : String(value)
    applyFilter()
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

// "KPN Corporation" is the group head: as a business-unit filter it means the
// whole group, so it offers every contribution level, same as picking none.
const GROUP_HEAD = 'KPN Corporation'

// Contribution levels belong to a business unit (companies.company_name). When a
// business unit is chosen, only its levels are offered; with none chosen (or the
// group head chosen), all are.
const filteredContributionLevels = computed(() => {
    if (!filter.business_unit || filter.business_unit === GROUP_HEAD) {
        return props.contributionLevelOptions
    }

    return props.contributionLevelOptions.filter(
        option => option.business_unit === filter.business_unit,
    )
})

// Searchable-select option lists ({ value, label }), each with an "All" reset.
const businessUnitSelectOptions = computed(() => [
    { value: '', label: t.value.teamHistory.allBusinessUnits },
    ...props.businessUnitOptions.map(item => ({ value: item, label: item })),
])

const contributionLevelSelectOptions = computed(() => [
    { value: '', label: t.value.report.allContributionLevels },
    ...filteredContributionLevels.value.map(option => ({
        value: option.code,
        label: option.name,
    })),
])

function onBusinessUnitSelected(value: string | number | null) {
    filter.business_unit = value == null ? '' : String(value)
    onBusinessUnitChange()
}

function onContributionLevelSelected(value: string | number | null) {
    filter.contribution_level = value == null ? '' : String(value)
    applyFilter()
}

function onBusinessUnitChange() {
    // Drop a contribution level that no longer belongs to the chosen unit,
    // otherwise the query would keep filtering on a now-hidden option.
    const stillValid = filteredContributionLevels.value.some(
        option => option.code === filter.contribution_level,
    )

    if (!stillValid) {
        filter.contribution_level = ''
    }

    applyFilter()
}

// A form type can be read across every period ('all'); with Latest
// Submission on that keeps each person's newest signature.
const periodOptions = computed(() => [
    ...(isForm.value ? [{ value: 'all', label: t.value.teamHistory.allPeriods }] : []),
    ...props.periods.map(period => ({ value: period, label: String(period) })),
])

const typeOptions = computed(() => [
    { value: '', label: t.value.report.allTypes },
    { value: 'employee', label: t.value.common.employee },
    { value: 'non_employee', label: t.value.common.nonEmployee },
])

const statusOptions = computed(() => (isForm.value
    ? [
        { value: '', label: t.value.common.allStatus },
        { value: 'signed', label: t.value.forms.statusSigned },
        { value: 'not_signed', label: t.value.forms.statusPending },
        { value: 'draft', label: t.value.forms.statusDraft },
        { value: 'pending', label: t.value.forms.statusNotStarted },
        { value: 'no_template', label: t.value.forms.statusNoTemplate },
    ]
    : [
        { value: '', label: t.value.common.allStatus },
        { value: 'submitted', label: t.value.common.submitted },
        { value: 'draft', label: t.value.status.draft },
        { value: 'pending', label: t.value.common.notSubmitted },
    ]))

// The form type table: same look as the COI one, columns of a signature.
const formColumns = computed(() => [
    { label: t.value.common.fullName, key: 'name', class: 'sticky left-0 z-20 bg-slate-50 shadow-[1px_0_0_0_#e2e8f0]' },
    { label: t.value.common.period, key: 'period', class: '' },
    { label: t.value.report.columnType, key: 'type', class: '' },
    { label: t.value.report.columnFormStatus, key: 'status', class: '' },
    { label: t.value.dashboard.form, key: '', class: '' },
    { label: t.value.forms.columnSignedAt, key: 'signed_at', class: '' },
])

function downloadFormPdf(row: FormRow, language: 'id' | 'en') {
    window.open(
        route('admin.forms.report.pdf', { declaration: row.declaration_id, locale: language }),
        '_blank',
    )
}

// Status values differ between COI and form types, and so do the sortable
// columns: a form switch starts from a clean status and sort.
function onFormChanged() {
    filter.status = ''
    filter.declaration_status = ''
    filter.sort = ''
    filter.direction = 'asc'

    applyFilter()
}

const declarationStatusOptions = computed(() => [
    { value: '', label: t.value.common.allStatus },
    { value: 'clear', label: t.value.common.clear },
    { value: 'conflict', label: t.value.common.hasConflict },
])

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

// --- Filter bar -----------------------------------------------------------------
// Form, period, search and Latest Submission stay in view; the rest sit in a
// collapsible panel and show as removable chips while set.

type ChipKey = 'type' | 'business_unit' | 'contribution_level' | 'status' | 'declaration_status' | 'declaration_question'

const labelOf = (options: { value: string | number, label: string }[], value: unknown) =>
    options.find(option => option.value === value)?.label ?? String(value)

const activeChips = computed(() => {
    const chips: { key: ChipKey, label: string, value: string }[] = []

    if (filter.type) {
        chips.push({ key: 'type', label: t.value.dashboard.declarationType, value: labelOf(typeOptions.value, filter.type) })
    }

    if (filter.business_unit) {
        chips.push({ key: 'business_unit', label: t.value.report.columnBusinessUnit, value: filter.business_unit })
    }

    if (filter.contribution_level) {
        chips.push({ key: 'contribution_level', label: t.value.report.columnContributionLevel, value: labelOf(contributionLevelSelectOptions.value, filter.contribution_level) })
    }

    if (filter.status) {
        chips.push({ key: 'status', label: t.value.report.columnFormStatus, value: labelOf(statusOptions.value, filter.status) })
    }

    if (!isForm.value && filter.declaration_status) {
        chips.push({ key: 'declaration_status', label: t.value.report.columnDeclarationStatus, value: labelOf(declarationStatusOptions.value, filter.declaration_status) })
    }

    if (!isForm.value && filter.declaration_question) {
        chips.push({ key: 'declaration_question', label: t.value.report.questionDetail, value: labelOf(questionDetailOptions.value, filter.declaration_question) })
    }

    return chips
})

// Open on arrival only when something in it is already set.
const showFilterPanel = ref(activeChips.value.length > 0)

function removeChip(key: ChipKey) {
    filter[key] = ''

    // A company only makes sense inside its business unit.
    if (key === 'business_unit') {
        filter.contribution_level = ''
    }

    applyFilter()
}

const hasAnyFilter = computed(() => activeChips.value.length > 0 || filter.search !== '' || !filter.latest_submission)

// Clear every filter back to its default (current period, no status/search,
// latest-submission on) in one click. Form, sort and page size are kept.
function resetFilters() {
    filter.period = new Date().getFullYear()
    filter.type = ''
    filter.business_unit = ''
    filter.contribution_level = ''
    filter.declaration_question = ''
    filter.status = ''
    filter.declaration_status = ''
    filter.search = ''
    filter.latest_submission = true

    applyFilter()
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

const canExport = computed(() => !isForm.value || can('form_report.export'))

async function exportExcel() {
    if (exportState.value === 'generating') {
        return
    }

    // A form type's workbook is small and built in the request.
    if (isForm.value) {
        window.location.href = route('admin.forms.report.export', {
            form_type: props.formTypeId,
            period: filter.period,
            type: filter.type,
            business_unit: filter.business_unit,
            contribution_level: filter.contribution_level,
            search: filter.search,
            status: filter.status,
            latest_submission: filter.latest_submission ? 1 : 0,
            sort: filter.sort,
            direction: filter.direction,
        })

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
                // Stamp the active UI language so the workbook (headers, status
                // values, question labels) and its filename match the screen.
                body: JSON.stringify({ ...filter.data(), locale: locale.value }),
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

async function pollExport(id: number, attempt = 0) {
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

        // A typical export is built in a few seconds, so a flat 3s poll spends
        // most of the wait on a file that is already sitting there. Check every
        // second for the first stretch, then back off for the long ones.
        setTimeout(
            () => pollExport(id, attempt + 1),
            attempt < 15 ? 1000 : 3000,
        )
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
            :description="`${formName[locale]} - ${t.report.description}`"
        >
            <template #actions>
                <button
                    v-if="canExport"
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

        <section class="mb-6 rounded-lg border border-border bg-white shadow-sm">
            <!-- Always in view: what is reported, for when, and who -->
            <div class="flex flex-wrap items-end gap-3 p-4">
                <div class="flex flex-col gap-1.5">
                    <label
                        for="report-form"
                        class="text-xs font-medium uppercase tracking-wide text-slate-500"
                    >
                        {{ t.dashboard.form }}
                    </label>
                    <SearchableSelect
                        id="report-form"
                        v-model="filter.form"
                        :options="formSelectOptions"
                        class="w-60 max-w-full"
                        @change="onFormChanged"
                    />
                </div>

                <div class="flex flex-col gap-1.5">
                    <label
                        for="report-period"
                        class="text-xs font-medium uppercase tracking-wide text-slate-500"
                    >
                        {{ t.dashboard.reportingPeriod }}
                    </label>
                    <SearchableSelect
                        id="report-period"
                        v-model="filter.period"
                        :options="periodOptions"
                        class="w-40 max-w-full"
                        @change="applyFilter"
                    />
                </div>

                <div class="relative min-w-[16rem] flex-1">
                    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400" />
                    <input
                        v-model="filter.search"
                        type="search"
                        :placeholder="t.report.searchPlaceholder"
                        :aria-label="t.common.search"
                        class="w-full rounded-md border border-border py-2 pl-9 pr-3 text-sm"
                    >
                </div>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium transition-colors"
                    :class="showFilterPanel || activeChips.length
                        ? 'border-primary bg-red-50 text-primary'
                        : 'border-border text-slate-700 hover:bg-slate-50'"
                    :aria-expanded="showFilterPanel"
                    aria-controls="report-filter-panel"
                    @click="showFilterPanel = !showFilterPanel"
                >
                    <i class="fa-solid fa-sliders" />
                    {{ t.report.filters }}
                    <span
                        v-if="activeChips.length"
                        class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-xs font-semibold text-white"
                    >
                        {{ activeChips.length }}
                    </span>
                    <i
                        class="fa-solid fa-chevron-down text-xs transition-transform duration-200"
                        :class="{ 'rotate-180': showFilterPanel }"
                    />
                </button>

                <!-- Latest Submission: a switch, as it is on/off -->
                <label class="inline-flex cursor-pointer select-none items-center gap-2 py-2 text-sm text-slate-700">
                    <input
                        v-model="filter.latest_submission"
                        type="checkbox"
                        role="switch"
                        class="peer sr-only"
                        @change="applyFilter"
                    >
                    <span
                        class="relative h-5 w-9 shrink-0 rounded-full transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40"
                        :class="filter.latest_submission ? 'bg-primary' : 'bg-slate-300'"
                        aria-hidden="true"
                    >
                        <span
                            class="absolute left-0.5 top-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform"
                            :class="{ 'translate-x-4': filter.latest_submission }"
                        />
                    </span>
                    {{ t.report.latestSubmission }}
                </label>
            </div>

            <!-- More filters -->
            <div
                v-show="showFilterPanel"
                id="report-filter-panel"
                class="border-t border-border bg-slate-50/60 px-4 py-4"
            >
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-slate-600">
                            {{ t.dashboard.declarationType }}
                        </label>
                        <SearchableSelect
                            v-model="filter.type"
                            :options="typeOptions"
                            class="w-full"
                            @change="applyFilter"
                        />
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-slate-600">
                            {{ t.report.columnBusinessUnit }}
                        </label>
                        <SearchableSelect
                            :model-value="filter.business_unit"
                            :options="businessUnitSelectOptions"
                            class="w-full"
                            @update:model-value="onBusinessUnitSelected"
                        />
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-slate-600">
                            {{ t.report.columnContributionLevel }}
                        </label>
                        <SearchableSelect
                            :model-value="filter.contribution_level"
                            :options="contributionLevelSelectOptions"
                            class="w-full"
                            @update:model-value="onContributionLevelSelected"
                        />
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-slate-600">
                            {{ t.report.columnFormStatus }}
                        </label>
                        <SearchableSelect
                            v-model="filter.status"
                            :options="statusOptions"
                            class="w-full"
                            @change="applyFilter"
                        />
                    </div>

                    <div
                        v-if="!isForm"
                        class="flex flex-col gap-1.5"
                    >
                        <label class="text-xs font-medium text-slate-600">
                            {{ t.report.columnDeclarationStatus }}
                        </label>
                        <SearchableSelect
                            v-model="filter.declaration_status"
                            :options="declarationStatusOptions"
                            class="w-full"
                            @change="applyFilter"
                        />
                    </div>
                </div>

                <!-- COI export-only option: kept apart, it changes the Excel
                     file, not the table. -->
                <div
                    v-if="!isForm"
                    class="mt-4 flex flex-col gap-1.5 border-t border-dashed border-border pt-4 sm:max-w-md"
                >
                    <label class="flex items-center gap-2 text-xs font-medium text-slate-600">
                        <i class="fa-solid fa-file-excel text-green-700" />
                        {{ t.report.questionDetail }}
                    </label>
                    <SearchableSelect
                        :model-value="filter.declaration_question"
                        :options="questionDetailOptions"
                        :placeholder="t.report.noQuestionDetail"
                        append-to-body
                        @update:model-value="onQuestionDetailSelected"
                    />
                    <p class="text-xs leading-snug text-slate-500">
                        {{ t.report.questionDetailHint }}
                    </p>
                </div>
            </div>

            <!-- Active filters -->
            <div
                v-if="hasAnyFilter"
                class="flex flex-wrap items-center gap-2 border-t border-border px-4 py-2.5 text-sm"
            >
                <span class="text-slate-500">
                    {{ declarations.total }} {{ t.report.results }}
                </span>

                <button
                    v-for="chip in activeChips"
                    :key="chip.key"
                    type="button"
                    class="inline-flex max-w-xs items-center gap-1.5 rounded-full border border-red-100 bg-red-50 px-2.5 py-1 text-xs text-primary hover:bg-red-100"
                    :aria-label="`${t.report.removeFilter}: ${chip.label} ${chip.value}`"
                    @click="removeChip(chip.key)"
                >
                    <span class="text-slate-500">{{ chip.label }}:</span>
                    <span class="truncate font-medium">{{ chip.value }}</span>
                    <i class="fa-solid fa-xmark" />
                </button>

                <button
                    type="button"
                    class="ml-auto inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-primary"
                    @click="resetFilters"
                >
                    <i class="fa-solid fa-rotate-left" />
                    {{ t.report.resetFilters }}
                </button>
            </div>
        </section>

        <!-- TABLE: form type -->

        <Card v-if="isForm">
            <div class="table-container">
                <table class="table-custom min-w-[1100px]">
                    <thead>
                        <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-slate-500">
                            <th
                                v-for="col in formColumns"
                                :key="col.label"
                                :class="['py-3 whitespace-nowrap', col.class]"
                                :aria-sort="col.key && filter.sort === col.key ? (filter.direction === 'asc' ? 'ascending' : 'descending') : undefined"
                            >
                                <button
                                    v-if="col.key"
                                    type="button"
                                    class="inline-flex select-none items-center gap-1.5 uppercase tracking-[inherit] [font:inherit] transition-colors hover:text-slate-700"
                                    @click="toggleSort(col.key)"
                                >
                                    {{ col.label }}
                                    <i :class="sortIcon(col.key)" />
                                </button>

                                <template v-else>
                                    {{ col.label }}
                                </template>
                            </th>
                            <th class="py-3 text-center sticky right-0 z-10 bg-slate-50 shadow-[-1px_0_0_0_#e2e8f0]">
                                {{ t.common.action }}
                            </th>
                        </tr>
                    </thead>

                    <tbody v-if="declarations.data.length">
                        <tr
                            v-for="row in (declarations.data as FormRow[])"
                            :key="row.key"
                            class="group border-b border-slate-100"
                        >
                            <td class="py-4 sticky left-0 z-10 bg-white group-hover:bg-[#fafafa] shadow-[1px_0_0_0_#e2e8f0]">
                                <div
                                    v-if="row.business_unit"
                                    class="mt-0.5 truncate text-xs text-slate-400"
                                    :title="row.business_unit"
                                >
                                    {{ row.business_unit }}
                                </div>
                                <div
                                    class="truncate font-medium text-slate-800"
                                    :title="row.name"
                                >
                                    {{ row.name }}
                                </div>
                                <div class="mt-0.5 truncate text-xs text-slate-400">
                                    {{ row.employee_id || '-' }}
                                </div>
                                <div
                                    v-if="row.contribution_level"
                                    class="mt-0.5 truncate text-xs text-slate-400"
                                    :title="row.contribution_level"
                                >
                                    {{ row.contribution_level }}
                                </div>
                            </td>

                            <td class="py-4 whitespace-nowrap text-slate-600">
                                {{ row.period }}
                            </td>

                            <td class="py-4">
                                <span
                                    class="inline-block whitespace-nowrap rounded-md border px-2 py-1 text-xs font-medium"
                                    :class="row.type === 'employee'
                                        ? 'border-blue-200 bg-blue-50 text-blue-700'
                                        : 'border-amber-200 bg-amber-50 text-amber-700'"
                                >
                                    {{ row.type === 'employee' ? t.common.employee : t.common.nonEmployee }}
                                </span>
                            </td>

                            <td class="py-4">
                                <FormStatusBadge :status="row.status" />
                            </td>

                            <td class="py-4 text-slate-600">
                                {{ row.template_title?.[locale] ?? '-' }}
                            </td>

                            <td class="py-4 whitespace-nowrap text-slate-600">
                                {{ row.signed_at ? formatDateTime(row.signed_at) : '-' }}
                            </td>

                            <td class="py-4 text-center sticky right-0 z-10 bg-white group-hover:bg-[#fafafa] whitespace-nowrap shadow-[-1px_0_0_0_#e2e8f0]">
                                <div
                                    v-if="row.declaration_id"
                                    class="flex items-center justify-center gap-2"
                                >
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary-custom btn-sm"
                                        @click="downloadFormPdf(row, 'id')"
                                    >
                                        <i class="fa-solid fa-file-pdf" />
                                        ID
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-outline-primary-custom btn-sm"
                                        @click="downloadFormPdf(row, 'en')"
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
                            </td>
                        </tr>
                    </tbody>

                    <tbody v-else>
                        <tr>
                            <td
                                :colspan="formColumns.length + 1"
                                class="py-10 text-center"
                            >
                                <div class="flex flex-col items-center">
                                    <i class="fa-solid fa-file-circle-xmark text-4xl text-slate-300" />

                                    <div class="mt-3 font-semibold text-slate-600">
                                        {{ t.report.noDeclarationsFound }}
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

        <!-- TABLE: Conflict of Interest -->

        <Card v-else>
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
                                <!-- Employee / non-employee business unit -->
                                <div
                                    v-if="declaration.group_company && declaration.group_company !== '-'"
                                    class="mt-0.5 truncate text-xs text-slate-400"
                                    :title="declaration.group_company"
                                >
                                    {{ declaration.group_company }}
                                </div>
                                <div
                                    class="truncate font-medium text-slate-800"
                                    :title="declaration.name"
                                >
                                    {{ declaration.name }}
                                </div>
                                <div class="mt-0.5 truncate text-xs text-slate-400">
                                    {{ declaration.employee_id }}
                                </div>
                                <div
                                    v-if="declaration.contribution_level"
                                    class="mt-0.5 truncate text-xs text-slate-400"
                                    :title="declaration.contribution_level"
                                >
                                    {{ declaration.contribution_level }}
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