<script setup lang="ts">
import { watch } from 'vue'

interface Field {
    key: string
    label: string
}

const model = defineModel<Record<string, any>[]>({
    default: () => [],
})

const props = defineProps<{
    fields: Field[]
    questionKey: string
    errors: Record<string, string>
}>()

const emit = defineEmits<{
    clearError: [key: string]
}>()

// Watch model changes and clear the matching error
watch(
    model,
    (newVal) => {
        newVal.forEach((row, index) => {
            props.fields.forEach(field => {
                if (row[field.key]?.trim()) {
                    const key = `responses.${props.questionKey}.details.${index}.${field.key}`
                    emit('clearError', key)
                }
            })
        })
    },
    { deep: true }
)

function addRow() { model.value.push({}) }
function removeRow(index: number) { model.value.splice(index, 1) }
function getError(index: number, field: string): string | undefined {
    return props.errors[`responses.${props.questionKey}.details.${index}.${field}`]
}
function onInput(index: number, fieldKey: string) {
    const key = `responses.${props.questionKey}.details.${index}.${fieldKey}`
    emit('clearError', key)  // ← tell parent to clear this error
}
</script>

<template>
    <div class="mt-4 rounded-md border border-dashed border-slate-300 bg-slate-50 p-4">
        <div
            v-for="(row, index) in model"
            :key="index"
            class="mb-4 border-b border-slate-200 pb-4 last:mb-0 last:border-0"
        >
            <div class="grid gap-4 md:grid-cols-2">
                <div v-for="field in fields" :key="field.key">
                    <label class="mb-1 block text-xs font-semibold">
                        {{ field.label }}
                    </label>

                    <input
                        v-model="row[field.key]"
                        type="text"
                        :class="[
                            'w-full rounded-md border px-3 py-2 text-sm transition-colors',
                            getError(index, field.key)
                                ? 'border-red-500 bg-red-50 focus:outline-none focus:ring-1 focus:ring-red-500'
                                : 'border-border focus:outline-none focus:ring-1 focus:ring-primary'
                        ]"
                        @input="onInput(index, field.key)"
                    />

                    <p
                        v-if="getError(index, field.key)"
                        class="mt-1 flex items-center gap-1 text-xs text-red-500"
                    >
                        <svg class="h-3 w-3 shrink-0" viewBox="0 0 12 12" fill="currentColor">
                            <path d="M6 1a5 5 0 1 0 0 10A5 5 0 0 0 6 1zm0 3a.75.75 0 0 1 .75.75v2a.75.75 0 0 1-1.5 0v-2A.75.75 0 0 1 6 4zm0 5a.75.75 0 1 1 0-1.5A.75.75 0 0 1 6 9z"/>
                        </svg>
                        {{ getError(index, field.key) }}
                    </p>
                </div>
            </div>

            <button
                type="button"
                class="mt-3 rounded-md bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700"
                @click="removeRow(index)"
            >
                Remove
            </button>
        </div>

        <button
            type="button"
            class="rounded-md border border-primary px-4 py-2 text-sm font-medium text-primary hover:bg-primary/5"
            @click="addRow"
        >
            + Add Detail
        </button>
    </div>
</template>