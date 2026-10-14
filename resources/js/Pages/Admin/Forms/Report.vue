<script setup lang="ts">
import PageHeader from '@/Components/UI/PageHeader.vue'
import Card from '@/Components/UI/Card.vue'
import StatCard from '@/Components/UI/StatCard.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import SearchSelect from '@/Components/SearchSelect.vue'
import FormStatusBadge from '@/Components/Forms/FormStatusBadge.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { useLocale } from '@/Composables/useLocale'
import { usePermission } from '@/Composables/usePermission'
import { formatDateTime } from '@/Utils/date'
import FormTypeSelect from '@/Components/Forms/FormTypeSelect.vue'
import type { Paginated, FormRow, FormStats, FormTypeOption } from '@/Components/Forms/types'
import { Head, router } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'
import debounce from 'lodash/debounce'

const props = defineProps<{
    rows: Paginated<FormRow>
    stats: FormStats
    filters: {
        form_type: number | null
        period: number
        type: string | null
        business_unit: string | null
        contribution_level: string | null
        search: string | null
        status: string | null
        sort: string | null
        direction: string
    }
    formTypes: FormTypeOption[]
    periods: number[]
    businessUnitOptions: string[]
    contributionLevelOptions: { code: string, name: string, business_unit: string }[]
}>()

const { t, locale } = useLocale()
const { can } = usePermission()

const filter = reactive({
    form_type: props.filters.form_type,
    period: props.filters.period,
    type: props.filters.type ?? '',
    business_unit: props.filters.business_unit ?? '',
    contribution_level: props.filters.contribution_level ?? '',
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    sort: props.filters.sort ?? '',
    direction: props.filters.direction ?? 'asc',
})

function query(overrides: Record<string, unknown> = {}) {
    return Object.fromEntries(
        Object.entries({
            ...filter,
            per_page: props.rows.per_page,
            ...overrides,
        }).filter(([, value]) => value !== '' && value !== null && value !== undefined),
    )
}

