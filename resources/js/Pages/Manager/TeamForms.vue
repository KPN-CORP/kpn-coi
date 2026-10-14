<script setup lang="ts">
import PageHeader from '@/Components/UI/PageHeader.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import FormStatusBadge from '@/Components/Forms/FormStatusBadge.vue'
import ManagerLayout from '@/Layouts/ManagerLayout.vue'
import { useLocale } from '@/Composables/useLocale'
import { formatDateTime } from '@/Utils/date'
import type { Paginated, FormRow, FormStats, FormStatus } from '@/Components/Forms/types'
import { Head, router } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'
import debounce from 'lodash/debounce'

type TeamRow = FormRow & { relation: 'direct' | 'indirect', agreed_at: string | null }
type StatusTab = 'all' | 'not_signed' | 'draft' | 'signed' | 'no_template'

const props = defineProps<{
    formType: { id: number, code: string, name: { en: string, id: string } }
    rows: Paginated<TeamRow>
    stats: FormStats
    filters: {
        period: number
        search: string
        status: Exclude<StatusTab, 'all'> | 'pending' | null
        business_unit: string | null
        line: 'direct' | 'indirect' | null
        sort: 'name' | 'status' | 'signed_at' | 'business_unit'
        direction: 'asc' | 'desc'
    }
    periods: number[]
    businessUnitOptions: string[]
}>()

const { t, locale } = useLocale()

const filter = reactive({
    period: props.filters.period,
    search: props.filters.search ?? '',
    status: (props.filters.status ?? 'all') as StatusTab | 'pending',
    business_unit: props.filters.business_unit ?? '',
    line: props.filters.line ?? '',
    sort: props.filters.sort,
    direction: props.filters.direction,
})

function applyFilter(overrides: Record<string, unknown> = {}) {
    const query = { ...filter, per_page: props.rows.per_page, page: 1, ...overrides }

    router.get(
        route('manager.forms.team', { formType: props.formType.code }),
        Object.fromEntries(
            Object.entries(query).filter(([key, value]) => value !== '' && !(key === 'status' && value === 'all')),
        ),
        { preserveState: true, preserveScroll: true, replace: true },
    )
}

const onSearch = debounce(() => applyFilter(), 400)

function clearSearch() {
    filter.search = ''
    applyFilter()
}

// --- Summary ------------------------------------------------------------------

const notStarted = computed(() => Math.max(0, props.stats.pending - props.stats.draft))
const needed = computed(() => props.stats.signed + props.stats.pending)
const percent = (value: number) => (needed.value ? (value / needed.value) * 100 : 0)

// --- Status tabs ----------------------------------------------------------------

const tabs = computed(() => [
    { key: 'all' as StatusTab, label: t.value.forms.tabAll, count: props.stats.total },
    { key: 'not_signed' as StatusTab, label: t.value.forms.statusPending, count: props.stats.pending },
    { key: 'draft' as StatusTab, label: t.value.forms.statusDraft, count: props.stats.draft },
    { key: 'signed' as StatusTab, label: t.value.forms.statusSigned, count: props.stats.signed },
    { key: 'no_template' as StatusTab, label: t.value.forms.statusNoTemplate, count: props.stats.no_template },
].filter(tab => tab.key !== 'no_template' || tab.count > 0))

function setStatus(key: StatusTab) {
    filter.status = key
    applyFilter()
}

// --- Other filters -------------------------------------------------------------

const lines = computed(() => [
    { key: '', label: t.value.forms.lineAll },
    { key: 'direct', label: t.value.forms.lineDirect },
    { key: 'indirect', label: t.value.forms.lineIndirect },
])

function setLine(key: string) {
    filter.line = key
    applyFilter()
}

const activeChips = computed(() => {
    const chips: { key: 'search' | 'business_unit' | 'line', label: string }[] = []

    if (filter.search) {
        chips.push({ key: 'search', label: `“${filter.search}”` })
    }

    if (filter.business_unit) {
        chips.push({ key: 'business_unit', label: filter.business_unit })
    }

    if (filter.line) {
        chips.push({ key: 'line', label: lines.value.find(line => line.key === filter.line)?.label ?? filter.line })
    }

    return chips
})

