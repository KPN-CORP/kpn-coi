<script setup lang="ts">
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import Card from '@/Components/UI/Card.vue'
import DocumentActions from '@/Components/UI/DocumentActions.vue'
import Pagination from '@/Components/UI/Pagination.vue'
import SortableHeader from '@/Components/UI/SortableHeader.vue'
import StatusBadge from '@/Components/UI/StatusBadge.vue'
import DeclarationViewModal from '@/Components/Declaration/DeclarationViewModal.vue'
import PersonCell from '@/Components/Report/PersonCell.vue'
import TypeBadge from '@/Components/Report/TypeBadge.vue'
import { useLocale } from '@/Composables/useLocale'
import { formatDateTime } from '@/Utils/date'

/**
 * The Admin Report's Conflict of Interest table: one row per declaration
 * (or per person without one), a column per question, and the review pop-up.
 * `legacy` is the 2025 import: one merged conflict mark and an attachment
 * instead of a review.
 */
interface Declaration {
    id: number
    row_id: string
    period: number
    type: 'employee' | 'non_employee'

    name: string
    employee_id: string
    contribution_level?: string | null

    status: string
    declaration_status: string
    submitted_at: string | null

    has_conflict: boolean
    has_attachment: boolean

    declaration?: any | null
    group_company?: string | null
}

defineProps<{
    declarations: {
        data: Declaration[]
        links: any[]
        per_page: number
        total: number
        from: number | null
        to: number | null
    }
    // The page's sort; changing it is the page's job (server-side).
    sort: string
    direction: string
    legacy: boolean
}>()

const emit = defineEmits<{
    sort: [column: string]
    'per-page': [value: number]
}>()

const { t, locale } = useLocale()

// Questions come from config/coi.php, shared to every page.
const coiQuestions = computed(() => (usePage().props as any).coiQuestions as any[])

const sortableColumns = computed(() => [
    { label: t.value.common.fullName, key: 'name', class: 'sticky left-0 z-20 bg-slate-50 shadow-[1px_0_0_0_#e2e8f0]' },
    { label: t.value.common.period, key: 'period', class: '' },
    { label: t.value.report.columnType, key: 'type', class: '' },
    { label: t.value.report.columnFormStatus, key: 'status', class: '' },
    { label: t.value.report.columnDeclarationStatus, key: 'has_conflict', class: 'min-w-64' },
    { label: t.value.report.columnSubmittedAt, key: 'submitted_at', class: '' },
])

function openAttachment(declaration: Declaration) {
    if (!declaration.declaration?.id) {
        return
    }

    window.open(
        route('admin.report.declaration.attachment', declaration.declaration.id),
        '_blank',
    )
}

const showReviewModal = ref(false)

const selectedDeclaration = ref<any | null>(null)

function openReview(
    declaration: Declaration,
) {
    if (
        !declaration.declaration?.responses
    ) {
        return
    }

    selectedDeclaration.value = {
        ...declaration.declaration,

        questions:
            declaration.declaration.responses.map(
                (response: any) => ({
                    key: response.question_key,

                    answer:
                        response.response_value?.answer,

                    details:
                        response.response_value?.details ?? [],

                    title:
                        getQuestionTitle(
                            response.question_key,
                        ),
                }),
            ),
    }

    showReviewModal.value = true
}

function getQuestionTitle(key: string) {
    const question = coiQuestions.value.find(
        (q: any) => q.key === key,
    )

    return question?.title?.[locale.value] ?? key
}

function downloadPdf(
    declarationId: number,
    locale: 'id' | 'en',
) {
    window.open(
        route('admin.report.declaration.pdf', {
            declaration: declarationId,
            locale,
        }),
        '_blank',
    )
}

// Per-question mark for the report table (mirrors the Excel export):
// true  -> answered "Yes" for that question
// false -> answered "No"
// null  -> no submission for this row
function questionAnswered(
    declaration: Declaration,
    key: string,
): boolean | null {
    const responses = declaration.declaration?.responses

    if (!responses) {
        return null
    }

    const response = responses.find(
        (r: any) => r.question_key === key,
    )

    return response?.response_value?.answer === true
}
</script>

