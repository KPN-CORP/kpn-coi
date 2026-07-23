<script setup lang="ts">
import Card from '@/Components/UI/Card.vue'
import Modal from '@/Components/UI/Modal.vue'
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useLocale } from '@/Composables/useLocale'
import { formatDate } from '@/Utils/date'

const page = usePage()

const { t, locale } = useLocale()

const coiQuestions = computed(
    () => page.props.coiQuestions as any[]
)

// code -> name lookup for DB-backed multi-selects (e.g. company)
const companyNames = computed(
    () => (page.props.companyNames as Record<string, string>) ?? {}
)

function getQuestionConfig(key: string) {
    return coiQuestions.value.find(
        q => q.key === key
    )
}

function getFieldLabel(
    questionKey: string,
    fieldKey: string,
) {
    const question = getQuestionConfig(questionKey)

    const field = question?.fields.find(
        (f: any) => f.key === fieldKey
    )

    return field?.label?.[locale.value] ?? fieldKey
}

function getFieldValue(
    questionKey: string,
    fieldKey: string,
    value: any,
) {
    // Multi-select values (e.g. company) arrive as an array of codes;
    // resolve each code to its display name when we have the lookup.
    if (Array.isArray(value)) {
        return value
            .map((code: string) => companyNames.value[code] ?? code)
            .join(', ')
    }

    const question = getQuestionConfig(questionKey)

    const field = question?.fields.find(
        (f: any) => f.key === fieldKey
    )

    if (!field) {
        return value
    }

    if (
        field.type === 'select'
    ) {

        const option = field.options?.find(
            (o: any) => o.value === value
        )

        return option?.label?.[locale.value] ?? value
    }

    return value
}

defineProps({
    show: {
        type: Boolean,
        required: true,
    },

    declaration: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits<{
    close: []
}>()
</script>

<template>
    <Modal
        :show="show"
        maxWidth="max-w-5xl"
        @close="emit('close')"
    >
        <!-- Header -->

        <div
            class="flex items-center justify-between border-b border-border px-6 py-4"
        >
            <h2 class="text-lg font-bold">
                {{ t.declarationModal.viewTitle }} -
                {{
                    declaration.type === 'employee'
                        ? t.common.employee
                        : t.common.nonEmployee
                }}
            </h2>

            <button
                type="button"
                class="rounded-md p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                @click="emit('close')"
            >
                <i class="fa-solid fa-xmark text-lg" />
            </button>
        </div>

        <!-- Scrollable Body -->

        <div class="flex-1 overflow-y-auto p-6">
            <!-- Employee Information -->

            <Card class="mb-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label
                            class="mb-1 block text-xs font-semibold"
                        >
                            {{ t.common.fullName }}
                        </label>

                        <input
                            :value="declaration.employee.name"
                            disabled
                            class="w-full rounded-md border border-border bg-slate-100 px-3 py-2"
                        >
                    </div>

                    <div>
                        <label
                            class="mb-1 block text-xs font-semibold"
                        >
                            {{ t.declarationModal.employeeIdCitizenship }}
                        </label>

                        <input
                            :value="declaration.employee.employee_id ?? declaration.employee.citizenship_id"
                            disabled
                            class="w-full rounded-md border border-border bg-slate-100 px-3 py-2"
                        >
                    </div>
                </div>
            </Card>

            <!-- Questions -->

            <div
                v-for="question in declaration.questions"
                :key="question.key"
                class="mb-4 rounded-md border border-border bg-white p-4"
            >
                <div class="mb-3 font-semibold">
                    {{ question.title }}
                </div>

                <span
                    v-if="question.answer"
                    class="rounded-md border border-red-200 bg-red-50 px-2 py-1 text-xs font-semibold text-red-700"
                >
                    {{ t.common.yes }}
                </span>

                <span
                    v-else
                    class="rounded-md border border-green-200 bg-green-50 px-2 py-1 text-xs font-semibold text-green-700"
                >
                    {{ t.common.no }}
                </span>

                <!-- Details -->

                <div
                    v-if="question.details?.length"
                    class="mt-4 overflow-x-auto"
                >
                    <table
                        class="min-w-full border border-border"
                    >
                        <thead>

                            <tr>

                            <th
                                v-for="field in getQuestionConfig(question.key)?.fields"
                                :key="field.key"
                                class="border border-border bg-slate-100 p-2 text-left text-xs font-semibold"
                            >

                            {{ field.label[locale] }}

                            </th>

                            </tr>

                        </thead>

                        <tbody>

                            <tr
                                v-for="(detail,index) in question.details"
                                :key="index"
                            >

                            <td
                                v-for="field in getQuestionConfig(question.key)?.fields"
                                :key="field.key"
                                class="border border-border p-2"
                            >

                            <!-- Date Range -->

                            <template v-if="field.type === 'date_range'">
                                {{ formatDate(detail[`${field.key}_from`]) }}
                                -
                                {{
                                    detail[`${field.key}_current`]
                                        ? t.common.current
                                        : formatDate(detail[`${field.key}_to`])
                                }}
                            </template>

                            <template v-else-if="field.type === 'year'">
                                {{ detail[field.key] || '-' }}
                            </template>

                            <!-- Normal -->

                            <template
                                v-else
                            >

                            {{ getFieldValue(
                                question.key,
                                field.key,
                                detail[field.key],
                            ) }}

                            </template>

                            <!-- Requires -->

                            <div
                                v-if="
                                    field.type === 'select'
                                "
                                class="mt-2"
                            >

                            <div
                                v-for="required in field.options
                                    ?.find(
                                        o => o.value === detail[field.key]
                                    )
                                    ?.requires ?? []"
                                :key="required.key"
                                class="text-xs text-slate-500"
                            >

                            <strong>

                            {{ required.label[locale] }}

                            </strong>

                            :

                            {{ detail[required.key] }}

                            </div>

                            </div>

                            </td>

                            </tr>

                            </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer -->

        <div
            class="flex justify-end gap-2 border-t border-border px-6 py-4"
        >
            <button
                type="button"
                class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                @click="emit('close')"
            >
                {{ t.common.close }}
            </button>
        </div>
    </Modal>
</template>