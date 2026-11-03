<script setup lang="ts">
import FormReportTable from '@/Components/Report/FormReportTable.vue'
import CoiReportTable from '@/Components/Report/CoiReportTable.vue'
import { DEFAULT_EMPLOYEE_STATUS, useEmployeeStatusOptions } from '@/Composables/useEmployeeStatus'
import { csrfToken } from '@/Utils/http'
import { toggleSort as toggleSortState } from '@/Composables/useSort'
import PageHeader from '@/Components/UI/PageHeader.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { ref, computed, watch } from 'vue'
import SearchableSelect from '@/Components/UI/SearchableSelect.vue'
import { formatPeriod } from '@/Utils/date'
import debounce from 'lodash/debounce'
import { useLocale } from '@/Composables/useLocale'
import { usePermission } from '@/Composables/usePermission'

const { t, locale } = useLocale()
const { can } = usePermission()

const page = usePage()

const coiQuestions = computed(
    () => page.props.coiQuestions
)

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
        employee_status?: string
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
    // Active by default (the server's default too): people who have left
    // only show when asked for.
    employee_status: props.filters.employee_status ?? DEFAULT_EMPLOYEE_STATUS,
    declaration_question: props.filters.declaration_question ?? '',
    latest_submission: props.filters.latest_submission ?? true,
    per_page: props.filters.per_page ?? 20,
    sort: props.filters.sort ?? '',
    direction: props.filters.direction ?? 'asc'
})

function toggleSort(column: string) {
    toggleSortState(filter, column)

    applyFilter()
}

// 2025 is a historical import with no per-question breakdown: the Q1..Qn
// columns collapse into a single conflict mark, and the row's document is
// opened directly instead of the review modal.
const isLegacyReport = computed(() => !isForm.value && Number(filter.period) === 2025)

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
    ...props.periods.map(period => ({ value: period, label: formatPeriod(period, locale.value) })),
])

const employeeStatusOptions = useEmployeeStatusOptions()

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
    ]
    : [
        { value: '', label: t.value.common.allStatus },
        { value: 'submitted', label: t.value.common.submitted },
        { value: 'draft', label: t.value.status.draft },
        { value: 'pending', label: t.value.common.notSubmitted },
    ]))

// Status values differ between COI and form types, and so do the sortable
// columns: a form switch starts from a clean status and sort.
// The server picks the period when none is sent (a form type's latest
// month); show what it picked rather than an empty select.
watch(() => props.filters.period, (period) => {
    if (period !== undefined && period !== null && period !== '') {
        filter.period = period
    }
})

function onFormChanged() {
    // COI periods are years, form periods months: let the server pick the
    // new form's default period.
    filter.period = ''
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

type ChipKey = 'type' | 'business_unit' | 'contribution_level' | 'employee_status' | 'status' | 'declaration_status' | 'declaration_question'

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

    // A chip only when it differs from the default (active).
    if (filter.employee_status !== DEFAULT_EMPLOYEE_STATUS) {
        chips.push({ key: 'employee_status', label: t.value.common.employeeStatus, value: labelOf(employeeStatusOptions.value, filter.employee_status) })
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
    filter[key] = key === 'employee_status' ? DEFAULT_EMPLOYEE_STATUS : ''

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
    // A form type opens on its own default (latest month with a template).
    filter.period = isForm.value ? '' : new Date().getFullYear()
    filter.type = ''
    filter.business_unit = ''
    filter.contribution_level = ''
    filter.employee_status = DEFAULT_EMPLOYEE_STATUS
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


const canExport = computed(() => !isForm.value || can(`form_type.${props.form}`))

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
            employee_status: filter.employee_status,
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
                            {{ t.common.employeeStatus }}
                        </label>
                        <SearchableSelect
                            v-model="filter.employee_status"
                            :options="employeeStatusOptions"
                            class="w-full"
                            @change="applyFilter"
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

        <FormReportTable
            v-if="isForm"
            :declarations="declarations"
            :sort="filter.sort"
            :direction="filter.direction"
            @sort="toggleSort"
            @per-page="changePerPage"
        />

        <CoiReportTable
            v-else
            :declarations="declarations"
            :sort="filter.sort"
            :direction="filter.direction"
            :legacy="isLegacyReport"
            @sort="toggleSort"
            @per-page="changePerPage"
        />
    </AdminLayout>
</template>