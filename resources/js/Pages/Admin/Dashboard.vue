<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import Card from '@/Components/UI/Card.vue'
import StatCard from '@/Components/UI/StatCard.vue'
import StatusBadge from '@/Components/UI/StatusBadge.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Declaration } from '@/Config/declaration'
import { onMounted, ref, watch, reactive } from 'vue'
import Chart from 'chart.js/auto'
import { useForm, router } from '@inertiajs/vue3'
import type { Chart as ChartInstance } from 'chart.js'
import Swal from 'sweetalert2'
import { useLocale } from '@/Composables/useLocale'

const { t, locale } = useLocale()

const statusChart = ref<ChartInstance | null>(null)
const barChart = ref<ChartInstance | null>(null)

interface DashboardStats {
    total: number
    pending: number
    submitted: number
    conflict: number
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

        data: {
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

                datasets: props.barChart.datasets,

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                plugins: {

                    title: {

                        display: true,

                        text: props.barChart.title,

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

        filters: {
            period?: string
            status?: string
            business_unit?: string
            type?: string
        }
    }>()

const filter = useForm({
    period: props.filters.period || new Date().getFullYear().toString(),
    status: props.filters.status ?? '',
    business_unit: props.filters.business_unit ?? '',
    type: props.filters.type ?? 'employee',
})

function applyFilter() {
    router.get(
        route('admin.dashboard'),
        {
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

    ctx.drawImage(
        canvas,
        0,
        0,
        width,
        height,
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
            total = props.stats.submitted
            break

        case 'pending':
            total = props.stats.pending
            break

        case 'conflict':
            total = props.stats.conflict
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

    window.open(
        route(
            'admin.report',
            {
                period: filter.period,
                status: filter.status,
                type: filter.type,
                business_unit: filter.business_unit,
                ...filters,
            },
        ),
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

// Chart labels are baked in at render time, so redraw on language change.
watch(locale, () => {
    renderStatusChart()
})
</script>

<template>
    <AdminLayout>
        <PageHeader
            :title="t.dashboard.title"
            :description="t.dashboard.description"
        />
        <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex flex-wrap items-end gap-4">

                <!-- Reporting Period -->
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">
                        {{ t.dashboard.reportingPeriod }}
                    </label>

                    <select
                        v-model="filter.period"
                        class="rounded-md border border-border px-3 py-2 text-sm"
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
                        class="rounded-md border border-border px-3 py-2 text-sm"
                        @change="onTypeChanged"
                    >
                        <option value="employee">
                            {{ t.common.employee }}
                        </option>

                        <option value="non_employee">
                            {{ t.common.nonEmployee }}
                        </option>
                    </select>
                </div>

                <!-- Business Unit -->
                <div :class="[
                        'flex flex-col gap-2']">
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
                            v-for="item in businessUnitOptions"
                            :key="item"
                            :value="item"
                        >
                            {{ item }}
                        </option>
                    </select>
                </div>

                <!-- Status -->
                <!-- <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">
                        Status
                    </label>

                    <select
                        v-model="filter.status"
                        class="rounded-md border border-border px-3 py-2 text-sm"
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
                    {{ t.dashboard.kpiTotal }} {{ filter.type === 'employee'
                            ? t.common.employee
                            : t.common.nonEmployee }}
                </div>
            </Card>
            <Card class="stat-card cursor-pointer transition hover:-translate-y-1 hover:shadow-md" @click="openReport({status: 'submitted',})">
                <div class="stat-num text-green-600">
                    {{ stats.submitted }}
                </div>
                <div class="stat-title">
                    {{ t.dashboard.kpiTotal }} {{ filter.type === 'employee'
                            ? t.common.employee
                            : t.common.nonEmployee }} <br> {{ t.dashboard.kpiSubmitted }}
                </div>
            </Card>
            <Card class="stat-card cursor-pointer transition hover:-translate-y-1 hover:shadow-md" @click="openReport({status: 'pending',})">
                <div class="stat-num text-yellow-500">
                    {{ stats.pending }}
                </div>
                <div class="stat-title">
                    {{ t.dashboard.kpiTotal }} {{ filter.type === 'employee'
                            ? t.common.employee
                            : t.common.nonEmployee }} <br> {{ t.dashboard.kpiNotSubmitted }}
                </div>
            </Card>
            <Card class="stat-card cursor-pointer transition hover:-translate-y-1 hover:shadow-md" @click="openReport({declaration_status: 'conflict',})">
                <div class="stat-num text-red-600">
                    {{ stats.conflict }}
                </div>
                <div class="stat-title">
                    {{ t.dashboard.kpiTotal }} {{ filter.type === 'employee'
                            ? t.common.employee
                            : t.common.nonEmployee }} <br> {{ t.dashboard.kpiConflict }}
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
                        {{ t.dashboard.submissionsByBusinessUnit }}
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