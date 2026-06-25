<script setup lang="ts">
import { computed, ref } from 'vue'
import Dropdown from '@/Components/Dropdown.vue'

interface Option {
    code: string
    name: string
}

const props = defineProps<{
    modelValue: string[]
    label: string
    options: Option[]
}>()

const emit = defineEmits<{
    'update:modelValue': [string[]]
}>()

const keyword = ref('')

const selected = computed({
    get: () => props.modelValue,
    set: (value: string[]) => {
        emit('update:modelValue', value)
    },
})

const filteredOptions = computed(() => {
    const options = props.options ?? []

    if (!keyword.value) {
        return options
    }

    return options.filter(option =>
        option.name
            .toLowerCase()
            .includes(
                keyword.value.toLowerCase(),
            ),
    )
})

function toggle(value: string) {
    const items = [...selected.value]

    const index =
        items.indexOf(value)

    if (index > -1) {
        items.splice(index, 1)
    } else {
        items.push(value)
    }

    selected.value = items
}

const selectedCount = computed(
    () => selected.value?.length ?? 0,
)
</script>

<template>
    <Dropdown
        align="left"
        width="48"
    >
        <template #trigger>
            <button
                type="button"
                class="form-select flex w-full items-center justify-between"
            >
                <span>
                    {{
                        selectedCount
                            ? `${selectedCount} Selected`
                            : label
                    }}
                </span>

            <i
                class="fa-solid fa-chevron-down text-xs"
            />
        </button>
    </template>

    <template #content>
        <div
            class="w-72 p-3"
        >
            <input
                v-model="keyword"
                type="text"
                placeholder="Search..."
                class="form-input mb-3"
            >

            <div
                class="max-h-64 overflow-y-auto"
            >
                <label
                    v-for="option in filteredOptions"
                    :key="option.code"
                    class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-2 hover:bg-slate-50"
                >
                    <input
                        :checked="
                            selected.includes(
                                option.code,
                            )
                        "
                        type="checkbox"
                        @change="
                            toggle(
                                option.code,
                            )
                        "
                    >

                    <span
                        class="text-sm"
                    >
                        {{ option.name }}
                    </span>
                </label>

                <div
                    v-if="
                        !filteredOptions.length
                    "
                    class="py-3 text-center text-sm text-slate-400"
                >
                    No data found
                </div>
            </div>
        </div>
    </template>
</Dropdown>

</template>
