<script setup lang="ts">
import PageHeader from '@/Components/UI/PageHeader.vue'
import Card from '@/Components/UI/Card.vue'
import Modal from '@/Components/UI/Modal.vue'
import FlashMessage from '@/Components/UI/FlashMessage.vue'
import EmployeeLayout from '@/Layouts/EmployeeLayout.vue'
import FormDocument from '@/Components/Forms/FormDocument.vue'
import FormStatusBadge from '@/Components/Forms/FormStatusBadge.vue'
import SearchableSelect from '@/Components/UI/SearchableSelect.vue'
import type { FormStatus, Localized, RenderedForm } from '@/Components/Forms/types'
import { formatDateTime } from '@/Utils/date'
import { useLocale } from '@/Composables/useLocale'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import type { Flash } from '@/Config/inertia'

interface PendingItem {
    id: number
    period: number
    publish_at: string | null
    title: Localized
    draft_saved_at: string | null
    created_at: string | null
}

interface SignedForm extends RenderedForm {
    id: number
    period: number
    signed_name: string
    signed_at: string | null
    created_at: string | null
    locale: 'en' | 'id'
}

// One table row: a form still to sign (template) or a signed one.
interface Row {
    key: string
    period: number
    title: Localized
    status: FormStatus
    date: string | null
    createdAt: string | null
    pending?: PendingItem
    signed?: SignedForm
}

const props = defineProps<{
    formType: {
        id: number
        code: string
        name: Localized
        description: { en: string | null, id: string | null }
        icon: string
    }
    pending: PendingItem[]
    signed: SignedForm[]
}>()

const { t, locale } = useLocale()

const page = usePage<{ flash: Flash }>()

const rows = computed<Row[]>(() => [
    ...props.pending.map(item => ({
        key: `pending-${item.id}`,
        period: item.period,
        title: item.title,
        status: (item.draft_saved_at ? 'draft' : 'pending') as FormStatus,
        date: null,
        createdAt: item.created_at,
        pending: item,
    })),
    ...props.signed.map(item => ({
        key: `signed-${item.id}`,
        period: item.period,
        title: item.title,
        status: 'signed' as FormStatus,
        date: item.signed_at,
        createdAt: item.created_at,
        signed: item,
    })),
])

// --- Filter and sort (a person has a handful of rows: done in the browser) ---

const period = ref('')

const periods = computed(() => [...new Set(rows.value.map(row => row.period))].sort((a, b) => b - a))

const periodOptions = computed(() => [
    { value: '', label: t.value.history.allPeriods },
    ...periods.value.map(item => ({ value: String(item), label: String(item) })),
])

type SortKey = 'period' | 'status' | 'date' | 'createdAt'

const sortKey = ref<SortKey>('period')
const sortDir = ref<'asc' | 'desc'>('desc')

const columns = computed<{ key: SortKey, label: string }[]>(() => [
    { key: 'period', label: t.value.history.columnPeriod },
    { key: 'status', label: t.value.history.columnFormStatus },
    { key: 'date', label: t.value.forms.columnSignedAt },
    { key: 'createdAt', label: t.value.history.columnCreatedDate },
])

// Still to sign first when sorting by status.
const STATUS_ORDER: Record<string, number> = { pending: 0, draft: 1, signed: 2 }

const visibleRows = computed(() => {
    const value = (row: Row): number | string => {
        switch (sortKey.value) {
            case 'status':
                return STATUS_ORDER[row.status] ?? 9
            case 'date':
                return row.date ?? ''
            case 'createdAt':
                return row.createdAt ?? ''
            default:
                return row.period
        }
    }

    const direction = sortDir.value === 'asc' ? 1 : -1

    return rows.value
        .filter(row => !period.value || row.period === Number(period.value))
        .sort((a, b) => {
            const x = value(a)
            const y = value(b)

            // Unsigned rows go above signed ones within the same period.
            return x < y ? -direction : x > y ? direction : (STATUS_ORDER[a.status] ?? 9) - (STATUS_ORDER[b.status] ?? 9)
        })
})

function toggleSort(key: SortKey) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortKey.value = key
        sortDir.value = 'asc'
    }
}

function sortIcon(key: SortKey) {
    if (sortKey.value !== key) {
        return 'fa-solid fa-sort text-slate-300'
    }

    return sortDir.value === 'asc'
        ? 'fa-solid fa-sort-up text-slate-600'
        : 'fa-solid fa-sort-down text-slate-600'
}

// --- Actions ---------------------------------------------------------------

const viewing = ref<SignedForm | null>(null)

