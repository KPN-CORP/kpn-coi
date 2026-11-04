<script setup lang="ts">
import { ref, computed } from 'vue'
import { route } from 'ziggy-js'
import Card from '@/Components/UI/Card.vue'
import DocumentActions from '@/Components/UI/DocumentActions.vue'
import Modal from '@/Components/UI/Modal.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import SortableHeader from '@/Components/UI/SortableHeader.vue'
import FormDocument from '@/Components/Forms/FormDocument.vue'
import FormStatusBadge from '@/Components/Forms/FormStatusBadge.vue'
import PersonCell from '@/Components/Report/PersonCell.vue'
import TypeBadge from '@/Components/Report/TypeBadge.vue'
import { useLocale } from '@/Composables/useLocale'
import { formatDateTime } from '@/Utils/date'
import type { FormRow, Localized, RenderedForm } from '@/Components/Forms/types'

/**
 * The Admin Report's table for a form type (FormReportService rows): one row
 * per person and year, with the signed document's View pop-up and PDFs (or
 * the uploaded file, for a past year signed outside the app).
 */
defineProps<{
    declarations: {
        data: FormRow[]
        links: any[]
        per_page: number
        total: number
        from: number | null
        to: number | null
    }
    // The page's sort; changing it is the page's job (server-side).
    sort: string
    direction: string
}>()

const emit = defineEmits<{
    sort: [column: string]
    'per-page': [value: number]
}>()

const { t, locale } = useLocale()

// The form type table: same look as the COI one, columns of a signature.
const formColumns = computed(() => [
    { label: t.value.common.fullName, key: 'name', class: 'sticky left-0 z-20 bg-slate-50 shadow-[1px_0_0_0_#e2e8f0]' },
    { label: t.value.forms.periodYear, key: 'period', class: '' },
    { label: t.value.report.columnType, key: 'type', class: '' },
    { label: t.value.report.columnFormStatus, key: 'status', class: '' },
    { label: t.value.forms.columnSignedAt, key: 'signed_at', class: '' },
])

// A person who re-signed after the template was replaced holds several
// signatures in one year; admins can download each of them.
const versionsRow = ref<FormRow | null>(null)

// Hidden for now (not needed yet); set to true to bring the Versions
// button back -- the pop-up and its data are still in place.
const showVersions = false

const hasOtherVersions = (row: FormRow) => showVersions && row.signed_versions.some(version => !version.current)

function downloadVersion(id: number, language: 'id' | 'en') {
    window.open(
        route('admin.forms.report.pdf', { declaration: id, locale: language }),
        '_blank',
    )
}

// View: the signed document in a pop-up, as the signer's own history shows
// it. Fetched on demand -- report rows carry no document text.
const viewingForm = ref<(RenderedForm & { form_type: Localized | null }) | null>(null)
const viewingFormLoading = ref(false)
const viewingFormError = ref(false)
const viewingFormOpen = ref(false)

async function viewForm(row: FormRow) {
    viewingFormOpen.value = true
    viewingFormLoading.value = true
    viewingFormError.value = false
    viewingForm.value = null

    try {
        const res = await fetch(
            route('admin.forms.report.document', row.declaration_id),
            {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            },
        )

        if (!res.ok) {
            throw new Error(String(res.status))
        }

        viewingForm.value = await res.json()
    } catch {
        viewingFormError.value = true
    } finally {
        viewingFormLoading.value = false
    }
}

function closeViewForm() {
    viewingFormOpen.value = false
    viewingForm.value = null
}

function downloadFormPdf(row: FormRow, language: 'id' | 'en') {
    window.open(
        route('admin.forms.report.pdf', { declaration: row.declaration_id, locale: language }),
        '_blank',
    )
}
</script>

