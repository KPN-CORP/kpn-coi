<script setup lang="ts">
import PageHeader from '@/Components/UI/PageHeader.vue'
import Card from '@/Components/UI/Card.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import SearchableSelect from '@/Components/UI/SearchableSelect.vue'
import FormStatusBadge from '@/Components/Forms/FormStatusBadge.vue'
import ManagerLayout from '@/Layouts/ManagerLayout.vue'
import { useLocale } from '@/Composables/useLocale'
import { formatDateTime } from '@/Utils/date'
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
    ...props.periods.map(period => ({ value: period, label: String(period) })),
])

const businessUnitOptions = computed(() => [
    { value: '', label: t.value.teamHistory.allBusinessUnits },
    ...props.businessUnitOptions.map(unit => ({ value: unit, label: unit })),
])

const statusOptions = computed(() => [
    { value: '', label: t.value.common.allStatus },
    { value: 'not_signed', label: t.value.forms.statusPending },
    { value: 'draft', label: t.value.forms.statusDraft },
    { value: 'signed', label: t.value.forms.statusSigned },
    { value: 'no_template', label: t.value.forms.statusNoTemplate },
])

const columns = computed<{ key: SortKey | null, label: string }[]>(() => [
    { key: 'period', label: t.value.common.period },
    { key: 'name', label: t.value.common.name },
    { key: null, label: t.value.teamHistory.columnDesignation },
    { key: 'level', label: t.value.teamHistory.columnLevel },
    { key: 'business_unit', label: t.value.common.businessUnit },
    { key: 'status', label: t.value.teamHistory.formStatus },
    { key: 'signed_at', label: t.value.forms.columnSignedAt },
])

function toggleSort(key: SortKey) {
    if (filter.sort === key) {
        filter.direction = filter.direction === 'asc' ? 'desc' : 'asc'
    } else {
        filter.sort = key
        filter.direction = 'asc'
    }

    applyFilter()
}

function sortIcon(key: SortKey) {
    if (filter.sort !== key) {
        return 'fa-solid fa-sort text-slate-300'
    }

    return filter.direction === 'asc'
        ? 'fa-solid fa-sort-up text-slate-600'
        : 'fa-solid fa-sort-down text-slate-600'
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
            <div class="flex flex-col gap-2">
                <label
                    for="team-form-period"
                    class="text-sm font-medium text-slate-700"
                >
                    {{ t.teamHistory.declarationPeriod }}
                </label>

                <SearchableSelect
                    id="team-form-period"
                    v-model="filter.period"
                    :options="periodOptions"
                    class="w-48 max-w-full"
                    @change="applyFilter()"
                />
            </div>

            <!-- Business Unit -->
            <div class="flex flex-col gap-2">
                <label
                    for="team-form-unit"
                    class="text-sm font-medium text-slate-700"
                >
                    {{ t.common.businessUnit }}
                </label>

                <SearchableSelect
                    id="team-form-unit"
                    v-model="filter.business_unit"
                    :options="businessUnitOptions"
                    class="w-64 max-w-full"
                    @change="applyFilter()"
                />
            </div>

            <!-- Form Status -->
            <div class="flex flex-col gap-2">
                <label
                    for="team-form-status"
                    class="text-sm font-medium text-slate-700"
                >
                    {{ t.teamHistory.formStatus }}
                </label>

                <SearchableSelect
                    id="team-form-status"
                    v-model="filter.status"
                    :options="statusOptions"
                    class="w-52 max-w-full"
                    @change="applyFilter()"
                />
            </div>

            <!-- Search -->
            <div class="flex flex-col gap-2">
                <label
                    for="team-form-search"
                    class="text-sm font-medium text-slate-700"
                >
                    {{ t.common.search }}
                </label>

                <input
                    id="team-form-search"
                    v-model="filter.search"
                    type="text"
                    :placeholder="t.report.searchPlaceholder"
                    class="min-w-56 rounded-md border border-border px-3 py-2 text-sm"
                    @input="onSearch"
                >
            </div>

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
                            <th
                                v-for="col in columns"
                                :key="col.label"
                                class="whitespace-nowrap py-3"
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
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="row in rows.data"
                            :key="row.key"
                            class="border-b border-slate-100"
                        >
                            <td class="py-4">
                                {{ row.period }}
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
