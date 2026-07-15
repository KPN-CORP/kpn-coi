<script setup lang="ts">
import { computed, ref } from 'vue'
import Dropdown from '@/Components/Dropdown.vue'

interface Option {
    value: string | number
    label: string
}

const props = withDefaults(
    defineProps<{
        modelValue: string | number | null
        options: Option[]
        placeholder?: string
        disabled?: boolean
    }>(),
    {
        placeholder: 'Select...',
        disabled: false,
    },
)

const emit = defineEmits<{
    'update:modelValue': [string | number | null]
}>()

const keyword = ref('')

const filteredOptions = computed(() => {
    if (!keyword.value) {
        return props.options
    }

    return props.options.filter(option =>
        option.label
            .toLowerCase()
            .includes(keyword.value.toLowerCase()),
    )
})

const selectedOption = computed(() =>
    props.options.find(
        option => option.value === props.modelValue,
    ),
)

function select(
    option: Option,
    close: () => void,
) {
    emit('update:modelValue', option.value)

    keyword.value = ''

    close()
}
</script>

<template>
    <Dropdown
        align="left"
        width="72"
    >
        <template #trigger>
            <button
                type="button"
                :disabled="disabled"
                class="flex w-full items-center justify-between rounded-md border border-border bg-white px-3 py-2 text-left text-sm disabled:bg-gray-100"
            >
                <span
                    class="truncate"
                >
                    {{
                        selectedOption?.label ??
                        placeholder
                    }}
                </span>

                <i class="fa-solid fa-chevron-down text-xs" />
            </button>
        </template>

        <template #content="{ close }">
            <div class="w-72 p-3">
                <input
                    v-model="keyword"
                    type="text"
                    placeholder="Search..."
                    class="form-input mb-3"
                    @click.stop
                >

                <div class="max-h-64 overflow-y-auto">
                    <button
                        v-for="option in filteredOptions"
                        :key="option.value"
                        type="button"
                        class="flex w-full items-center rounded-md px-2 py-2 text-left text-sm hover:bg-slate-100"
                        @click.stop="
                            select(option, close)
                        "
                    >
                        {{ option.label }}
                    </button>

                    <div
                        v-if="!filteredOptions.length"
                        class="py-3 text-center text-sm text-slate-400"
                    >
                        No data found
                    </div>
                </div>
            </div>
        </template>
    </Dropdown>
</template>