<script setup lang="ts">
import PageHeader from '@/Components/UI/PageHeader.vue'
import Card from '@/Components/UI/Card.vue'
import FlashMessage from '@/Components/UI/FlashMessage.vue'
import EmployeeLayout from '@/Layouts/EmployeeLayout.vue'
import FormStatusBadge from '@/Components/Forms/FormStatusBadge.vue'
import type { Localized } from '@/Components/Forms/types'
import { formatDateTime } from '@/Utils/date'
import { useLocale } from '@/Composables/useLocale'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import type { Flash } from '@/Config/inertia'

interface CommitmentForm {
    id: number
    // Year only, like the COI row.
    period: number
    title: Localized
    form_type: {
        code: string
        name: Localized
        icon: string
    }
    // 'signed' rows stay listed for the year so their history is reachable.
    status: 'signed' | 'draft' | 'pending'
    // Latest signature of this form type, any period.
    last_submitted_at: string | null
    draft_saved_at: string | null
    // Pending again: an earlier version was signed, then replaced.
    resign: boolean
}

const props = defineProps<{
    coi: {
        period: number
        status: 'done' | 'draft' | 'pending'
        submitted_at: string | null
        draft_saved_at: string | null
        // Latest submission, any period.
        last_submitted_at: string | null
    }
    forms: CommitmentForm[]
}>()

const { t, locale } = useLocale()

const page = usePage<{ flash: Flash }>()

// COI (if not submitted) plus every form row still to sign.
const pendingCount = computed(() =>
    (props.coi.status === 'done' ? 0 : 1) + props.forms.filter(form => form.status !== 'signed').length,
)

const allDone = computed(() => pendingCount.value === 0)

const pendingMessage = computed(() => t.value.commitment.pendingWarning
    .replace(':count', String(pendingCount.value))
    .replace(':tasks', pendingCount.value === 1 ? t.value.commitment.taskOne : t.value.commitment.taskMany))

// The all-done note is only a confirmation: it fades out after a few
// seconds. The pending warning stays.
const showAllDone = ref(true)
let hideTimer: ReturnType<typeof setTimeout> | undefined

onMounted(() => {
    hideTimer = setTimeout(() => {
        showAllDone.value = false
    }, 5000)
})

onBeforeUnmount(() => clearTimeout(hideTimer))

// The COI row uses the same badge colours as the form rows.
const coiBadge = computed(() => {
    switch (props.coi.status) {
        case 'done':
            return { label: t.value.commitment.statusSubmitted, classes: 'bg-green-50 text-green-700 border-green-200' }

        case 'draft':
            return { label: t.value.forms.statusDraft, classes: 'bg-amber-50 text-amber-700 border-amber-200' }

        default:
            return { label: t.value.commitment.statusNotSubmitted, classes: 'bg-red-50 text-red-700 border-red-200' }
    }
})
</script>

