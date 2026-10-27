<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import Card from '@/Components/UI/Card.vue'
import StatCard from '@/Components/UI/StatCard.vue'
import StatusBadge from '@/Components/UI/StatusBadge.vue'
import SearchableSelect from '@/Components/UI/SearchableSelect.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Declaration } from '@/Config/declaration'
import { onMounted, ref, watch, reactive, computed } from 'vue'
import Chart from 'chart.js/auto'
import { useForm, router } from '@inertiajs/vue3'
import type { Chart as ChartInstance } from 'chart.js'
import Swal from 'sweetalert2'
import { useLocale } from '@/Composables/useLocale'
import { formatPeriod } from '@/Utils/date'

const { t, locale } = useLocale()

const statusChart = ref<ChartInstance | null>(null)
const barChart = ref<ChartInstance | null>(null)

// For a form type, submitted/pending are signed/not signed and the fourth
// card is drafts instead of conflicts (FormDashboardService).
interface DashboardStats {
    total: number
    pending: number
    submitted: number
    conflict?: number
    draft?: number
    not_started?: number
}

interface BarChart {
    title: string
    labels: string[]
    datasets: {
        label: string
        data: number[]
        backgroundColor: string | string[]
        borderRadius: number
    }[]
}

const statusChartRef = ref<HTMLCanvasElement | null>(
    null,
)

function renderStatusChart() {
    if (!statusChartRef.value) {
        return
    }

    statusChart.value?.destroy()

    statusChart.value = new Chart(statusChartRef.value, {
        type: 'doughnut',

        data: isForm.value
            ? {
                labels: [
                    t.value.forms.statusSigned,
                    t.value.forms.statusDraft,
                    t.value.forms.statusNotStarted,
                ],

                datasets: [{
                    data: [
                        props.stats.submitted,
                        props.stats.draft ?? 0,
                        props.stats.not_started ?? 0,
                    ],

                    backgroundColor: [
                        '#2f855a',
                        '#f59e0b',
                        '#cbd5e1',
                    ],

                    borderWidth: 0,
                }],
            }
            : {
                labels: [
                    t.value.common.submitted,
                    t.value.common.notSubmitted,
                ],

                datasets: [{
                    data: [
                        props.stats.submitted,
                        props.stats.pending,
                    ],

                    backgroundColor: [
                        '#2f855a',
                        '#cbd5e1',
                    ],

                    borderWidth: 0,
                }],
            },

        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',

            plugins: {
                legend: {
                    position: 'bottom',
                },
            },
        },
    })
}

const barChartRef =
    ref<HTMLCanvasElement | null>(null)

// The bar chart's title and dataset labels arrive from the server in English;
// translate them here so they follow the live language toggle.
function localizeDatasetLabel(label: string): string {
    switch (label) {
        case 'Submitted':
            return t.value.common.submitted

        case 'Not Submitted':
            return t.value.common.notSubmitted

        case 'Signed':
            return t.value.forms.statusSigned

        case 'Not Signed':
            return t.value.forms.statusPending

        default:
            return label
    }
}

function renderBarChart() {

    if (!barChartRef.value) {
        return
    }

    barChart.value?.destroy()

    barChart.value = new Chart(
        barChartRef.value,
        {
            type: 'bar',

            data: {

                labels: props.barChart.labels,

                datasets: props.barChart.datasets.map(dataset => ({
                    ...dataset,
                    label: localizeDatasetLabel(dataset.label),
                })),

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                plugins: {

                    title: {

                        display: true,

                        text: barChartTitle.value,

                    },

                    legend: {

                        position: 'bottom',

                    },

                },

                scales: {

                    x: {

                        grid: {

                            display: false,

                        },

                    },

                    y: {

                        beginAtZero: true,

                        border: {

                            display: false,

                        },

                    },

                },

            },

        }
    )
}

function getStatusChartImage(): string | null {
    return statusChartRef.value
        ? canvasToImage(statusChartRef.value, 420, 420)
        : null
}

function getBarChartImage(): string | null {
    return barChartRef.value
        ? canvasToImage(barChartRef.value, 900, 420)
        : null
}

const props = defineProps<{
    declarations: {
        data: Declaration[],
        links: any[]
    }
    employeeDeclarations: {
    data: Declaration[]
    }

    nonEmployeeDeclarations: {
        data: Declaration[]
    }
    stats: DashboardStats
    periods: number[]
    businessUnitOptions: string[]

    barChart: BarChart

    // Which declaration the page shows: 'coi' or a form type code.
    form: string
    formKind: 'coi' | 'form'
    formTypeId: number | null
    formName: { en: string, id: string }
    formOptions: { value: string, label: { en: string, id: string } }[]

        filters: {
            period?: string
            status?: string
            business_unit?: string
            type?: string
        }
    }>()

