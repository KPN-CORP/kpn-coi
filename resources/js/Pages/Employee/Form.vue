<script setup lang="ts">
import SortableHeader from '@/Components/UI/SortableHeader.vue'
import DocumentActions from '@/Components/UI/DocumentActions.vue'
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
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import Swal from '@/Utils/alert'
import { computed, ref } from 'vue'
import type { Flash } from '@/Config/inertia'

interface PendingItem {
    id: number
    // The year it is owed for: always this year.
    period: number
    publish_at: string | null
    title: Localized
    draft_saved_at: string | null
    created_at: string | null
    // Pending again: an earlier version was signed, then replaced.
    resign: boolean
}

interface SignedForm extends Omit<RenderedForm, 'title'> {
    id: number
    // The year the signature counts for.
    period: number
    // Null for an upload, which has no template.
    title: Localized | null
    signed_name: string
    signed_at: string | null
    created_at: string | null
    locale: 'en' | 'id'
    // Kept, but its template was replaced by a newer version: not valid.
    superseded: boolean
    // A past year's signed document uploaded by the employee: a file,
    // nothing to render.
    upload: boolean
}

// One table row: a form still to sign (template), a past year still to
// upload, or a signed one.
interface Row {
    key: string
    period: number
    status: FormStatus
    date: string | null
    createdAt: string | null
    pending?: PendingItem
    signed?: SignedForm
    // A past year with nothing on record yet: upload its signed document.
    uploadYear?: number
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
    // What "Add / Update" opens: the template that applies now. Signing it
    // again adds a new declaration, which becomes the valid one.
    updateTarget: { id: number, period: number } | null
    // Past years (from 2025) still open for an upload of the signed
    // document; one already uploaded stays open to replace it.
    uploadYears: number[]
}>()

const { t, locale } = useLocale()

const page = usePage<{ flash: Flash }>()

const rows = computed<Row[]>(() => [
    ...props.pending.map(item => ({
        key: `pending-${item.id}`,
        period: item.period,
        status: (item.draft_saved_at ? 'draft' : 'pending') as FormStatus,
        date: null,
        createdAt: item.created_at,
        pending: item,
    })),
    ...props.signed.map(item => ({
        key: `signed-${item.id}`,
        period: item.period,
        // An older declaration (replaced by a newer one) is still a
        // signature: shown as Signed, viewable, but without a PDF.
        status: 'signed' as FormStatus,
        date: item.signed_at,
        createdAt: item.created_at,
        signed: item,
    })),
    // Past years are not signed in the app: like the COI 2025 row, each one
    // gets a row of its own, uploaded from the table.
    ...props.uploadYears
        .filter(year => !props.signed.some(item => item.upload && item.period === year))
        .map(year => ({
            key: `upload-${year}`,
            period: year,
            status: 'pending' as FormStatus,
            date: null,
            createdAt: null,
            uploadYear: year,
        })),
])

// --- Filter and sort (a person has a handful of rows: done in the browser) ---

const period = ref('')

// The filter is by year, like the reports.
const periods = computed(() => [...new Set(rows.value.map(row => row.period))].sort((a, b) => b - a))

const periodOptions = computed(() => [
    { value: '', label: t.value.history.allPeriods },
    ...periods.value.map(item => ({ value: String(item), label: String(item) })),
])

type SortKey = 'period' | 'status' | 'date' | 'createdAt'

const sortKey = ref<SortKey>('period')
const sortDir = ref<'asc' | 'desc'>('desc')