function applyFilter(overrides: Record<string, unknown> = {}) {
    router.get(route('admin.forms.report'), query({ page: 1, ...overrides }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const onSearch = debounce(() => applyFilter(), 400)

const businessUnitSelect = computed(() => [
    { value: '', label: t.value.forms.allBusinessUnits },
    ...props.businessUnitOptions.map(unit => ({ value: unit, label: unit })),
])

// Same cascade as the COI report: a business unit narrows the companies.
const contributionLevelSelect = computed(() => [
    { value: '', label: t.value.forms.allContributionLevels },
    ...props.contributionLevelOptions
        .filter(option => !filter.business_unit
            || filter.business_unit === 'KPN Corporation'
            || option.business_unit === filter.business_unit)
        .map(option => ({ value: option.code, label: option.name })),
])

function onBusinessUnit(value: string | number | null) {
    filter.business_unit = value == null ? '' : String(value)
    filter.contribution_level = ''
    applyFilter()
}

function onContributionLevel(value: string | number | null) {
    filter.contribution_level = value == null ? '' : String(value)
    applyFilter()
}

const columns = computed(() => [
    { key: 'name', label: t.value.forms.columnName, sortable: true },
    { key: 'employee_id', label: t.value.forms.columnEmployeeId, sortable: true },
    { key: 'type', label: t.value.forms.columnType, sortable: false },
    { key: 'business_unit', label: t.value.common.businessUnit, sortable: true },
    { key: 'template', label: t.value.forms.columnTemplate, sortable: false },
    { key: 'status', label: t.value.common.status, sortable: true },
    { key: 'signed_at', label: t.value.forms.columnSignedAt, sortable: true },
])

function toggleSort(key: string) {
    if (filter.sort === key) {
        filter.direction = filter.direction === 'asc' ? 'desc' : 'asc'
    } else {
        filter.sort = key
        filter.direction = 'asc'
    }

    applyFilter({ page: props.rows.current_page })
}

function sortIcon(key: string) {
    if (filter.sort !== key) {
        return 'fa-solid fa-sort text-slate-300'
    }

    return filter.direction === 'asc'
        ? 'fa-solid fa-sort-up text-slate-600'
        : 'fa-solid fa-sort-down text-slate-600'
}

function exportExcel() {
    window.location.href = route('admin.forms.report.export', query())
}

function downloadPdf(row: FormRow, language: 'en' | 'id') {
    window.open(
        route('admin.forms.report.pdf', { declaration: row.declaration_id, locale: language }),
        '_blank',
    )
}
</script>

<template>
    <Head :title="t.forms.reportTitle" />

    <AdminLayout>
        <PageHeader
            :title="t.forms.reportTitle"
            :description="t.forms.reportDescription"
        >
            <template #actions>
                <button
                    v-if="can('form_report.export')"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white"
                    @click="exportExcel"
                >
                    <i class="fa-solid fa-file-excel" />
                    {{ t.forms.exportExcel }}
                </button>
            </template>
        </PageHeader>

        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
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
                :title="t.forms.statRate"
                :value="`${stats.rate}%`"
                icon="fa-solid fa-percent"
            />
        </div>

        <Card class="mb-6">
            <div class="grid grid-cols-1 items-end gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">{{ t.forms.formType }}</label>
                    <FormTypeSelect
                        v-model="filter.form_type"
                        :options="formTypes"
                        @change="applyFilter()"
                    />
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">{{ t.common.period }}</label>
                    <select
                        v-model="filter.period"
                        class="w-full rounded-md border border-border px-3 py-2 text-sm"
                        @change="applyFilter()"
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
                        @change="applyFilter()"
                    >
                        <option value="">{{ t.forms.allTypes }}</option>
                        <option value="employee">{{ t.common.employee }}</option>
                        <option value="non_employee">{{ t.common.nonEmployee }}</option>
                    </select>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">{{ t.common.status }}</label>
                    <select
                        v-model="filter.status"
                        class="w-full rounded-md border border-border px-3 py-2 text-sm"
                        @change="applyFilter()"
                    >
                        <option value="">{{ t.forms.allStatus }}</option>
                        <option value="signed">{{ t.forms.statusSigned }}</option>
                        <option value="draft">{{ t.forms.statusDraft }}</option>
                        <option value="pending">{{ t.forms.statusPending }}</option>
                        <option value="no_template">{{ t.forms.statusNoTemplate }}</option>
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

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">{{ t.forms.contributionLevel }}</label>
                    <SearchSelect
                        :model-value="filter.contribution_level"
                        :options="contributionLevelSelect"
                        :placeholder="t.forms.allContributionLevels"
                        @update:model-value="onContributionLevel"
                    />
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-slate-700">{{ t.common.search }}</label>
                    <input
                        v-model="filter.search"
                        type="search"
                        class="w-full rounded-md border border-border px-3 py-2 text-sm"
                        :placeholder="t.forms.searchPlaceholder"
                        @input="onSearch"
                    >
                </div>
            </div>
        </Card>

        <Card>
            <div class="table-container">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th
                                v-for="column in columns"
                                :key="column.key"
                                class="whitespace-nowrap py-3"
                                :class="column.sortable ? 'cursor-pointer select-none hover:text-slate-700' : ''"
                                @click="column.sortable && toggleSort(column.key)"
                            >
                                <span class="inline-flex items-center gap-1.5">
                                    {{ column.label }}
                                    <i
                                        v-if="column.sortable"
                                        :class="sortIcon(column.key)"
                                    />
                                </span>
                            </th>
                            <th class="py-3">{{ t.common.action }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="row in rows.data"
                            :key="row.key"
                            class="border-b border-slate-100 text-center"
                        >
                            <td class="py-4 text-left">
                                <div class="font-medium text-slate-800">{{ row.name }}</div>
                                <div class="text-xs text-slate-400">
                                    {{ row.designation || '' }}
                                    <template v-if="row.designation && row.contribution_level"> &middot; </template>
                                    {{ row.contribution_level || '' }}
                                </div>
                            </td>
                            <td class="py-4">{{ row.employee_id || '-' }}</td>
                            <td class="py-4 whitespace-nowrap">
                                {{ row.type === 'employee' ? t.common.employee : t.common.nonEmployee }}
                            </td>
                            <td class="py-4">{{ row.business_unit || '-' }}</td>
                            <td class="py-4">{{ row.template_title?.[locale] ?? '-' }}</td>
                            <td class="py-4">
                                <FormStatusBadge :status="row.status" />
                            </td>
                            <td class="py-4 whitespace-nowrap">{{ formatDateTime(row.signed_at) }}</td>
                            <td class="py-4">
                                <div
                                    v-if="row.declaration_id"
                                    class="flex justify-center gap-2"
                                >
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary-custom btn-sm"
                                        @click="downloadPdf(row, 'id')"
                                    >
                                        <i class="fa-solid fa-file-pdf" />
                                        ID
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary-custom btn-sm"
                                        @click="downloadPdf(row, 'en')"
                                    >
                                        <i class="fa-solid fa-file-pdf" />
                                        EN
                                    </button>
                                </div>
                                <span
                                    v-else
                                    class="text-slate-300"
                                >-</span>
                            </td>
                        </tr>

                        <tr v-if="!rows.data.length">
                            <td
                                colspan="8"
                                class="py-8 text-center text-slate-500"
                            >
                                {{ t.forms.noRows }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination
                :links="rows.links"
                :per-page="rows.per_page"
                :total="rows.total"
                :from="rows.from"
                :to="rows.to"
                @update:per-page="value => applyFilter({ per_page: value })"
            />
        </Card>
    </AdminLayout>
</template>