const filter = useForm({
    form: props.form,
    period: props.filters.period || new Date().getFullYear().toString(),
    status: props.filters.status ?? '',
    business_unit: props.filters.business_unit ?? '',
    type: props.filters.type ?? 'employee',
})

// Readable label for the KPI cards: Employee / Non-Employee / All Types.
const typeLabel = computed(() => {
    if (filter.type === 'employee') {
        return t.value.common.employee
    }

    if (filter.type === 'non_employee') {
        return t.value.common.nonEmployee
    }

    return t.value.report.allTypes
})

const isForm = computed(() => props.formKind === 'form')

const formSelectOptions = computed(() => props.formOptions.map(option => ({
    value: option.value,
    label: option.label[locale.value],
})))

const barChartTitle = computed(() => (isForm.value
    ? t.value.dashboard.signedByBusinessUnit
    : t.value.dashboard.submissionsByBusinessUnit))

const periodOptions = computed(() => props.periods.map(period => ({ value: period, label: formatPeriod(period, locale.value) })))

const typeOptions = computed(() => [
    { value: 'all', label: t.value.report.allTypes },
    { value: 'employee', label: t.value.common.employee },
    { value: 'non_employee', label: t.value.common.nonEmployee },
])

const businessUnitSelectOptions = computed(() => [
    { value: '', label: t.value.teamHistory.allBusinessUnits },
    ...props.businessUnitOptions.map(item => ({ value: item, label: item })),
])

const statusOptions = computed(() => [
    { value: '', label: t.value.common.allStatus },
    { value: 'submitted', label: t.value.common.submitted },
    { value: 'pending', label: t.value.common.notSubmitted },
    { value: 'conflict', label: t.value.common.hasConflict },
])

// The server picks the period when none is sent (a form type's latest
// month); show what it picked rather than an empty select.
watch(() => props.filters.period, (period) => {
    if (period !== undefined && period !== null && period !== '') {
        filter.period = period
    }
})

// COI periods are years, form periods months: let the server pick the new
// form's default period.
function onFormChanged() {
    filter.period = ''
    applyFilter()
}