<template>
        <Card>
        <div class="table-container">
            <table
                class="table-custom"
                :style="`table-layout: fixed; width: ${1340 + (legacy ? 120 : 56 * coiQuestions.length)}px`"
            >
                <colgroup>
                    <col style="width: 260px">
                    <col style="width: 110px">
                    <col style="width: 150px">
                    <col style="width: 180px">
                    <col style="width: 200px">
                    <col style="width: 190px">
                    <template v-if="!legacy">
                        <col
                            v-for="question in coiQuestions"
                            :key="question.key"
                            style="width: 56px"
                        >
                    </template>
                    <col v-else style="width: 120px">
                    <col style="width: 250px">
                </colgroup>
                <thead>
                    <tr
                        class="border-b border-border text-left text-xs uppercase tracking-wide text-slate-500"
                    >
                        <SortableHeader
                            v-for="col in sortableColumns"
                            :key="col.key"
                            :class="['py-3', col.class]"
                            :label="col.label"
                            :column="col.key"
                            :sort="sort"
                            :direction="direction"
                            @sort="column => emit('sort', column)"
                        />
                        <template v-if="!legacy">
                            <th
                                v-for="(question, i) in coiQuestions"
                                :key="question.key"
                                class="group/question relative w-12 py-3 text-center"
                            >
                                <span
                                    class="cursor-help underline decoration-dotted decoration-slate-300 underline-offset-4"
                                >
                                    Q{{ i + 1 }}
                                </span>
                                <div
                                    class="pointer-events-none invisible absolute left-1/2 top-full z-30 mt-1 w-64 -translate-x-1/2 rounded-lg bg-slate-800 px-3 py-2 text-left text-xs font-normal normal-case tracking-normal text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover/question:visible group-hover/question:opacity-100"
                                >
                                    {{ question.title[locale] }}
                                </div>
                            </th>
                        </template>
                        <th
                            v-else
                            class="py-3 text-center"
                            :title="t.report.coiTooltip"
                        >
                            COI
                        </th>
                        <th
                            class="py-3 text-center sticky right-0 z-10 bg-slate-50 shadow-[-1px_0_0_0_#e2e8f0]"
                        >
                            {{ t.common.action }}
                        </th>
                    </tr>
                </thead>

                <tbody v-if="declarations.data.length">
                    <tr
                        v-for="declaration in declarations.data"
                        :key="declaration.row_id"
                        class="group border-b border-slate-100"
                    >
                        <PersonCell
                            :name="declaration.name"
                            :employee-id="declaration.employee_id"
                            :business-unit="declaration.group_company"
                            :contribution-level="declaration.contribution_level"
                        />

                        <td class="py-4 whitespace-nowrap text-slate-600">
                            {{ declaration.period }}
                        </td>

                        <td class="py-4">
                            <TypeBadge :type="declaration.type" />
                        </td>

                        <td class="py-4">
                            <StatusBadge
                                :status="declaration.status"
                                :label="declaration.status === 'pending'
                                    ? t.common.notSubmitted
                                    : null"
                            />
                        </td>

                        <td class="py-4">
                            <span
                                v-if="declaration.status === 'pending'"
                                class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-semibold text-slate-600"
                            >
                                {{ t.common.na }}
                            </span>

                            <span
                                v-else-if="declaration.has_conflict"
                                class="rounded-md border border-red-200 bg-red-50 px-2 py-1 text-xs font-semibold text-red-700"
                            >
                                {{ t.common.hasConflict }}
                            </span>

                            <span
                                v-else
                                class="rounded-md border border-green-200 bg-green-50 px-2 py-1 text-xs font-semibold text-green-700"
                            >
                                {{ t.common.clear }}
                            </span>
                        </td>

                        <td class="py-4 whitespace-nowrap text-slate-600">
                            {{ formatDateTime(declaration.submitted_at) }}
                        </td>

                        <template v-if="!legacy">
                            <td
                                v-for="question in coiQuestions"
                                :key="question.key"
                                class="py-4 text-center"
                            >
                                <span
                                    v-if="questionAnswered(declaration, question.key)"
                                    class="font-bold text-green-600"
                                >
                                    ✓
                                </span>

                                <span
                                    v-else
                                    class="text-slate-300"
                                >
                                    -
                                </span>
                            </td>
                        </template>

                        <!-- 2025: single merged conflict mark -->
                        <td
                            v-else
                            class="py-4 text-center"
                        >
                            <span
                                v-if="declaration.has_conflict"
                                class="font-bold text-red-600"
                                :title="t.common.hasConflict"
                            >
                                ✓
                            </span>

                            <span
                                v-else
                                class="text-slate-300"
                            >
                                -
                            </span>
                        </td>

                        <td
                            class="py-4 text-center sticky right-0 z-10 bg-white group-hover:bg-[#fafafa] whitespace-nowrap shadow-[-1px_0_0_0_#e2e8f0]"
                        >
                            <!-- 2025: open the uploaded document directly -->
                            <template v-if="legacy">
                                <button
                                    v-if="declaration.has_attachment"
                                    type="button"
                                    class="btn btn-outline-secondary btn-sm"
                                    @click="openAttachment(declaration)"
                                >
                                    <i class="fa-solid fa-file-arrow-down" />
                                    {{ t.common.view }}
                                </button>

                                <span
                                    v-else
                                    class="text-xs text-slate-400"
                                >
                                    {{ t.report.noAttachment }}
                                </span>
                            </template>

                            <template v-else>
                                <div
                                    v-if="declaration.status !== 'pending' && declaration.declaration"
                                    class="flex items-center justify-center gap-2"
                                >
                                    <DocumentActions
                                        @view="openReview(declaration)"
                                        @download="language => downloadPdf(declaration.declaration!.id, language)"
                                    />
                                </div>

                                <span
                                    v-else
                                    class="text-xs text-slate-400"
                                >
                                    {{ t.common.noSubmission }}
                                </span>
                            </template>
                        </td>
                    </tr>
                </tbody>

                <tbody v-else>
                    <tr>
                        <td
                            :colspan="7 + (legacy ? 1 : coiQuestions.length)"
                            class="py-10 text-center"
                        >
                            <div class="flex flex-col items-center">
                                <i
                                    class="fa-solid fa-file-circle-xmark text-4xl text-slate-300"
                                />

                                <div
                                    class="mt-3 font-semibold text-slate-600"
                                >
                                    {{ t.report.noDeclarationsFound }}
                                </div>

                                <div
                                    class="text-sm text-slate-500"
                                >
                                    {{ t.report.noEmployeeDeclarations }}
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

    <DeclarationViewModal
        v-if="selectedDeclaration"
        :show="showReviewModal"
        :declaration="selectedDeclaration"
        @close="showReviewModal = false"
    />
</template>
