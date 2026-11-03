<script setup lang="ts">
import FilterField from '@/Components/UI/FilterField.vue'
import { DEFAULT_EMPLOYEE_STATUS, useEmployeeStatusOptions } from '@/Composables/useEmployeeStatus'
import SortableHeader from '@/Components/UI/SortableHeader.vue'
import { toggleSort as toggleSortState } from '@/Composables/useSort'
import PageHeader from '@/Components/UI/PageHeader.vue'
import Card from '@/Components/UI/Card.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import SearchableSelect from '@/Components/UI/SearchableSelect.vue'
import FormStatusBadge from '@/Components/Forms/FormStatusBadge.vue'
import ManagerLayout from '@/Layouts/ManagerLayout.vue'
import { useLocale } from '@/Composables/useLocale'
import { formatDateTime, formatPeriod, periodMonth, periodYear } from '@/Utils/date'
import type { Paginated, FormRow, FormStatusFilter } from '@/Components/Forms/types'
import { Head, router } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'
import { cleanQuery, useDebounced } from '@/Composables/useFilterQuery'

type SortKey = 'period' | 'name' | 'level' | 'business_unit' | 'status' | 'signed_at'

const props = defineProps<{
    formType: { id: number, code: string, name: { en: string, id: string } }
    rows: Paginated<FormRow>
    filters: {
        // 'all' = every period.
        period: number | 'all'
        search: string
        status: FormStatusFilter | null
        business_unit: string | null
        employee_status: string
        latest_submission: boolean
        sort: SortKey
        direction: 'asc' | 'desc'
    }
    periods: number[]
    businessUnitOptions: string[]
}>()

const { t, locale } = useLocale()

// Laid out like the COI Team History: labelled filters above one table.
const filter = reactive({
    period: props.filters.period,
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    business_unit: props.filters.business_unit ?? '',
    employee_status: props.filters.employee_status ?? DEFAULT_EMPLOYEE_STATUS,
    latest_submission: props.filters.latest_submission,
    sort: props.filters.sort,
    direction: props.filters.direction,
})

// A filter or sort change lands back on page one -- a shorter result set
// would otherwise leave the manager on an empty page.
function applyFilter(overrides: Record<string, unknown> = {}) {
    router.get(
        route('manager.forms.team', { formType: props.formType.code }),
        cleanQuery({
            ...filter,
            // Sent explicitly: the server treats a missing value as "on".
            latest_submission: filter.latest_submission ? 1 : 0,
            per_page: props.rows.per_page,
            ...overrides,
        }),
        { preserveState: true, preserveScroll: true, replace: true },
    )
}

const onSearch = useDebounced(() => applyFilter())

const periodOptions = computed(() => [
    { value: 'all', label: t.value.teamHistory.allPeriods },
    ...props.periods.map(period => ({ value: period, label: formatPeriod(period, locale.value) })),
])

const businessUnitOptions = computed(() => [
    { value: '', label: t.value.teamHistory.allBusinessUnits },
    ...props.businessUnitOptions.map(unit => ({ value: unit, label: unit })),
])

const employeeStatusOptions = useEmployeeStatusOptions()

const statusOptions = computed(() => [
    { value: '', label: t.value.common.allStatus },
    { value: 'not_signed', label: t.value.forms.statusPending },
    { value: 'draft', label: t.value.forms.statusDraft },
    { value: 'signed', label: t.value.forms.statusSigned },
])

const columns = computed<{ key: SortKey | null, label: string }[]>(() => [
    { key: 'period', label: t.value.forms.periodYear },
    { key: null, label: t.value.forms.periodMonth },
    { key: 'name', label: t.value.common.name },
    { key: null, label: t.value.teamHistory.columnDesignation },
    { key: 'level', label: t.value.teamHistory.columnLevel },
    { key: 'business_unit', label: t.value.common.businessUnit },
    { key: 'status', label: t.value.teamHistory.formStatus },
    { key: 'signed_at', label: t.value.forms.columnSignedAt },
])

function toggleSort(key: SortKey) {
    toggleSortState(filter, key)

    applyFilter()
}

</script>