const columns = computed<{ key: SortKey, label: string }[]>(() => [
    { key: 'period', label: t.value.forms.periodYear },
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

            // Unsigned rows go above signed ones within the same year.
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

// --- Actions ---------------------------------------------------------------

const viewing = ref<SignedForm | null>(null)

function downloadPdf(id: number, language: 'en' | 'id') {
    window.open(
        route('employee.forms.pdf', { declaration: id, locale: language }),
        '_blank',
    )
}

// --- Past year: upload the signed document ---------------------------------

const canReplace = (signed: SignedForm) => signed.upload && props.uploadYears.includes(signed.period)

const uploadingYear = ref<number | null>(null)
const documentInput = ref<HTMLInputElement | null>(null)

function chooseDocument(year: number) {
    uploadingYear.value = year
    documentInput.value?.click()
}

function onDocumentChosen(event: Event) {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    const year = uploadingYear.value

    if (!file || !year) {
        return
    }

    router.post(
        route('employee.forms.upload', { formType: props.formType.code }),
        { year, document: file, locale: locale.value },
        {
            forceFormData: true,
            preserveScroll: true,

            // Success shows as the page's flash message.
            onError: (errors) => {
                Swal.fire({
                    icon: 'error',
                    title: t.value.history.uploadFailedTitle,
                    text: errors.document ?? errors.year ?? t.value.history.uploadFailedText,
                })
            },

            onFinish: () => {
                uploadingYear.value = null
                input.value = ''
            },
        },
    )
}
</script>

<template>
    <Head :title="formType.name[locale]" />

    <EmployeeLayout>
        <PageHeader
            :title="formType.name[locale]"
            :description="formType.description[locale] || t.forms.description.replace(':form', formType.name[locale])"
        >
            <template
                v-if="updateTarget"
                #actions
            >
                <Link
                    :href="route('employee.forms.read', { formType: formType.code, template: updateTarget.id })"
                    class="btn-primary-custom"
                >
                    <i class="fa-solid fa-pen-to-square" />
                    {{ t.forms.addUpdate }} {{ formType.name[locale] }}
                </Link>
            </template>
        </PageHeader>

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

            <span>{{ t.forms.notSignedWarning.replace(':form', formType.name[locale]) }}</span>
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
                            <SortableHeader
                                v-for="col in columns"
                                :key="col.key"
                                class="py-3"
                                :label="col.label"
                                :column="col.key"
                                :sort="sortKey"
                                :direction="sortDir"
                                @sort="column => toggleSort(column as SortKey)"
                            />
                            <th class="py-3">{{ t.common.action }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="row in visibleRows"
                            :key="row.key"
                            class="border-b border-slate-100 text-center"
                        >
                            <td class="py-4 font-semibold">
                                {{ row.period }}
                            </td>

                            <td class="py-4">
                                <FormStatusBadge :status="row.status" />
                                <div
                                    v-if="row.signed?.upload"
                                    class="mt-1 flex items-center justify-center gap-1 text-xs font-medium text-slate-500"
                                >
                                    <i class="fa-solid fa-paperclip" />
                                    {{ t.forms.uploadedDocument }}
                                </div>
                                <div
                                    v-if="row.pending?.resign"
                                    class="mt-1 flex items-center justify-center gap-1 text-xs font-medium text-amber-700"
                                >
                                    <i class="fa-solid fa-rotate" />
                                    {{ t.forms.resignRequired }}
                                </div>
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
                                        <i class="fa-solid fa-signature" />
                                        {{ row.pending.draft_saved_at ? t.common.continue : t.forms.openToRead }}
                                    </Link>

                                    <!-- A replaced version has no PDF for the signer
                                         (admins keep it in the Form Report). -->
                                    <!-- Past year with nothing yet: upload, as the COI 2025 row -->
                                    <button
                                        v-if="row.uploadYear"
                                        type="button"
                                        class="btn btn-primary-custom btn-sm"
                                        :title="t.forms.uploadPastYearHint"
                                        @click="chooseDocument(row.uploadYear)"
                                    >
                                        <i class="fa-solid fa-upload" />
                                        {{ t.forms.upload }}
                                    </button>

                                    <button
                                        v-if="row.signed && canReplace(row.signed)"
                                        type="button"
                                        class="btn btn-outline-secondary btn-sm"
                                        @click="chooseDocument(row.signed.period)"
                                    >
                                        <i class="fa-solid fa-arrows-rotate" />
                                        {{ t.common.replace }}
                                    </button>

                                    <DocumentActions
                                        v-if="row.signed"
                                        :pdf="!row.signed.superseded"
                                        :file="row.signed.upload"
                                        @view="viewing = row.signed"
                                        @download="language => downloadPdf(row.signed!.id, language)"
                                    />
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!visibleRows.length">
                            <td
                                colspan="5"
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
            :title="formType.name[locale]"
            @close="viewing = null"
        >
            <template v-if="viewing && viewing.page">

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

        <!-- Shared hidden picker for a past year's signed document -->
        <input
            ref="documentInput"
            type="file"
            accept=".pdf,.jpg,.jpeg,.png"
            class="hidden"
            @change="onDocumentChosen"
        >
    </EmployeeLayout>
</template>