function removeChip(key: 'search' | 'business_unit' | 'line') {
    filter[key] = ''
    applyFilter()
}

const hasFilters = computed(() => activeChips.value.length > 0 || filter.status !== 'all')

function resetFilters() {
    Object.assign(filter, { search: '', status: 'all', business_unit: '', line: '' })
    applyFilter()
}

// --- Sorting --------------------------------------------------------------------

function sortBy(column: typeof filter.sort) {
    if (filter.sort === column) {
        filter.direction = filter.direction === 'asc' ? 'desc' : 'asc'
    } else {
        filter.sort = column
        filter.direction = 'asc'
    }

    applyFilter({ page: props.rows.current_page })
}

function sortIcon(column: typeof filter.sort) {
    if (filter.sort !== column) {
        return 'fa-solid fa-sort text-slate-300'
    }

    return filter.direction === 'asc' ? 'fa-solid fa-sort-up text-primary' : 'fa-solid fa-sort-down text-primary'
}

const initials = (name: string) => name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map(part => part[0]?.toUpperCase())
    .join('')

const whenLabel = (row: TeamRow) => {
    if (row.status === 'signed') {
        return formatDateTime(row.signed_at)
    }

    if (row.status === 'draft') {
        return `${t.value.forms.draftSavedAt} ${formatDateTime(row.agreed_at)}`
    }

    return '-'
}

const statusOf = (row: TeamRow) => row.status as FormStatus
</script>

