<script setup lang="ts">
import PageHeader from '@/Components/UI/PageHeader.vue'
import Card from '@/Components/UI/Card.vue'
import StatCard from '@/Components/UI/StatCard.vue'
import SearchSelect from '@/Components/SearchSelect.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useLocale } from '@/Composables/useLocale'
import { usePermission } from '@/Composables/usePermission'
import { formatDateTime } from '@/Utils/date'
import FormTypeSelect from '@/Components/Forms/FormTypeSelect.vue'
import type { Localized, FormStats, FormTypeOption } from '@/Components/Forms/types'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import Chart from 'chart.js/auto'
import type { Chart as ChartInstance } from 'chart.js'

interface UnitStats extends FormStats {
    business_unit: string
}

interface TemplateSummary {
    id: number
    title: Localized
    business_units: string[]
    publish_at: string | null
    is_active: boolean
    is_published: boolean
    signed: number
    pending: number
}

const props = defineProps<{
    stats: FormStats
    byBusinessUnit: UnitStats[]
    templates: TemplateSummary[]
    filters: { form_type: number | null, period: number, type: string | null, business_unit: string | null }
    formTypes: FormTypeOption[]
    periods: number[]
    businessUnitOptions: string[]
}>()

const { t, locale } = useLocale()
const { can } = usePermission()

const filter = reactive({
    form_type: props.filters.form_type,
    period: props.filters.period,
    type: props.filters.type ?? '',
    business_unit: props.filters.business_unit ?? '',
})

function applyFilter() {
    router.get(
        route('admin.forms.dashboard'),
        Object.fromEntries(Object.entries(filter).filter(([, value]) => value !== '')),
        { preserveState: true, preserveScroll: true, replace: true },
    )
}

const businessUnitSelect = computed(() => [
    { value: '', label: t.value.forms.allBusinessUnits },
    ...props.businessUnitOptions.map(unit => ({ value: unit, label: unit })),
])

function onBusinessUnit(value: string | number | null) {
    filter.business_unit = value == null ? '' : String(value)
    applyFilter()
}

// --- Chart -----------------------------------------------------------------

const chartCanvas = ref<HTMLCanvasElement | null>(null)
let chart: ChartInstance | null = null

