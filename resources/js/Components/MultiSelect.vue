<script setup lang="ts">
import Multiselect from '@vueform/multiselect'
import { computed } from 'vue'

interface Option {
    code: string | number
    name: string
}

const props = defineProps<{
    modelValue: (string | number)[]
    options: Option[]
    placeholder?: string
}>()

const emit = defineEmits<{
    'update:modelValue': [(string | number)[]]
}>()

const value = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val),
})

const multiselectOptions = computed(() =>
    (props.options ?? []).map(option => ({
        value: option.code,
        label: option.name,
    })),
)
</script>

<template>
    <Multiselect
        v-model="value"
        :options="multiselectOptions"
        mode="tags"
        searchable
        :close-on-select="false"
        :clear-on-select="false"
        :hide-selected="false"
        :placeholder="placeholder ?? 'Select option'"
    />
</template> 