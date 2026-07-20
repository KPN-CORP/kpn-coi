<script setup lang="ts">

import PageHeader from '@/Components/UI/PageHeader.vue'
import Card from '@/Components/UI/Card.vue'
import StatusBadge from '@/Components/UI/StatusBadge.vue'
import DeclarationViewModal from '@/Components/Declaration/DeclarationViewModal.vue'
import FlashMessage from '@/Components/UI/FlashMessage.vue'
import EmployeeLayout from '@/Layouts/EmployeeLayout.vue'
import Pagination from '@/Components/UI/Pagination.vue'

import { Link, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import type { Flash } from '@/Config/inertia'
import Swal from 'sweetalert2'

const showViewModal = ref(false)

const selectedDeclaration = ref<any | null>(null)

const page = usePage<{
    flash: Flash
}>()

const coiQuestions = computed(
    () => page.props.coiQuestions
)

const currentYear = new Date().getFullYear()

const hasCurrentYearDeclaration = computed(() =>
    props.declarations.data.some(
        declaration =>
            Number(declaration.period) === currentYear,
    ),
)

interface Declaration {
    id: number
    period: number
    status: string
    submitted_at: string | null
    created_at: string | null
    reviewed_at: string | null
    responses_count: number
    has_conflict: boolean
    has_attachment: boolean
}

const props = defineProps<{
    declarations: {
        data: Declaration[]
        links: any[]
        meta: {
            links: any[]
            per_page: number
            total: number
            from: number | null
            to: number | null
        }
    }
    periods: number[]
    filters: {
        period?: string
        sort?: string
        direction?: string
    }
}>()

const sortKey = ref(props.filters.sort ?? '')
const sortDir = ref(props.filters.direction ?? 'asc')

const sortableColumns = [
    { label: 'Period', key: 'period' },
    { label: 'Form Status', key: 'status' },
    { label: 'Submit Date', key: 'submitted_at' },
    { label: 'Created Date', key: 'created_at' },
]

// Keep period / per_page / sort travelling together on every navigation.
function reload(overrides: Record<string, unknown> = {}) {
    router.get(
        route('employee.history'),
        {
            period: props.filters.period,
            per_page: props.declarations.meta.per_page,
            sort: sortKey.value || undefined,
            direction: sortDir.value,
            ...overrides,
        },
        {
            preserveState: true,
            replace: true,
        },
    )
}

function changePerPage(value: number) {
    reload({ per_page: value })
}

function changePeriod(value: string) {
    reload({ period: value })
}

function toggleSort(column: string) {
    if (sortKey.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortKey.value = column
        sortDir.value = 'asc'
    }

    reload()
}

function sortIcon(column: string) {
    if (sortKey.value !== column) {
        return 'fa-solid fa-sort text-slate-300'
    }

    return sortDir.value === 'asc'
        ? 'fa-solid fa-sort-up text-slate-600'
        : 'fa-solid fa-sort-down text-slate-600'
}

function viewDeclaration(declaration: any) {
    
    selectedDeclaration.value = {
        ...declaration,

        questions: declaration.questions.map(
            (question: any) => ({
                ...question,

                title:
                    getQuestionTitle(
                        question.key,
                    ),
            }),
        ),
    }

    showViewModal.value = true
}

function getQuestionTitle(key: string) {
    const question = coiQuestions.value.find(
        q => q.key === key,
    )

    return question?.title?.en ?? key
}

function continueDraft(declaration: any) {
    router.visit(
        route('employee.language'),
    )
}

function getActions(status: string) {
    switch (status) {
        case 'draft':
        case 'revision_required':
            return ['continue']

        case 'submitted':
        case 'approved':
        case 'rejected':
            return ['view']

        default:
            return []
    }
}

function formatDate(date: string | null) {
    if (!date) return '-'

    const d = new Date(date)

    const day = String(d.getDate()).padStart(2, '0')
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const year = d.getFullYear()

    const hours = String(d.getHours()).padStart(2, '0')
    const minutes = String(d.getMinutes()).padStart(2, '0')
    const seconds = String(d.getSeconds()).padStart(2, '0')

    return `${day}-${month}-${year}, ${hours}:${minutes}:${seconds}`
}

const downloadPdf = (id: number, declaration: any, locale: string) => {
    window.open(
        route('employee.declarations.pdf', {
            declaration: id,
            locale: locale ?? 'id',
        }),
        '_blank'
    )
}

// --- 2025 supporting-document upload/download ---

// 2025 is the imported historical period: every row needs a supporting
// document, conflict or not.
function isLegacy(declaration: Declaration) {
    return Number(declaration.period) === 2025
}

// Until the document is uploaded the row has no meaningful dates (created_at
// is just the import moment), so the table shows "-" for both. Uploading
// stamps them with the real submission time.
function isPendingUpload(declaration: Declaration) {
    return isLegacy(declaration) && !declaration.has_attachment
}

const uploadingId = ref<number | null>(null)
const attachmentInput = ref<HTMLInputElement | null>(null)

function chooseAttachment(declaration: Declaration) {
    uploadingId.value = declaration.id
    attachmentInput.value?.click()
}

function onAttachmentChosen(event: Event) {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    const id = uploadingId.value

    if (!file || !id) {
        return
    }

    const form = new FormData()
    form.append('attachment', file)

    router.post(
        route('employee.declarations.attachment.store', id),
        form,
        {
            forceFormData: true,
            preserveScroll: true,

            onSuccess: () => {
                Swal.fire({
                    icon: 'success',
                    title: 'Uploaded',
                    text: 'Attachment uploaded successfully.',
                    confirmButtonColor: '#ab2f2b',
                })
            },

            onError: (errors) => {
                Swal.fire({
                    icon: 'error',
                    title: 'Upload Failed',
                    text: errors.attachment
                        ?? 'Could not upload the file. Please try again.',
                    confirmButtonColor: '#ab2f2b',
                })
            },

            onFinish: () => {
                uploadingId.value = null
                input.value = ''
            },
        },
    )
}

function downloadAttachment(declaration: Declaration) {
    window.open(
        route('employee.declarations.attachment.show', declaration.id),
        '_blank',
    )
}
</script>

<template>
    <EmployeeLayout>
        <PageHeader
            title="History"
            description="Your conflict of interest declaration records."
        >
            <FlashMessage
                type="success"
                :message="page.props.flash?.success"
            />

            <FlashMessage
                type="error"
                :message="page.props.flash?.error"
            />
            <template #actions>
                <Link
                    :href="route('employee.language')"
                    class="btn-primary-custom"
                >
                    <i class="fa-solid fa-plus" />
                    Create Declaration
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
            v-if="!hasCurrentYearDeclaration"
            class="mb-6 flex items-center gap-2 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700"
        >
            <i class="fa-solid fa-triangle-exclamation" />

            <span>
                You have not submitted the conflict of interest declaration,
                please submit immediately.
            </span>
        </div>
        <div class="mb-6 flex items-center gap-3">
            <select
                class="form-select"
                :value="filters.period"
                @change="changePeriod(($event.target as HTMLSelectElement).value)"
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

        <Card>
            <div class="table-container">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th
                                v-for="col in sortableColumns"
                                :key="col.key"
                                class="py-3 cursor-pointer select-none whitespace-nowrap transition-colors hover:text-slate-700"
                                @click="toggleSort(col.key)"
                            >
                                <span class="inline-flex items-center gap-1.5">
                                    {{ col.label }}
                                    <i :class="sortIcon(col.key)" />
                                </span>
                            </th>
                            <th class="py-3">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="declaration in props.declarations.data"
                            :key="declaration.period"
                            class="border-b border-slate-100 text-center"
                            :class="isPendingUpload(declaration) ? 'bg-red-50' : ''"
                        >
                            <td class="py-4 font-semibold">
                                {{ declaration.period }}
                            </td>

                            <td class="py-4">
                                <StatusBadge
                                    :status="declaration.status"
                                />
                            </td>
                            <td class="py-4">
                                {{
                                    isPendingUpload(declaration)
                                        ? '-'
                                        : formatDate(declaration.submitted_at)
                                }}
                            </td>

                            <td class="py-4">
                                {{
                                    isPendingUpload(declaration)
                                        ? '-'
                                        : formatDate(declaration.created_at)
                                }}
                            </td>

                            <td class="py-4">
                                <div class="flex justify-center gap-2">
                                    <!-- 2025 historical rows: attachment only -->
                                    <template v-if="isLegacy(declaration)">
                                        <button
                                            v-if="!declaration.has_attachment"
                                            type="button"
                                            class="btn btn-primary-custom btn-sm"
                                            @click="chooseAttachment(declaration)"
                                        >
                                            <i class="fa-solid fa-upload" />
                                            Upload
                                        </button>

                                        <template v-else>
                                            <button
                                                type="button"
                                                class="btn btn-outline-primary-custom btn-sm"
                                                @click="downloadAttachment(declaration)"
                                            >
                                                <i class="fa-solid fa-file-arrow-down" />
                                                Download
                                            </button>

                                            <button
                                                type="button"
                                                class="btn btn-outline-secondary btn-sm"
                                                @click="chooseAttachment(declaration)"
                                            >
                                                <i class="fa-solid fa-arrows-rotate" />
                                                Replace
                                            </button>
                                        </template>
                                    </template>

                                    <!-- Regular declaration rows -->
                                    <template v-else>
                                        <button
                                            v-if="getActions(declaration.status).includes('continue')"
                                            class="btn bg-yellow-400 hover:bg-yellow-500 hover:font-bold text-white btn-sm"
                                            @click="continueDraft(declaration)"
                                        >
                                            Continue
                                        </button>
                                        <button
                                            v-if="getActions(declaration.status).includes('view')"
                                            class="btn btn-outline-secondary btn-sm"
                                            @click="viewDeclaration(declaration)"
                                        >
                                            <i class="fa-solid fa-eye" />
                                            View
                                        </button>

                                        <button
                                            v-if="declaration.status === 'submitted'"
                                            type="button"
                                            class="btn btn-outline-primary-custom btn-sm"
                                            @click="downloadPdf(declaration.id, declaration, 'id')"
                                        >
                                            <i class="fa-solid fa-file-pdf" />
                                            ID
                                        </button>
                                        <button
                                            v-if="declaration.status === 'submitted'"
                                            type="button"
                                            class="btn btn-outline-primary-custom btn-sm"
                                            @click="downloadPdf(declaration.id, declaration, 'en')"
                                        >
                                            <i class="fa-solid fa-file-pdf" />
                                            EN
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!props.declarations.data.length">
                            <td
                                colspan="4"
                                class="py-8 text-center text-slate-500"
                            >
                                No declaration records found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination
                :links="props.declarations.meta.links"
                :per-page="props.declarations.meta.per_page"
                :total="props.declarations.meta.total"
                :from="props.declarations.meta.from"
                :to="props.declarations.meta.to"
                @update:per-page="changePerPage"
            />
        </Card>
        <DeclarationViewModal
            v-if="selectedDeclaration"
            :show="showViewModal"
            :declaration="selectedDeclaration"
            @close="showViewModal = false"
        />

        <!-- Shared hidden picker for 2025 attachment upload/replace -->
        <input
            ref="attachmentInput"
            type="file"
            accept=".pdf,.doc,.docx"
            class="hidden"
            @change="onAttachmentChosen"
        >
    </EmployeeLayout>
</template>