<template>
        <Card>
        <div class="table-container">
            <table class="table-custom min-w-[1100px]">
                <thead>
                    <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-slate-500">
                        <SortableHeader
                            v-for="col in formColumns"
                            :key="col.label"
                            :class="['py-3', col.class]"
                            :label="col.label"
                            :column="col.key"
                            :sort="sort"
                            :direction="direction"
                            @sort="column => emit('sort', column)"
                        />
                        <th class="py-3 text-center sticky right-0 z-10 bg-slate-50 shadow-[-1px_0_0_0_#e2e8f0]">
                            {{ t.common.action }}
                        </th>
                    </tr>
                </thead>

                <tbody v-if="declarations.data.length">
                    <tr
                        v-for="row in (declarations.data as FormRow[])"
                        :key="row.key"
                        class="group border-b border-slate-100"
                    >
                        <PersonCell
                            :name="row.name"
                            :employee-id="row.employee_id"
                            :business-unit="row.business_unit"
                            :contribution-level="row.contribution_level"
                        />

                        <td class="py-4 whitespace-nowrap text-slate-600">
                            {{ row.period }}
                        </td>

                        <td class="py-4">
                            <TypeBadge :type="row.type" />
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

                        <td class="py-4 whitespace-nowrap text-slate-600">
                            {{ row.signed_at ? formatDateTime(row.signed_at) : '-' }}
                        </td>

                        <td class="py-4 text-center sticky right-0 z-10 bg-white group-hover:bg-[#fafafa] whitespace-nowrap shadow-[-1px_0_0_0_#e2e8f0]">
                            <div
                                v-if="row.declaration_id || hasOtherVersions(row)"
                                class="flex items-center justify-center gap-2"
                            >
                                <DocumentActions
                                    v-if="row.declaration_id"
                                    :file="row.source === 'upload'"
                                    @view="viewForm(row)"
                                    @download="language => downloadFormPdf(row, language)"
                                />

                                <!-- Signatures on replaced versions, kept for the record -->
                                <button
                                    v-if="hasOtherVersions(row)"
                                    type="button"
                                    class="btn btn-outline-secondary btn-sm"
                                    :title="t.forms.versionsHint"
                                    @click="versionsRow = row"
                                >
                                    <i class="fa-solid fa-clock-rotate-left" />
                                    {{ t.forms.versions }} ({{ row.signed_versions.length }})
                                </button>
                            </div>

                            <span
                                v-else
                                class="text-xs text-slate-400"
                            >
                                {{ t.common.noSubmission }}
                            </span>
                        </td>
                    </tr>
                </tbody>

                <tbody v-else>
                    <tr>
                        <td
                            :colspan="formColumns.length + 1"
                            class="py-10 text-center"
                        >
                            <div class="flex flex-col items-center">
                                <i class="fa-solid fa-file-circle-xmark text-4xl text-slate-300" />

                                <div class="mt-3 font-semibold text-slate-600">
                                    {{ t.report.noDeclarationsFound }}
                                </div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination
            :links="declarations.links"
            :per-page="declarations.per_page"
            :total="declarations.total"
            :from="declarations.from"
            :to="declarations.to"
            @update:per-page="value => emit('per-page', value)"
        />
    </Card>


    <!-- One signed declaration, as the signer's own history shows it -->
    <Modal
        :show="viewingFormOpen"
        max-width="max-w-4xl"
        :title="viewingForm?.form_type?.[locale] ?? t.common.view"
        @close="closeViewForm"
    >

        <div
            v-if="viewingFormLoading"
            class="py-16 text-center text-slate-400"
        >
            <i class="fa-solid fa-spinner fa-spin text-2xl" />
        </div>

        <div
            v-else-if="viewingFormError"
            class="py-16 text-center text-sm text-slate-500"
        >
            {{ t.forms.viewFailed }}
        </div>

        <div
            v-else-if="viewingForm"
            class="overflow-y-auto"
        >
            <FormDocument
                :page="viewingForm.page"
                :content="viewingForm.content[locale]"
                :header="viewingForm.header[locale]"
                :footer="viewingForm.footer[locale]"
            />
        </div>
    </Modal>

    <Modal
        :show="versionsRow !== null"
        max-width="max-w-2xl"
        :title="t.forms.versionsTitle"
        :subtitle="versionsRow ? `${versionsRow.name} · ${versionsRow.employee_id || '-'} · ${t.common.period} ${versionsRow.period}` : ''"
        @close="versionsRow = null"
    >
        <template v-if="versionsRow">

            <ul class="divide-y divide-slate-100">
                <li
                    v-for="(version, index) in versionsRow.signed_versions"
                    :key="version.id"
                    class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-semibold text-slate-400">#{{ versionsRow.signed_versions.length - index }}</span>
                            <span class="truncate font-medium text-slate-800">{{ version.template_title?.[locale] ?? '-' }}</span>
                            <span
                                class="rounded-full border px-2 py-0.5 text-xs font-semibold"
                                :class="version.current
                                    ? 'border-green-200 bg-green-50 text-green-700'
                                    : 'border-slate-200 bg-slate-50 text-slate-500'"
                            >
                                {{ version.current ? t.forms.versionCurrent : t.forms.versionReplaced }}
                            </span>
                        </div>
                        <div class="mt-0.5 text-sm text-slate-500">
                            {{ t.forms.columnSignedAt }}: {{ version.signed_at ? formatDateTime(version.signed_at) : '-' }}
                        </div>
                    </div>

                    <div class="flex shrink-0 gap-2">
                        <button
                            type="button"
                            class="btn btn-outline-primary-custom btn-sm"
                            @click="downloadVersion(version.id, 'id')"
                        >
                            <i class="fa-solid fa-file-pdf" />
                            ID
                        </button>

                        <button
                            type="button"
                            class="btn btn-outline-primary-custom btn-sm"
                            @click="downloadVersion(version.id, 'en')"
                        >
                            <i class="fa-solid fa-file-pdf" />
                            EN
                        </button>
                    </div>
                </li>
            </ul>
        </template>
    </Modal>
</template>