function applyFilter() {
    router.get(
        route('admin.dashboard'),
        {
            form: filter.form,
            period: filter.period,
            business_unit: filter.business_unit,
            type: filter.type,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    )
}

interface ReportFilters {
    period?: string | number
    status?: string
    declaration_status?: string
    type?: string
    business_unit?: string
    search?: string
    pending?: boolean | number
    conflict?: boolean | number
}

async function downloadDashboardPdf() {

    const form = document.createElement('form')

    form.method = 'POST'

    form.action = route('admin.dashboard.pdf')

    form.target = '_blank'

    form.style.display = 'none'

    function append(name: string, value: any) {

        const input = document.createElement('input')

        input.type = 'hidden'

        input.name = name

        input.value = value ?? ''

        form.appendChild(input)
    }

    append('_token', document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content'))

    append('form', filter.form)

    append('period', filter.period)

    append('status', filter.status)

    append(
        'business_unit',
        filter.business_unit
    )

    append(
        'type',
        filter.type
    )

    append(
        'status_chart',
        getStatusChartImage()
    )

    append(
        'business_unit_chart',
        getBarChartImage()
    )

    document.body.appendChild(form)

    form.submit()

    document.body.removeChild(form)
}

function exportExcel() {

    window.open(

        route(
            'admin.dashboard.excel',
            {
                period: filter.period,
                status: filter.status,
                type: filter.type,
                business_unit: filter.business_unit,
            },
        ),

        '_blank',
    )
}

function canvasToImage(
    canvas: HTMLCanvasElement,
    width: number,
    height: number,
): string {
    const exportCanvas = document.createElement('canvas')

    exportCanvas.width = width
    exportCanvas.height = height

    const ctx = exportCanvas.getContext('2d')

    if (!ctx) {
        return ''
    }

    // White backdrop: the chart canvas is transparent, and dompdf can render
    // transparent PNG regions as black, so paint the page colour underneath.
    ctx.fillStyle = '#ffffff'
    ctx.fillRect(0, 0, width, height)

    // Preserve the source aspect ratio (contain, centred) rather than stretching
    // it to fill. Chart.js sizes the on-screen canvas to its container, which is
    // not square, so a plain stretch turns the doughnut into an oval in the PDF.
    const scale = Math.min(
        width / canvas.width,
        height / canvas.height,
    )

    const drawWidth = canvas.width * scale
    const drawHeight = canvas.height * scale
    const offsetX = (width - drawWidth) / 2
    const offsetY = (height - drawHeight) / 2

    ctx.drawImage(
        canvas,
        offsetX,
        offsetY,
        drawWidth,
        drawHeight,
    )

    return exportCanvas.toDataURL(
        'image/png',
        1.0,
    )
}

function onTypeChanged() {
    filter.business_unit = ''
    applyFilter()
}

function openReport(
    filters: ReportFilters = {},
) {
    let total = props.stats.total

    // Conflict moved to its own filter on the report, so it arrives as
    // declaration_status rather than status.
    switch (filters.status ?? filters.declaration_status) {
        case 'submitted':
        case 'signed':
            total = props.stats.submitted
            break

        case 'pending':
        case 'not_signed':
            total = props.stats.pending
            break

        case 'draft':
            total = props.stats.draft ?? 0
            break

        case 'conflict':
            total = props.stats.conflict ?? 0
            break
    }

    if (total === 0) {
        Swal.fire({
            icon: 'info',
            title: t.value.dashboard.noDataTitle,
            text: t.value.dashboard.noDataText,
            confirmButtonText: t.value.common.ok,
        })

        return
    }

    // The reports use an empty type for "all"; the dashboard uses the
    // literal 'all', so translate before linking.
    const shared = {
        period: filter.period,
        type: filter.type === 'all' ? '' : filter.type,
        business_unit: filter.business_unit,
    }

    window.open(
        isForm.value
            ? route('admin.report', { ...shared, form: props.form, ...filters })
            : route('admin.report', { ...shared, status: filter.status, ...filters }),
        '_blank',
    )
}

onMounted(() => {

    if (props.barChart.labels.length) {
        renderBarChart()
    }

    renderStatusChart()

})

watch(
    () => props.stats,
    () => {
        renderStatusChart()
    },
    {
        deep: true,
    },
)

watch(
    () => props.barChart,
    () => {
        renderBarChart()
    },
    {
        deep: true,
    },
)

// Chart labels/titles are baked in at render time, so redraw on language change.
watch(locale, () => {
    renderStatusChart()

    if (props.barChart.labels.length) {
        renderBarChart()
    }
})
</script>

<template>
    <AdminLayout>
        <PageHeader
            :title="t.dashboard.title"
            :description="`${formName[locale]} - ${t.dashboard.description}`"
        />
        <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex flex-wrap items-end gap-4">

                <!-- Form: Conflict of Interest or a form type -->
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">
                        {{ t.dashboard.form }}
                    </label>

                    <SearchableSelect
                        v-model="filter.form"
                        :options="formSelectOptions"
                        class="w-56 max-w-full"
                        @change="onFormChanged"
                    />
                </div>

                <!-- Reporting Period -->
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">
                        {{ t.dashboard.reportingPeriod }}
                    </label>

                    <SearchableSelect
                        v-model="filter.period"
                        :options="periodOptions"
                        class="w-36 max-w-full"
                        @change="applyFilter"
                    />
                </div>

                <!-- Declaration Type -->
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">
                        {{ t.dashboard.declarationType }}
                    </label>

                    <SearchableSelect
                        v-model="filter.type"
                        :options="typeOptions"
                        class="w-48 max-w-full"
                        @change="onTypeChanged"
                    />
                </div>

                <!-- Business Unit -->
                <div :class="[
                        'flex flex-col gap-2']">
                    <label class="text-sm font-medium text-slate-700">
                        {{ t.common.businessUnit }}
                    </label>

                    <SearchableSelect
                        v-model="filter.business_unit"
                        :options="businessUnitSelectOptions"
                        class="w-64 max-w-full"
                        @change="applyFilter"
                    />
                </div>

                <!-- Status -->
                <!-- <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">
                        Status
                    </label>

                    <SearchableSelect
                        v-model="filter.status"
                        :options="statusOptions"
                        class="w-48 max-w-full"
                        @change="applyFilter"
                    />
                </div> -->

            </div>

            <div class="flex items-center gap-2">

                <button
                    class="btn-sm btn-primary-custom"
                    @click="downloadDashboardPdf"
                >
                    <i class="fa-solid fa-file-pdf mr-2" />
                    {{ t.dashboard.downloadPdf }}
                </button>

                <!-- <button
                    class="btn-sm btn-secondary"
                    @click="exportExcel"
                >
                    <i class="fa-solid fa-file-excel mr-2" />
                    Export Excel
                </button> -->

            </div>
        </div>

        <!-- KPI CARDS -->

        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4 mb-6">
            <Card class="stat-card">
                <div class="stat-num text-blue-700">
                    {{ stats.total }}
                </div>
                <div class="stat-title">
                    {{ t.dashboard.kpiTotal }} {{ typeLabel }}
                </div>
            </Card>
            <Card class="stat-card cursor-pointer transition hover:-translate-y-1 hover:shadow-md" @click="openReport({ status: isForm ? 'signed' : 'submitted' })">
                <div class="stat-num text-green-600">
                    {{ stats.submitted }}
                </div>
                <div class="stat-title">
                    {{ t.dashboard.kpiTotal }} {{ typeLabel }} <br> {{ isForm ? t.dashboard.kpiSigned : t.dashboard.kpiSubmitted }}
                </div>
            </Card>
            <Card class="stat-card cursor-pointer transition hover:-translate-y-1 hover:shadow-md" @click="openReport({ status: isForm ? 'not_signed' : 'pending' })">
                <div class="stat-num text-yellow-500">
                    {{ stats.pending }}
                </div>
                <div class="stat-title">
                    {{ t.dashboard.kpiTotal }} {{ typeLabel }} <br> {{ isForm ? t.dashboard.kpiNotSigned : t.dashboard.kpiNotSubmitted }}
                </div>
            </Card>
            <Card
                v-if="isForm"
                class="stat-card cursor-pointer transition hover:-translate-y-1 hover:shadow-md"
                @click="openReport({ status: 'draft' })"
            >
                <div class="stat-num text-orange-500">
                    {{ stats.draft ?? 0 }}
                </div>
                <div class="stat-title">
                    {{ t.dashboard.kpiTotal }} {{ typeLabel }} <br> {{ t.dashboard.kpiDraft }}
                </div>
            </Card>
            <Card
                v-else
                class="stat-card cursor-pointer transition hover:-translate-y-1 hover:shadow-md"
                @click="openReport({ declaration_status: 'conflict' })"
            >
                <div class="stat-num text-red-600">
                    {{ stats.conflict ?? 0 }}
                </div>
                <div class="stat-title">
                    {{ t.dashboard.kpiTotal }} {{ typeLabel }} <br> {{ t.dashboard.kpiConflict }}
                </div>
            </Card>
        </div>

        <!-- CHART PLACEHOLDER -->

        <div class="grid gap-6 md:grid-cols-12 mb-6">
            <!-- Overall Status -->

            <Card class="card-custom md:col-span-4 bg-white">
                <div class="mb-4">
                    <h2 class="font-semibold">
                        {{ t.dashboard.overallStatus }}
                    </h2>
                </div>

                <div class="h-[320px]">
                    <canvas ref="statusChartRef" />
                </div>
            </Card>

            <!-- Business Unit -->

            <Card class="card-custom md:col-span-8 bg-white">
                <div class="mb-4">
                    <h2 class="font-semibold">
                        {{ barChartTitle }}
                    </h2>
                </div>

                <div class="h-[320px]">
                    <canvas ref="barChartRef" />
                </div>
            </Card>
        </div>

        <!-- RECENT SUBMISSIONS -->

        <!-- <Card class="card-custom">
            <div class="mb-4">
                <h2 class="font-semibold">
                    Recent Employee Submissions
                </h2>
            </div>

            <div class="table-container">
                <table class="table-custom">
                    <thead>
                        <tr class="border-b border-border text-left text-xs uppercase text-slate-500">
                            <th class="py-3 w-2/6">Employee</th>
                            <th class="py-3 w-1/6">Period</th>
                            <th class="py-3 w-1/6">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-if="employeeDeclarations.data.length"
                            v-for="declaration in employeeDeclarations.data"
                            :key="declaration.id"
                            class="border-b border-slate-100"
                        >
                            <td class="py-4 font-medium">
                                {{ declaration.employee.name }} <p class="text-sm text-slate-400">({{ declaration.employee.employee_id }})</p>
                            </td>

                            <td class="py-4">
                                {{ declaration.period }}
                            </td>

                            <td class="py-4">
                                <StatusBadge :status="declaration.status" />
                            </td>
                        </tr>

                        <tr v-else>
                            <td colspan="4" class="py-10 text-center text-slate-500">
                                No employee declarations available.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
        <Card class="card-custom mt-6">
            <div class="mb-4">
                <h2 class="font-semibold">
                    Recent Non Employee Submissions
                </h2>
            </div>

            <div class="table-container">
                <table class="table-custom">
                    <thead>
                        <tr class="border-b border-border text-left text-xs uppercase text-slate-500">
                            <th class="py-3 w-2/6">Full Name</th>
                            <th class="py-3 w-1/6">Period</th>
                            <th class="py-3 w-1/6">Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-if="nonEmployeeDeclarations.data.length"
                            v-for="declaration in nonEmployeeDeclarations.data"
                            :key="declaration.id"
                            class="border-b border-slate-100"
                        >
                            <td class="py-4 font-medium">
                                {{ declaration.employee.name }}
                            </td>

                            <td class="py-4">
                                {{ declaration.period }}
                            </td>

                            <td class="py-4">
                                <StatusBadge :status="declaration.status" />
                            </td>
                        </tr>

                        <tr v-else>
                            <td colspan="4" class="py-10 text-center text-slate-500">
                                No non employee declarations available.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card> -->
    </AdminLayout>
</template>