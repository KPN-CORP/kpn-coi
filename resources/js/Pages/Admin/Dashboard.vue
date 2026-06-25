<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/UI/PageHeader.vue'
import Card from '@/Components/UI/Card.vue'
import StatCard from '@/Components/UI/StatCard.vue'
import StatusBadge from '@/Components/UI/StatusBadge.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Declaration } from '@/Config/declaration'
import { onMounted, ref } from 'vue'
import Chart from 'chart.js/auto'
import { useForm, router } from '@inertiajs/vue3'

interface DashboardStats {
    total: number
    pending: number
    submitted: number
    conflict: number
}

const statusChartRef = ref<HTMLCanvasElement | null>(
    null,
)

const businessUnitChartRef =
    ref<HTMLCanvasElement | null>(null)

const props = defineProps<{
    declarations: {
        data: Declaration[],
        links: any[]
    }
    stats: DashboardStats
    periods: number[]

    filters: {
        period?: string
        status?: string
    }
}>()

const filter = useForm({
    period: props.filters.period ?? '',
})

function applyFilter() {
    router.get(
        route('admin.dashboard'),
        {
            period: filter.period,
        },
        {
            preserveState: true,
            replace: true,
        },
    )
}

interface ReportFilters {
    period?: string | number
    status?: string
    search?: string
    pending?: boolean | number
    conflict?: boolean | number
}

function openReport(
    filters: ReportFilters = {},
) {
    window.open(
        route(
            'admin.report',
            {
                period: filter.period,
                ...filters,
            },
        ),
        '_blank',
    )
}

onMounted(() => {
    if (statusChartRef.value) {
        new Chart(
            statusChartRef.value,
            {
                type: 'doughnut',

                data: {
                    labels: [
                        'Submitted',
                        'Pending',
                    ],

                    datasets: [
                        {
                            data: [
                                props.stats.submitted,

                                props.stats.total -
                                    props.stats.submitted,
                            ],

                            backgroundColor: [
                                '#2f855a',
                                '#cbd5e1',
                            ],

                            borderWidth: 0,
                        },
                    ],
                },

                options: {
                    responsive: true,

                    maintainAspectRatio:
                        false,

                    cutout: '75%',

                    plugins: {
                        legend: {
                            position:
                                'bottom',
                        },
                    },
                },
            },
        )
    }
})

onMounted(() => {
    if (businessUnitChartRef.value) {
        new Chart(
            businessUnitChartRef.value,
            {
                type: 'bar',

                data: {
                    labels: [
                        'Corporation',
                        'Property',
                        'Plantations',
                        'Downstream',
                        'Cement',
                    ],

                    datasets: [
                        {
                            label:
                                'Submitted',

                            data: [
                                450,
                                310,
                                892,
                                312,
                                143,
                            ],

                            backgroundColor:
                                '#AB2F2B',

                            borderRadius: 4,
                        },

                        {
                            label:
                                'Pending',

                            data: [
                                12,
                                40,
                                156,
                                8,
                                64,
                            ],

                            backgroundColor:
                                '#e2e8f0',

                            borderRadius: 4,
                        },
                    ],
                },

                options: {
                    responsive: true,

                    maintainAspectRatio:
                        false,

                    plugins: {
                        legend: {
                            position:
                                'bottom',
                        },
                    },

                    scales: {
                        x: {
                            grid: {
                                display:
                                    false,
                            },
                        },

                        y: {
                            beginAtZero:
                                true,

                            border: {
                                display:
                                    false,
                            },
                        },
                    },
                },
            },
        )
    }
})
</script>

<template>
    <AdminLayout>
        <PageHeader
            title="Compliance Dashboard"
            description="Real-time overview of declaration submissions."
        />
        <Card class="mb-6">
            <div class="flex items-center gap-1">
                <select
                    v-model="filter.period"
                    class="rounded-md border border-border px-3 py-2 text-sm"
                    @change="applyFilter"
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
        </Card>

        <!-- KPI CARDS -->

        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4 mb-6">
            <Card class="stat-card">
                <div class="stat-num text-blue-700">
                    {{ stats.total }}
                </div>
                <div class="stat-title">
                    TOTAL EMPLOYEES
                </div>
            </Card>
            <Card class="stat-card cursor-pointer transition hover:-translate-y-1 hover:shadow-md" @click="openReport({status: 'submitted',})">
                <div class="stat-num text-green-600">
                    {{ stats.submitted }}
                </div>
                <div class="stat-title">
                    SUBMITTED
                </div>
            </Card>
            <Card class="stat-card cursor-pointer transition hover:-translate-y-1 hover:shadow-md" @click="openReport({status: 'pending',})">
                <div class="stat-num text-yellow-500">
                    {{ stats.pending }}
                </div>
                <div class="stat-title">
                    PENDING
                </div>
            </Card>
            <Card class="stat-card cursor-pointer transition hover:-translate-y-1 hover:shadow-md" @click="openReport({status: 'conflict',})">
                <div class="stat-num text-red-600">
                    {{ stats.conflict }}
                </div>
                <div class="stat-title">
                    CONFLICT INDICATED
                </div>
            </Card>
        </div>

        <!-- CHART PLACEHOLDER -->

        <div class="grid gap-6 md:grid-cols-12 mb-6">
            <!-- Overall Status -->

            <Card class="card-custom md:col-span-4 bg-white">
                <div class="mb-4">
                    <h2 class="font-semibold">
                        Overall Status
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
                        Submissions by Business Unit
                    </h2>
                </div>

                <div class="h-[320px]">
                    <canvas ref="businessUnitChartRef" />
                </div>
            </Card>
        </div>

        <!-- RECENT SUBMISSIONS -->

        <Card class="card-custom">
            <div class="mb-4">
                <h2 class="font-semibold">
                    Recent Submissions
                </h2>
            </div>

            <div class="table-container">
                <table class="table-custom">
                    <thead>
                        <tr
                            class="border-b border-border text-left text-xs uppercase text-slate-500"
                        >
                            <th class="py-3">
                                Employee
                            </th>

                            <th class="py-3">
                                Period
                            </th>

                            <th class="py-3">
                                Status
                            </th>
                        </tr>
                    </thead>

                    <tbody v-if="declarations.data.length">
                        <tr
                            v-for="declaration in declarations.data"
                            :key="declaration.id"
                            class="border-b border-slate-100"
                        >
                             <td class="py-4 font-medium">
                                {{ declaration.employee.name }}
                            </td>

                            <td class="py-4">
                                {{ declaration.employee.employee_id }}
                            </td>

                            <td class="py-4">
                                <StatusBadge
                                    :status="declaration.status"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </AdminLayout>
</template>