function downloadPdf(id: number, language: 'en' | 'id') {
    window.open(
        route('employee.forms.pdf', { declaration: id, locale: language }),
        '_blank',
    )
}
</script>

<template>
    <Head :title="formType.name[locale]" />

    <EmployeeLayout>
        <PageHeader
            :title="formType.name[locale]"
            :description="formType.description[locale] ?? t.forms.description"
        />

        <FlashMessage
            type="success"
            :message="page.props.flash?.success"
        />

        <FlashMessage
            type="error"
            :message="page.props.flash?.error"
        />

        <div
            v-if="pending.length"
            class="mb-6 flex items-center gap-2 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700"
        >
            <i class="fa-solid fa-triangle-exclamation" />

            <span>{{ t.forms.notSignedWarning }}</span>
        </div>

        <div class="mb-6 flex items-center gap-3">
            <SearchableSelect
                v-model="period"
                :options="periodOptions"
                class="w-48 max-w-full"
                :aria-label="t.history.columnPeriod"
            />
        </div>

        <Card>
            <div class="table-container">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th class="py-3">{{ t.forms.columnForm }}</th>
                            <th
                                v-for="col in columns"
                                :key="col.key"
                                class="py-3"
                                :aria-sort="sortKey === col.key ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none'"
                            >
                                <button
                                    type="button"
                                    class="inline-flex select-none items-center gap-1.5 whitespace-nowrap uppercase tracking-[inherit] [font:inherit] transition-colors hover:text-slate-700"
                                    @click="toggleSort(col.key)"
                                >
                                    {{ col.label }}
                                    <i :class="sortIcon(col.key)" />
                                </button>
                            </th>
                            <th class="py-3">{{ t.common.action }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="row in visibleRows"
                            :key="row.key"
                            class="border-b border-slate-100 text-center"
                        >
                            <td class="py-4 text-left font-medium text-slate-800">
                                {{ row.title[locale] ?? '-' }}
                            </td>

                            <td class="py-4 font-semibold">
                                {{ row.period }}
                            </td>

                            <td class="py-4">
                                <FormStatusBadge :status="row.status" />
                            </td>

                            <td class="py-4">
                                {{ row.date ? formatDateTime(row.date) : '-' }}
                            </td>

                            <td class="py-4">
                                {{ row.createdAt ? formatDateTime(row.createdAt) : '-' }}
                            </td>

                            <td class="py-4">
                                <div class="flex justify-center gap-2">
                                    <Link
                                        v-if="row.pending"
                                        :href="route('employee.forms.read', { formType: formType.code, template: row.pending.id })"
                                        class="btn btn-sm"
                                        :class="row.pending.draft_saved_at
                                            ? 'bg-yellow-400 text-white hover:bg-yellow-500 hover:font-bold'
                                            : 'btn-primary-custom'"
                                    >
                                        <i class="fa-solid fa-book-open" />
                                        {{ row.pending.draft_saved_at ? t.common.continue : t.forms.openToRead }}
                                    </Link>

                                    <template v-if="row.signed">
                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary btn-sm"
                                            @click="viewing = row.signed"
                                        >
                                            <i class="fa-solid fa-eye" />
                                            {{ t.common.view }}
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-outline-primary-custom btn-sm"
                                            :title="`PDF ${t.forms.indonesian}`"
                                            @click="downloadPdf(row.signed.id, 'id')"
                                        >
                                            <i class="fa-solid fa-file-pdf" />
                                            ID
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-outline-primary-custom btn-sm"
                                            :title="`PDF ${t.forms.english}`"
                                            @click="downloadPdf(row.signed.id, 'en')"
                                        >
                                            <i class="fa-solid fa-file-pdf" />
                                            EN
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!visibleRows.length">
                            <td
                                colspan="6"
                                class="py-8 text-center text-slate-500"
                            >
                                {{ t.history.noRecords }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Modal
            :show="viewing !== null"
            max-width="max-w-4xl"
            @close="viewing = null"
        >
            <template v-if="viewing">
                <div class="flex items-center justify-between border-b border-border px-6 py-4">
                    <h3 class="text-lg font-bold">
                        {{ viewing.title[locale] }}
                    </h3>

                    <button
                        type="button"
                        class="text-slate-400 hover:text-primary"
                        :aria-label="t.common.close"
                        @click="viewing = null"
                    >
                        <i class="fa-solid fa-xmark text-lg" />
                    </button>
                </div>

                <div class="overflow-y-auto">
                    <FormDocument
                        :page="viewing.page"
                        :content="viewing.content[locale]"
                        :header="viewing.header[locale]"
                        :footer="viewing.footer[locale]"
                    />
                </div>
            </template>
        </Modal>
    </EmployeeLayout>
</template>