<template>
    <Head :title="`${formType.name[locale]} - ${t.nav.teamHistory}`" />

    <ManagerLayout>
        <PageHeader
            :title="`${formType.name[locale]} - ${t.nav.teamHistory}`"
            :description="t.forms.teamDescription"
        />

        <div class="mb-4 flex flex-wrap items-end gap-4">
            <!-- Period -->
            <FilterField
                :label="t.teamHistory.declarationPeriod"
                for="team-form-period"
            >
                <SearchableSelect
                    id="team-form-period"
                    v-model="filter.period"
                    :options="periodOptions"
                    class="w-48 max-w-full"
                    @change="applyFilter()"
                />
            </FilterField>

            <!-- Business Unit -->
            <FilterField
                :label="t.common.businessUnit"
                for="team-form-unit"
            >
                <SearchableSelect
                    id="team-form-unit"
                    v-model="filter.business_unit"
                    :options="businessUnitOptions"
                    class="w-64 max-w-full"
                    @change="applyFilter()"
                />
            </FilterField>

            <!-- Employee Status -->
            <FilterField
                :label="t.common.employeeStatus"
                for="team-form-employee-status"
            >
                <SearchableSelect
                    id="team-form-employee-status"
                    v-model="filter.employee_status"
                    :options="employeeStatusOptions"
                    class="w-48 max-w-full"
                    @change="applyFilter()"
                />
            </FilterField>

            <!-- Form Status -->
            <FilterField
                :label="t.teamHistory.formStatus"
                for="team-form-status"
            >
                <SearchableSelect
                    id="team-form-status"
                    v-model="filter.status"
                    :options="statusOptions"
                    class="w-52 max-w-full"
                    @change="applyFilter()"
                />
            </FilterField>

            <!-- Search -->
            <FilterField
                :label="t.common.search"
                for="team-form-search"
            >
                <input
                    id="team-form-search"
                    v-model="filter.search"
                    type="text"
                    :placeholder="t.report.searchPlaceholder"
                    class="min-w-56 rounded-md border border-border px-3 py-2 text-sm"
                    @input="onSearch"
                >
            </FilterField>

            <!-- Latest Submission -->
            <label class="flex cursor-pointer select-none items-center gap-2 rounded-md border border-border bg-white px-3 py-2 text-sm">
                <input
                    v-model="filter.latest_submission"
                    type="checkbox"
                    class="h-4 w-4 rounded border-border"
                    @change="applyFilter()"
                >
                {{ t.report.latestSubmission }}
            </label>
        </div>

        <Card>
            <div class="table-container">
                <table class="table-custom">
                    <thead>
                        <tr class="border-b border-border text-left text-xs uppercase text-slate-500">
                            <SortableHeader
                                v-for="col in columns"
                                :key="col.label"
                                class="py-3"
                                :label="col.label"
                                :column="col.key"
                                :sort="filter.sort"
                                :direction="filter.direction"
                                @sort="column => toggleSort(column as SortKey)"
                            />
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="row in rows.data"
                            :key="row.key"
                            class="border-b border-slate-100"
                        >
                            <td class="py-4 font-semibold">
                                {{ periodYear(row.period) }}
                            </td>

                            <td class="py-4 whitespace-nowrap">
                                {{ periodMonth(row.period, locale) }}
                            </td>

                            <td class="py-4">
                                <div class="font-semibold">{{ row.name }}</div>
                                <div class="text-xs text-slate-500">{{ row.employee_id || '-' }}</div>
                            </td>

                            <td class="py-4">
                                {{ row.designation || '-' }}
                            </td>

                            <td class="py-4">
                                {{ row.level || '-' }}
                            </td>

                            <td class="py-4">
                                {{ row.business_unit || '-' }}
                            </td>

                            <td class="py-4">
                                <FormStatusBadge :status="row.status" />
                                <div
                                    v-if="row.resign_required"
                                    class="mt-1 flex items-center gap-1 text-xs font-medium text-amber-700"
                                >
                                    <i class="fa-solid fa-rotate" />
                                    {{ t.forms.resignRequired }}
                                </div>
                            </td>

                            <td class="whitespace-nowrap py-4">
                                {{ row.status === 'signed' ? formatDateTime(row.signed_at) : '-' }}
                            </td>
                        </tr>

                        <tr v-if="!rows.data.length">
                            <td
                                :colspan="columns.length"
                                class="py-10 text-center text-sm text-slate-500"
                            >
                                {{ t.teamHistory.noRecords }}
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
    </ManagerLayout>
</template>