function renderChart() {
    chart?.destroy()
    chart = null

    if (!chartCanvas.value || !props.byBusinessUnit.length) {
        return
    }

    chart = new Chart(chartCanvas.value, {
        type: 'bar',
        data: {
            labels: props.byBusinessUnit.map(unit => unit.business_unit),
            datasets: [
                {
                    label: t.value.forms.statSigned,
                    data: props.byBusinessUnit.map(unit => unit.signed),
                    backgroundColor: '#AB2F2B',
                    borderRadius: 4,
                },
                {
                    label: t.value.forms.statPending,
                    data: props.byBusinessUnit.map(unit => unit.pending),
                    backgroundColor: '#E2E8F0',
                    borderRadius: 4,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { stacked: true, grid: { display: false } },
                y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
            },
            plugins: {
                legend: { position: 'bottom' },
            },
        },
    })
}

onMounted(renderChart)
onBeforeUnmount(() => chart?.destroy())

watch(
    () => [props.byBusinessUnit, locale.value],
    () => nextTick(renderChart),
)

function templateState(template: TemplateSummary) {
    if (!template.is_active) {
        return { label: t.value.forms.inactive, classes: 'bg-slate-50 text-slate-600 border-slate-200' }
    }

    return template.is_published
        ? { label: t.value.forms.active, classes: 'bg-green-50 text-green-700 border-green-200' }
        : { label: t.value.forms.scheduled, classes: 'bg-amber-50 text-amber-700 border-amber-200' }
}

function rate(template: TemplateSummary) {
    const total = template.signed + template.pending

    return total ? Math.round((template.signed / total) * 1000) / 10 : 0
}
</script>

<template>
    <Head :title="t.forms.dashboardTitle" />

    <AdminLayout>
        <PageHeader
            :title="t.forms.dashboardTitle"
            :description="t.forms.dashboardDescription"
        >
            <template #actions>
                <Link
                    v-if="can('form_report.view')"
                    :href="route('admin.forms.report', { form_type: filter.form_type, period: filter.period })"
                    class="btn btn-outline-secondary"
                >
                    <i class="fa-solid fa-clipboard-check" />
                    {{ t.nav.formReport }}
                </Link>
            </template>
        </PageHeader>

        <Card class="mb-6">
            <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">{{ t.forms.formType }}</label>
                    <FormTypeSelect
                        v-model="filter.form_type"
                        :options="formTypes"
                        @change="applyFilter"
                    />
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">{{ t.common.period }}</label>
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

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">{{ t.forms.type }}</label>
                    <select
                        v-model="filter.type"
                        class="w-full rounded-md border border-border px-3 py-2 text-sm"
                        @change="applyFilter"
                    >
                        <option value="">{{ t.forms.allTypes }}</option>
                        <option value="employee">{{ t.common.employee }}</option>
                        <option value="non_employee">{{ t.common.nonEmployee }}</option>
                    </select>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">{{ t.common.businessUnit }}</label>
                    <SearchSelect
                        :model-value="filter.business_unit"
                        :options="businessUnitSelect"
                        :placeholder="t.forms.allBusinessUnits"
                        @update:model-value="onBusinessUnit"
                    />
                </div>
            </div>
        </Card>

        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-5">
            <StatCard
                :title="t.forms.statTotal"
                :value="stats.total"
                icon="fa-solid fa-users"
            />
            <StatCard
                :title="t.forms.statSigned"
                :value="stats.signed"
                icon="fa-solid fa-file-signature"
            />
            <StatCard
                :title="t.forms.statPending"
                :value="stats.pending"
                icon="fa-solid fa-hourglass-half"
            />
            <StatCard
                :title="t.forms.statNoTemplate"
                :value="stats.no_template"
                icon="fa-solid fa-file-circle-xmark"
            />
            <StatCard
                :title="t.forms.statRate"
                :value="`${stats.rate}%`"
                icon="fa-solid fa-percent"
            />
        </div>

        <div class="grid gap-6 xl:grid-cols-5">
            <div class="rounded-md border border-border bg-white p-5 shadow-sm xl:col-span-3">
                <h2 class="mb-4 font-bold text-slate-800">{{ t.forms.chartTitle }}</h2>

                <div
                    v-if="byBusinessUnit.length"
                    class="relative h-80"
                >
                    <canvas ref="chartCanvas" />
                </div>

                <p
                    v-else
                    class="py-16 text-center text-sm text-slate-500"
                >
                    {{ t.forms.noChartData }}
                </p>
            </div>

            <div class="rounded-md border border-border bg-white p-5 shadow-sm xl:col-span-2">
                <h2 class="mb-4 font-bold text-slate-800">{{ t.forms.templatesInPeriod }}</h2>

                <ul
                    v-if="templates.length"
                    class="space-y-4"
                >
                    <li
                        v-for="template in templates"
                        :key="template.id"
                        class="rounded-md border border-slate-100 p-3"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-medium text-slate-800">{{ template.title[locale] }}</div>
                                <div class="mt-0.5 text-xs text-slate-500">
                                    {{ template.business_units.length ? template.business_units.join(', ') : t.forms.allBusinessUnits }}
                                    &middot; {{ formatDateTime(template.publish_at) }}
                                </div>
                            </div>

                            <span
                                class="shrink-0 rounded-full border px-2 py-0.5 text-xs font-semibold"
                                :class="templateState(template).classes"
                            >
                                {{ templateState(template).label }}
                            </span>
                        </div>

                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full bg-primary"
                                :style="{ width: `${rate(template)}%` }"
                            />
                        </div>

                        <div class="mt-1 flex justify-between text-xs text-slate-500">
                            <span>{{ template.signed }} {{ t.forms.statSigned.toLowerCase() }} / {{ template.signed + template.pending }}</span>
                            <span class="font-semibold text-slate-700">{{ rate(template) }}%</span>
                        </div>
                    </li>
                </ul>

                <p
                    v-else
                    class="py-8 text-center text-sm text-slate-500"
                >
                    {{ t.forms.noTemplates }}
                </p>
            </div>
        </div>
    </AdminLayout>
</template>
