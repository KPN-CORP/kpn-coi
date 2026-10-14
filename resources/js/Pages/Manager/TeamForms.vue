<script setup lang="ts">
import PageHeader from '@/Components/UI/PageHeader.vue'
import Card from '@/Components/UI/Card.vue'
import StatCard from '@/Components/UI/StatCard.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import FormStatusBadge from '@/Components/Forms/FormStatusBadge.vue'
import ManagerLayout from '@/Layouts/ManagerLayout.vue'
import { useLocale } from '@/Composables/useLocale'
import { formatDateTime } from '@/Utils/date'
import FormTypeSelect from '@/Components/Forms/FormTypeSelect.vue'
import type { Paginated, FormRow, FormStats, FormTypeOption } from '@/Components/Forms/types'
import { Head, router } from '@inertiajs/vue3'
import { reactive } from 'vue'
import debounce from 'lodash/debounce'

const props = defineProps<{
    formType: { id: number, code: string, name: { en: string, id: string } }
    rows: Paginated<FormRow>
    stats: FormStats
    filters: { form_type: number | null, period: number, search: string, status: string | null, business_unit: string | null }
    formTypes: FormTypeOption[]
    periods: number[]
    businessUnitOptions: string[]
}>()

const { t, locale } = useLocale()

const filter = reactive({
    form_type: props.filters.form_type as number | string | null,
    period: props.filters.period,
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    business_unit: props.filters.business_unit ?? '',
})

function applyFilter(overrides: Record<string, unknown> = {}) {
    // The form type is part of the URL (it is the menu entry), not a filter.
    const code = props.formTypes.find(type => type.id === Number(filter.form_type))?.code ?? props.formType.code
    const { form_type: _formType, ...query } = filter

    router.get(
        route('manager.forms.team', { formType: code }),
        Object.fromEntries(
            Object.entries({ ...query, per_page: props.rows.per_page, page: 1, ...overrides })
                .filter(([, value]) => value !== ''),
        ),
        { preserveState: true, preserveScroll: true, replace: true },
    )
}

const onSearch = debounce(() => applyFilter(), 400)
</script>

<template>
    <Head :title="`${formType.name[locale]} - ${t.nav.teamDeclaration}`" />

    <ManagerLayout>
        <PageHeader
            :title="`${formType.name[locale]} - ${t.nav.teamDeclaration}`"
            :description="t.forms.teamDescription"
        />

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

        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="sm:w-56">
                <FormTypeSelect
                    v-model="filter.form_type"
                    :options="formTypes"
                    @change="applyFilter()"
                />
            </div>

            <select
                v-model="filter.period"
                class="rounded-md border border-border px-3 py-2 text-sm"
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

            <select
                v-model="filter.business_unit"
                class="rounded-md border border-border px-3 py-2 text-sm"
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

            <select
                v-model="filter.status"
                class="rounded-md border border-border px-3 py-2 text-sm"
                @change="applyFilter()"
            >
                <option value="">{{ t.forms.allStatus }}</option>
                <option value="signed">{{ t.forms.statusSigned }}</option>
                <option value="draft">{{ t.forms.statusDraft }}</option>
                <option value="pending">{{ t.forms.statusPending }}</option>
                <option value="no_template">{{ t.forms.statusNoTemplate }}</option>
            </select>

            <input
                v-model="filter.search"
                type="search"
                class="w-full rounded-md border border-border px-3 py-2 text-sm sm:max-w-xs"
                :placeholder="t.forms.searchPlaceholder"
                @input="onSearch"
            >
        </div>

        <Card>
            <div class="table-container">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th class="py-3 text-left">{{ t.forms.columnName }}</th>
                            <th class="py-3">{{ t.forms.columnEmployeeId }}</th>
                            <th class="py-3">{{ t.common.businessUnit }}</th>
                            <th class="py-3">{{ t.forms.columnTemplate }}</th>
                            <th class="py-3">{{ t.common.status }}</th>
                            <th class="py-3">{{ t.forms.columnSignedAt }}</th>
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
                                <div class="text-xs text-slate-400">{{ row.designation || '' }}</div>
                            </td>
                            <td class="py-4">{{ row.employee_id || '-' }}</td>
                            <td class="py-4">{{ row.business_unit || '-' }}</td>
                            <td class="py-4">{{ row.template_title?.[locale] ?? '-' }}</td>
                            <td class="py-4">
                                <FormStatusBadge :status="row.status" />
                            </td>
                            <td class="py-4 whitespace-nowrap">{{ formatDateTime(row.signed_at) }}</td>
                        </tr>

                        <tr v-if="!rows.data.length">
                            <td
                                colspan="6"
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
    </ManagerLayout>
</template>