<template>
    <Head :title="t.commitment.title" />

    <EmployeeLayout>
        <PageHeader
            :title="t.commitment.title"
            :description="t.commitment.description"
        />

        <FlashMessage
            type="success"
            :message="page.props.flash?.success"
        />

        <FlashMessage
            type="error"
            :message="page.props.flash?.error"
        />

        <Transition
            leave-active-class="transition-opacity duration-500"
            leave-to-class="opacity-0"
        >
            <div
                v-if="allDone && showAllDone"
                class="mb-6 flex items-center gap-2 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700"
            >
                <i class="fa-solid fa-circle-check" />

                <span>{{ t.commitment.allDone }}</span>
            </div>
        </Transition>

        <div
            v-if="!allDone"
            class="mb-6 flex items-center gap-2 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700"
        >
            <i class="fa-solid fa-triangle-exclamation" />

            <span>{{ pendingMessage }}</span>
        </div>

        <Card>
            <div class="table-container">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th class="py-3">{{ t.commitment.columnCommitment }}</th>
                            <th class="py-3">{{ t.history.columnPeriod }}</th>
                            <th class="py-3">{{ t.history.columnFormStatus }}</th>
                            <th class="py-3">{{ t.common.action }}</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr class="border-b border-slate-100 text-center">
                            <td class="py-4 text-left">
                                <div class="flex items-start gap-3">
                                    <i class="fa-solid fa-scale-balanced mt-1 w-4 text-primary" />

                                    <div>
                                        <div class="font-medium text-slate-800">
                                            {{ t.nav.conflictOfInterest }}
                                        </div>
                                        <div class="mt-0.5 text-xs text-slate-500">
                                            <template v-if="coi.last_submitted_at">
                                                {{ t.commitment.lastSubmitted }}: {{ formatDateTime(coi.last_submitted_at) }}
                                            </template>
                                            <template v-else>
                                                {{ t.commitment.coiDescription }}
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="py-4 font-semibold">
                                {{ coi.period }}
                            </td>

                            <td class="py-4">
                                <span
                                    class="inline-flex whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                                    :class="coiBadge.classes"
                                >
                                    {{ coiBadge.label }}
                                </span>
                            </td>

                            <td class="py-4">
                                <div class="flex justify-center">
                                    <Link
                                        v-if="coi.status === 'done'"
                                        :href="route('employee.history')"
                                        class="btn btn-outline-secondary btn-sm"
                                    >
                                        <i class="fa-solid fa-clock-rotate-left" />
                                        {{ t.commitment.viewHistory }}
                                    </Link>

                                    <Link
                                        v-else
                                        :href="route('employee.declarations.create', { locale })"
                                        class="btn btn-sm"
                                        :class="coi.status === 'draft'
                                            ? 'bg-yellow-400 text-white hover:bg-yellow-500 hover:font-bold'
                                            : 'btn-primary-custom'"
                                    >
                                        <i class="fa-solid fa-pen-to-square" />
                                        {{ coi.status === 'draft' ? t.common.continue : t.commitment.fillIn }}
                                    </Link>
                                </div>
                            </td>
                        </tr>

                        <tr
                            v-for="form in forms"
                            :key="`${form.status}-${form.id}`"
                            class="border-b border-slate-100 text-center"
                        >
                            <td class="py-4 text-left">
                                <div class="flex items-start gap-3">
                                    <i
                                        class="mt-1 w-4 text-primary"
                                        :class="form.form_type.icon"
                                    />

                                    <div>
                                        <div class="font-medium text-slate-800">
                                            {{ form.form_type.name[locale] }}
                                        </div>
                                        <div class="mt-0.5 text-xs text-slate-500">
                                            <template v-if="form.last_submitted_at">
                                                {{ t.commitment.lastSubmitted }}: {{ formatDateTime(form.last_submitted_at) }}
                                            </template>
                                            <template v-else>
                                                {{ form.title[locale] ?? '-' }}
                                            </template>
                                        </div>
                                        <div
                                            v-if="form.resign"
                                            class="mt-0.5 flex items-center gap-1 text-xs font-medium text-amber-700"
                                        >
                                            <i class="fa-solid fa-rotate" />
                                            {{ t.forms.resignRequired }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="py-4 font-semibold">
                                {{ form.period }}
                            </td>

                            <td class="py-4">
                                <FormStatusBadge :status="form.status" />
                            </td>

                            <td class="py-4">
                                <div class="flex justify-center">
                                    <Link
                                        v-if="form.status === 'signed'"
                                        :href="route('employee.forms.show', { formType: form.form_type.code })"
                                        class="btn btn-outline-secondary btn-sm"
                                    >
                                        <i class="fa-solid fa-clock-rotate-left" />
                                        {{ t.commitment.viewHistory }}
                                    </Link>

                                    <Link
                                        v-else
                                        :href="route('employee.forms.read', { formType: form.form_type.code, template: form.id })"
                                        class="btn btn-sm"
                                        :class="form.draft_saved_at
                                            ? 'bg-yellow-400 text-white hover:bg-yellow-500 hover:font-bold'
                                            : 'btn-primary-custom'"
                                    >
                                        <i class="fa-solid fa-book-open" />
                                        {{ form.draft_saved_at ? t.common.continue : t.forms.openToRead }}
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </EmployeeLayout>
</template>