<template>
    <Head :title="`${formType.name[locale]} - ${t.nav.teamDeclaration}`" />

    <ManagerLayout>
        <PageHeader
            :title="`${formType.name[locale]} - ${t.nav.teamDeclaration}`"
            :description="t.forms.teamDescription"
        >
            <template #actions>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <i class="fa-regular fa-calendar" />
                    <span class="sr-only">{{ t.common.period }}</span>
                    <select
                        v-model="filter.period"
                        class="rounded-md border border-border bg-white py-2 pl-3 pr-8 text-sm font-semibold text-slate-800"
                        :aria-label="t.common.period"
                        @change="applyFilter()"
                    >
                        <option
                            v-for="period in periods"
                            :key="period"
                            :value="period"
                        >
                            {{ t.common.period }} {{ period }}
                        </option>
                    </select>
                </label>
            </template>
        </PageHeader>

        <!-- Progress -->
        <section class="mb-6 rounded-lg border border-border bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm text-slate-500">{{ t.forms.teamProgress }}</p>
                    <p class="mt-1 text-2xl font-bold text-slate-800">
                        {{ stats.signed }}
                        <span class="text-base font-medium text-slate-400">/ {{ needed }} {{ t.forms.signedLower }}</span>
                    </p>
                </div>
                <div class="text-left md:text-right">
                    <span class="text-3xl font-bold text-primary">{{ stats.rate }}%</span>
                </div>
            </div>

            <div
                class="mt-4 flex h-3 overflow-hidden rounded-full bg-slate-100"
                role="img"
                :aria-label="`${stats.signed} ${t.forms.statusSigned}, ${stats.draft} ${t.forms.statusDraft}, ${notStarted} ${t.forms.statusNotStarted}`"
            >
                <div
                    class="bg-green-500"
                    :style="{ width: `${percent(stats.signed)}%` }"
                />
                <div
                    class="bg-amber-400"
                    :style="{ width: `${percent(stats.draft)}%` }"
                />
            </div>

            <ul class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-slate-600">
                <li class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-green-500" />
                    {{ t.forms.statusSigned }} <strong class="text-slate-800">{{ stats.signed }}</strong>
                </li>
                <li class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-amber-400" />
                    {{ t.forms.statusDraft }} <strong class="text-slate-800">{{ stats.draft }}</strong>
                </li>
                <li class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-slate-300" />
                    {{ t.forms.statusNotStarted }} <strong class="text-slate-800">{{ notStarted }}</strong>
                </li>
                <li
                    v-if="stats.no_template"
                    class="flex items-center gap-2"
                >
                    <span class="h-2.5 w-2.5 rounded-full border border-slate-300 bg-white" />
                    {{ t.forms.statusNoTemplate }} <strong class="text-slate-800">{{ stats.no_template }}</strong>
                </li>
            </ul>
        </section>

        <!-- Filters -->
        <section class="mb-4 rounded-lg border border-border bg-white shadow-sm">
            <div
                class="flex gap-1 overflow-x-auto border-b border-border px-3 pt-2"
                role="tablist"
                :aria-label="t.common.status"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    role="tab"
                    :aria-selected="filter.status === tab.key"
                    class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium transition"
                    :class="filter.status === tab.key
                        ? 'border-primary text-primary'
                        : 'border-transparent text-slate-500 hover:text-slate-800'"
                    @click="setStatus(tab.key)"
                >
                    {{ tab.label }}
                    <span
                        class="rounded-full px-2 py-0.5 text-xs font-semibold"
                        :class="filter.status === tab.key ? 'bg-primary text-white' : 'bg-slate-100 text-slate-600'"
                    >{{ tab.count }}</span>
                </button>
            </div>

            <div class="flex flex-col gap-3 p-3 lg:flex-row lg:items-center">
                <div class="relative flex-1">
                    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400" />
                    <input
                        v-model="filter.search"
                        type="search"
                        class="w-full rounded-md border border-border py-2 pl-9 pr-9 text-sm"
                        :placeholder="t.forms.searchPlaceholder"
                        :aria-label="t.common.search"
                        @input="onSearch"
                    >
                    <button
                        v-if="filter.search"
                        type="button"
                        class="absolute right-2 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        :aria-label="t.forms.clearSearch"
                        @click="clearSearch"
                    >
                        <i class="fa-solid fa-xmark text-xs" />
                    </button>
                </div>

                <div
                    class="inline-flex shrink-0 rounded-md border border-border p-0.5"
                    role="group"
                    :aria-label="t.forms.reportingLine"
                >
                    <button
                        v-for="item in lines"
                        :key="item.key"
                        type="button"
                        class="rounded px-3 py-1.5 text-sm font-medium transition"
                        :class="filter.line === item.key ? 'bg-primary text-white' : 'text-slate-600 hover:text-slate-900'"
                        :aria-pressed="filter.line === item.key"
                        @click="setLine(item.key)"
                    >
                        {{ item.label }}
                    </button>
                </div>

                <select
                    v-if="businessUnitOptions.length > 1"
                    v-model="filter.business_unit"
                    class="shrink-0 rounded-md border border-border py-2 pl-3 pr-8 text-sm lg:w-52"
                    :aria-label="t.common.businessUnit"
                    @change="applyFilter()"
                >
                    <option value="">{{ t.forms.allBusinessUnits }}</option>
                    <option
                        v-for="unit in businessUnitOptions"
                        :key="unit"
                        :value="unit"
                    >
                        {{ unit }}
                    </option>
                </select>
            </div>

            <div
                v-if="hasFilters"
                class="flex flex-wrap items-center gap-2 border-t border-border px-3 py-2 text-sm"
            >
                <span class="text-slate-500">{{ t.forms.showing }} {{ rows.total }} {{ t.forms.people }}</span>

                <button
                    v-for="chip in activeChips"
                    :key="chip.key"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-primary hover:bg-red-100"
                    :aria-label="`${t.forms.removeFilter}: ${chip.label}`"
                    @click="removeChip(chip.key)"
                >
                    {{ chip.label }}
                    <i class="fa-solid fa-xmark" />
                </button>

                <button
                    type="button"
                    class="ml-auto text-xs font-semibold text-slate-500 underline hover:text-primary"
                    @click="resetFilters"
                >
                    {{ t.forms.resetFilters }}
                </button>
            </div>
        </section>

        <!-- People -->
        <section class="rounded-lg border border-border bg-white shadow-sm">
            <!-- Desktop table -->
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full text-sm">
                    <thead class="border-b border-border bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 uppercase"
                                    @click="sortBy('name')"
                                >
                                    {{ t.forms.columnName }} <i :class="sortIcon('name')" />
                                </button>
                            </th>
                            <th class="px-3 py-3">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 uppercase"
                                    @click="sortBy('business_unit')"
                                >
                                    {{ t.common.businessUnit }} <i :class="sortIcon('business_unit')" />
                                </button>
                            </th>
                            <th class="px-3 py-3">{{ t.forms.reportingLine }}</th>
                            <th class="px-3 py-3">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 uppercase"
                                    @click="sortBy('status')"
                                >
                                    {{ t.common.status }} <i :class="sortIcon('status')" />
                                </button>
                            </th>
                            <th class="px-5 py-3">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 uppercase"
                                    @click="sortBy('signed_at')"
                                >
                                    {{ t.forms.lastActivity }} <i :class="sortIcon('signed_at')" />
                                </button>
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="row in rows.data"
                            :key="row.key"
                            class="hover:bg-slate-50"
                        >
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600">
                                        {{ initials(row.name) }}
                                    </span>
                                    <div class="min-w-0">
                                        <div class="truncate font-medium text-slate-800">{{ row.name }}</div>
                                        <div class="truncate text-xs text-slate-500">
                                            {{ row.employee_id || '-' }}
                                            <template v-if="row.designation"> &middot; {{ row.designation }}</template>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3 text-slate-600">{{ row.business_unit || '-' }}</td>
                            <td class="px-3 py-3">
                                <span
                                    class="rounded px-2 py-0.5 text-xs font-medium"
                                    :class="row.relation === 'direct' ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-600'"
                                >
                                    {{ row.relation === 'direct' ? t.forms.lineDirect : t.forms.lineIndirect }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <FormStatusBadge :status="statusOf(row)" />
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ whenLabel(row) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Phone cards -->
            <ul class="divide-y divide-slate-100 md:hidden">
                <li
                    v-for="row in rows.data"
                    :key="row.key"
                    class="flex items-start gap-3 px-4 py-3"
                >
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600">
                        {{ initials(row.name) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <span class="font-medium text-slate-800">{{ row.name }}</span>
                            <FormStatusBadge :status="statusOf(row)" />
                        </div>
                        <div class="mt-0.5 text-xs text-slate-500">
                            {{ row.employee_id || '-' }} &middot; {{ row.business_unit || '-' }}
                            &middot; {{ row.relation === 'direct' ? t.forms.lineDirect : t.forms.lineIndirect }}
                        </div>
                        <div
                            v-if="whenLabel(row) !== '-'"
                            class="mt-1 text-xs text-slate-500"
                        >
                            {{ whenLabel(row) }}
                        </div>
                    </div>
                </li>
            </ul>

            <!-- Empty -->
            <div
                v-if="!rows.data.length"
                class="flex flex-col items-center gap-2 px-6 py-12 text-center"
            >
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-xl text-slate-400">
                    <i class="fa-solid fa-user-slash" />
                </span>
                <p class="font-semibold text-slate-700">{{ t.forms.noRows }}</p>
                <button
                    v-if="hasFilters"
                    type="button"
                    class="text-sm font-semibold text-primary underline"
                    @click="resetFilters"
                >
                    {{ t.forms.resetFilters }}
                </button>
            </div>

            <div class="border-t border-border px-4">
                <Pagination
                    :links="rows.links"
                    :per-page="rows.per_page"
                    :total="rows.total"
                    :from="rows.from"
                    :to="rows.to"
                    @update:per-page="value => applyFilter({ per_page: value })"
                />
            </div>
        </section>
    </ManagerLayout>
</template>
